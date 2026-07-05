<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Report;
use App\Models\Alert;
use App\Models\ReportComment;
use App\Models\PlaceReview;
use App\Services\AchievementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserProfileController extends Controller
{
    /**
     * Get a public user profile by ID (no auth required).
     */
    public function show($id)
    {
        $user = User::findOrFail($id);

        $totalReports = Report::where('user_id', $user->id)->count();
        $approvedReports = Report::where('user_id', $user->id)->where('status', 'approved')->count();
        $approvalRate = $totalReports > 0 ? round(($approvedReports / $totalReports) * 100, 1) : 0;

        $rank = User::where('total_xp', '>', $user->total_xp)->count() + 1;

        $achievementService = app(AchievementService::class);

        $nextLevelXp = $achievementService->getNextLevelXp($user->current_level);
        $levelProgress = $achievementService->getLevelProgress($user);

        $badges = $achievementService->getUserAchievements($user);

        $recentReports = Report::where('user_id', $user->id)
            ->with(['category', 'media'])
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($r) => [
                'id' => (string) $r->id,
                'title' => $r->title,
                'description' => $r->description,
                'priority' => $r->priority,
                'category_name' => $r->category?->name ?? 'Unknown',
                'image_url' => $r->media->first() ? asset('storage/'.$r->media->first()->media_url) : null,
                'helpful_count' => $r->helpful_count,
                'comments_count' => $r->comments_count,
                'time_ago' => $r->created_at?->diffForHumans(),
                'created_at' => $r->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => (string) $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar,
                'bio' => $user->bio,
                'role' => $user->roleName ?? 'user',
                'verification_tick' => $user->verification_tick ?? 'none',
                'member_since_days' => $user->created_at ? $user->created_at->diffInDays(now()) : 0,

                'total_xp' => (int) ($user->total_xp ?? 0),
                'current_level' => (int) ($user->current_level ?? 1),
                'level_name' => $achievementService->getLevelName($user->current_level ?? 1),
                'next_level_name' => $achievementService->getNextLevelName($user->current_level ?? 1),
                'next_level_xp' => $nextLevelXp,
                'level_progress' => $levelProgress,
                'rank' => $rank,

                'total_reports' => $totalReports,
                'approved_reports' => $approvedReports,
                'approval_rate' => $approvalRate,

                'badges' => $badges,
                'recent_reports' => $recentReports,
            ]
        ]);
    }


}
