<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    /**
     * Get leaderboard rankings based on XP and contributions
     */
    public function index(Request $request)
    {
        $period = $request->input('period', 'all_time');
        $category = $request->input('category', 'xp');
        $limit = min((int) $request->input('limit', 50), 100);
        $offset = (int) $request->input('offset', 0);

        $query = User::query();

        // Apply category sorting with pre-loaded counts (avoids N+1)
        switch ($category) {
            case 'reports':
                $query->withCount('reports')->orderByDesc('reports_count');
                break;
            case 'alerts':
                $query->withCount('alerts')->orderByDesc('alerts_count');
                break;
            case 'reviews':
                $query->withCount('reviews')->orderByDesc('reviews_count');
                break;
            case 'xp':
            default:
                $query->withCount(['reports', 'alerts', 'reviews'])->orderByDesc('total_xp');
                break;
        }

        // Apply period filter based on actual contribution activity
        if ($period === 'weekly' || $period === 'monthly') {
            $dateThreshold = $period === 'weekly' ? now()->subWeek() : now()->subMonth();
            $query->where(function ($q) use ($dateThreshold) {
                $q->whereHas('reports', fn($r) => $r->where('created_at', '>=', $dateThreshold))
                  ->orWhereHas('alerts', fn($a) => $a->where('created_at', '>=', $dateThreshold))
                  ->orWhereHas('reviews', fn($r) => $r->where('created_at', '>=', $dateThreshold));
            });
        }

        $total = $query->count();
        $users = $query->skip($offset)->take($limit)->get();

        // Get the current user's rank (efficient single query)
        $currentUser = $request->user();
        $userRank = null;
        if ($currentUser) {
            switch ($category) {
                case 'xp':
                default:
                    $userRank = User::where('total_xp', '>', $currentUser->total_xp)->count() + 1;
                    break;
                case 'reports':
                    $reportsCount = $currentUser->reports()->count();
                    $userRank = DB::table('users')
                        ->whereExists(function ($q) {
                            $q->select(DB::raw(1))
                              ->from('reports')
                              ->whereColumn('reports.user_id', 'users.id');
                        })
                        ->where('id', '!=', $currentUser->id)
                        ->count() + 1;
                    break;
                case 'alerts':
                case 'reviews':
                    $userRank = 0;
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $users->map(fn($user, $index) => [
                'rank' => $offset + $index + 1,
                'user_id' => (string) $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar,
                'total_xp' => (int) ($user->total_xp ?? 0),
                'total_points' => (int) ($user->total_xp ?? 0),
                'current_level' => (int) ($user->current_level ?? 1),
                'level_name' => app(AchievementService::class)->getLevelName($user->current_level ?? 1),
                'approved_reports' => (int) ($user->approved_reports ?? 0),
                'total_reports' => (int) ($user->reports_count ?? $user->reports()->count()),
                'total_alerts' => (int) ($user->alerts_count ?? $user->alerts()->count()),
                'total_reviews' => (int) ($user->reviews_count ?? $user->reviews()->count()),
                'badge_count' => UserAchievement::where('user_id', $user->id)->count(),
                'verification_tick' => $user->verification_tick ?? 'none',
            ]),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
                'system_total_xp' => User::sum('total_xp'),
                'system_total_points' => User::sum('total_xp'),
                'system_average_xp' => User::count() ? round(User::avg('total_xp'), 2) : 0,
                'system_total_users' => User::count(),
                'user_rank' => $userRank,
            ],
        ]);
    }

    /**
     * Get the top 3 users (podium) for quick display
     */
    public function topThree()
    {
        $topUsers = User::orderByDesc('total_xp')->take(3)->get();

        return response()->json([
            'success' => true,
            'data' => $topUsers->map(fn($user, $index) => [
                'rank' => $index + 1,
                'user_id' => (string) $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar,
                'total_xp' => (int) ($user->total_xp ?? 0),
                'total_points' => (int) ($user->total_xp ?? 0),
                'current_level' => (int) ($user->current_level ?? 1),
                'level_name' => app(AchievementService::class)->getLevelName($user->current_level ?? 1),
                'approved_reports' => (int) ($user->approved_reports ?? 0),
                'verification_tick' => $user->verification_tick ?? 'none',
            ]),
        ]);
    }


}