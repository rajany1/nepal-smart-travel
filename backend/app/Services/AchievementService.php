<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Alert;
use App\Models\PlaceReview;
use App\Models\Report;
use App\Models\ReportComment;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\XpTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AchievementService
{
    public function awardXp(User $user, int $amount, string $actionType, ?string $description = null, $reference = null): void
    {
        if ($amount <= 0) return;

        DB::transaction(function () use ($user, $amount, $actionType, $description, $reference) {
            $user->increment('total_xp', $amount);

            $data = [
                'user_id' => $user->id,
                'amount' => $amount,
                'action_type' => $actionType,
                'description' => $description,
                'metadata' => null,
            ];

            if ($reference) {
                $data['reference_id'] = $reference->id;
                $data['reference_type'] = get_class($reference);
            }

            XpTransaction::create($data);

            $this->checkLevelUp($user);
            $this->checkAndAwardAchievements($user);
        });
    }

    public function deductXp(User $user, int $amount, string $actionType, ?string $description = null): void
    {
        if ($amount <= 0) return;

        $actualDeduction = min($amount, $user->total_xp);

        DB::transaction(function () use ($user, $actualDeduction, $actionType, $description) {
            $user->decrement('total_xp', $actualDeduction);

            XpTransaction::create([
                'user_id' => $user->id,
                'amount' => -$actualDeduction,
                'action_type' => $actionType,
                'description' => $description,
            ]);

            if ($user->total_xp < 0) {
                $user->update(['total_xp' => 0]);
            }

            $this->checkLevelUp($user);
        });
    }

    public function checkLevelUp(User $user): void
    {
        $newLevel = $this->calculateLevel($user->total_xp);
        if ($newLevel !== $user->current_level) {
            $user->update(['current_level' => $newLevel]);
            $this->checkAndAwardAchievements($user);
        }
    }

    public function calculateLevel(int $totalXp): int
    {
        $level = 1;
        $cumulative = 0;

        while (true) {
            $needed = $this->xpForLevel($level);
            if ($needed === 0) break;
            $cumulative += $needed;
            if ($totalXp < $cumulative) break;
            $level++;
        }

        return $level;
    }

    public function xpForLevel(int $level): int
    {
        if ($level <= 5) return 50;
        if ($level <= 15) return 150;
        if ($level <= 30) return 300;
        if ($level <= 50) return 500;
        if ($level <= 100) return 1000;
        return 0;
    }

    public function getLevelName(int $level): string
    {
        if ($level <= 5) return 'Explorer';
        if ($level <= 15) return 'Contributor';
        if ($level <= 30) return 'Trusted Local';
        if ($level <= 50) return 'Regional Guide';
        if ($level <= 100) return 'Community Expert';
        return 'Legendary Hero';
    }

    public function getNextLevelName(int $level): string
    {
        if ($level <= 5) return 'Contributor';
        if ($level <= 15) return 'Trusted Local';
        if ($level <= 30) return 'Regional Guide';
        if ($level <= 50) return 'Community Expert';
        if ($level <= 100) return 'Legendary Hero';
        return 'Max Level';
    }

    public function getNextLevelXp(int $level): int
    {
        if ($level <= 5) return 50;
        if ($level <= 15) return 150;
        if ($level <= 30) return 300;
        if ($level <= 50) return 500;
        if ($level <= 100) return 1000;
        return 0;
    }

    public function getLevelProgress(User $user): float
    {
        $level = $user->current_level ?? 1;
        $nextXp = $this->xpForLevel($level);
        if ($nextXp <= 0) return 1.0;

        $cumulativeBefore = 0;
        for ($i = 1; $i < $level; $i++) {
            $cumulativeBefore += $this->xpForLevel($i);
        }

        $xpInCurrentLevel = max(0, $user->total_xp - $cumulativeBefore);

        return min($xpInCurrentLevel / $nextXp, 1.0);
    }

    public function checkAndAwardAchievements(User $user): void
    {
        $achievements = Achievement::all();
        $userStats = $this->getUserStats($user);

        foreach ($achievements as $achievement) {
            $alreadyUnlocked = $user->achievements()
                ->where('achievement_id', $achievement->id)
                ->exists();

            if ($alreadyUnlocked) continue;

            $criteria = $achievement->criteria;
            if (!$criteria) continue;

            if ($this->evaluateCriteria($criteria, $userStats, $user)) {
                $user->achievements()->attach($achievement->id, [
                    'unlocked_at' => now(),
                    'metadata' => json_encode(['stats' => $userStats]),
                ]);

                if ($achievement->xp_reward > 0) {
                    XpTransaction::create([
                        'user_id' => $user->id,
                        'amount' => $achievement->xp_reward,
                        'action_type' => 'achievement_reward',
                        'description' => "Achievement unlocked: {$achievement->display_name}",
                    ]);
                    $user->increment('total_xp', $achievement->xp_reward);
                    $this->checkLevelUp($user);
                }
            }
        }
    }

    public function getUserStats(User $user): array
    {
        $totalReports = Report::where('user_id', $user->id)->count();
        $approvedReports = Report::where('user_id', $user->id)->where('status', 'approved')->count();
        $hasAlertCreator = Schema::hasColumn('alerts', 'created_by');
        $totalAlerts = $hasAlertCreator ? Alert::where('created_by', $user->id)->count() : 0;
        $totalReviews = PlaceReview::where('user_id', $user->id)->count();
        $totalComments = ReportComment::where('user_id', $user->id)->count();
        $criticalAlerts = $hasAlertCreator ? Alert::where('created_by', $user->id)->where('severity', 'critical')->count() : 0;

        return [
            'total_reports' => $totalReports,
            'approved_reports' => $approvedReports,
            'total_alerts' => $totalAlerts,
            'total_reviews' => $totalReviews,
            'total_comments' => $totalComments,
            'critical_alerts' => $criticalAlerts,
            'current_level' => $user->current_level ?? 1,
        ];
    }

    private function evaluateCriteria(array $criteria, array $stats, User $user): bool
    {
        $type = $criteria['type'] ?? null;
        $value = $criteria['value'] ?? 0;

        return match ($type) {
            'reports_count' => ($stats['total_reports'] ?? 0) >= $value,
            'approved_reports' => ($stats['approved_reports'] ?? 0) >= $value,
            'alerts_count' => ($stats['total_alerts'] ?? 0) >= $value,
            'reviews_count' => ($stats['total_reviews'] ?? 0) >= $value,
            'comments_count' => ($stats['total_comments'] ?? 0) >= $value,
            'level_reached' => ($stats['current_level'] ?? 1) >= $value,
            'critical_alerts' => ($stats['critical_alerts'] ?? 0) >= $value,
            default => false,
        };
    }

    public function getUserAchievements(User $user): array
    {
        $all = Achievement::orderBy('sort_order')->orderBy('display_name')->get();
        $unlockedIds = $user->achievements()
            ->withPivot(['unlocked_at', 'is_suspicious', 'suspicious_reason'])
            ->get()
            ->keyBy('id');

        return $all->map(function ($achievement) use ($unlockedIds) {
            $pivot = $unlockedIds->get($achievement->id);
            return [
                'id' => $achievement->name,
                'name' => $achievement->display_name,
                'description' => $achievement->description,
                'icon' => $achievement->icon,
                'category' => $achievement->category,
                'xp_reward' => $achievement->xp_reward,
                'unlocked' => $pivot !== null,
                'unlocked_at' => $pivot?->pivot->unlocked_at,
                'is_suspicious' => $pivot?->pivot->is_suspicious ?? false,
                'suspicious_reason' => $pivot?->pivot->suspicious_reason,
            ];
        })->values()->toArray();
    }

    public function getXpHistory(User $user, int $perPage = 30)
    {
        return XpTransaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function flagAchievement(int $userAchievementId, string $reason, int $flaggedBy): void
    {
        UserAchievement::where('id', $userAchievementId)
            ->update([
                'is_suspicious' => true,
                'suspicious_reason' => $reason,
                'flagged_by' => $flaggedBy,
            ]);
    }

    public function clearSuspicious(int $userAchievementId, int $clearedBy): void
    {
        UserAchievement::where('id', $userAchievementId)
            ->update([
                'is_suspicious' => false,
                'suspicious_reason' => null,
                'flagged_by' => null,
                'cleared_at' => now(),
                'cleared_by' => $clearedBy,
            ]);
    }

    public function recalculateLevelForUser(User $user): void
    {
        $newLevel = $this->calculateLevel($user->total_xp);
        if ($newLevel !== $user->current_level) {
            $user->update(['current_level' => $newLevel]);
        }
    }
}
