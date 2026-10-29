<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OriporiCoinWallet;
use App\Models\User;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use Illuminate\Database\Eloquent\Builder;

class LeaderboardController extends Controller
{
    /**
     * Ranked categories. Reviews are intentionally not a standalone category;
     * they feed the composite "contributions" score.
     */
    private const CATEGORIES = ['xp', 'reports', 'alerts', 'earnings', 'contributions'];

    /**
     * Composite map-contribution score: approved user-submitted places +
     * applied place corrections + visible reviews.
     */
    private const CONTRIBUTION_SCORE_SQL = "
        (SELECT COUNT(*) FROM places
            WHERE places.created_by = users.id
              AND places.source = 'user_submitted'
              AND places.is_verified = 1)
        + (SELECT COUNT(*) FROM place_corrections
            WHERE place_corrections.user_id = users.id
              AND place_corrections.status = 'applied')
        + (SELECT COUNT(*) FROM place_reviews
            WHERE place_reviews.user_id = users.id
              AND (place_reviews.moderation_status IS NULL
                   OR place_reviews.moderation_status = 'approved'))
    ";

    /**
     * Get leaderboard rankings based on XP and contributions.
     *
     * Only regular users compete: staff (admin, super admin, moderator,
     * business) and system accounts never appear on the board.
     */
    public function index(Request $request)
    {
        $period = $request->input('period', 'all_time');
        $category = $this->normalizeCategory($request->input('category', 'xp'));
        $limit = min((int) $request->input('limit', 50), 100);
        $offset = (int) $request->input('offset', 0);

        // Total eligible users (separate from the ranked query so ordering by
        // computed aliases can't break the aggregate).
        $countQuery = $this->eligibleQuery();
        $this->applyPeriodFilter($countQuery, $category, $period);
        $total = $countQuery->count();

        $listQuery = $this->rankedQuery($category);
        $this->applyPeriodFilter($listQuery, $category, $period);
        $users = $listQuery
            ->skip($offset)
            ->take($limit)
            ->get();

        // Preload badge counts for all users in one query
        $badgeCounts = UserAchievement::whereIn('user_id', $users->pluck('id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as count')
            ->pluck('count', 'user_id');

        // Get the current user's rank (null for staff / system accounts)
        $userRank = null;
        if ($currentUser = $request->user()) {
            $userRank = $this->rankFor($category, $currentUser);
        }

        $systemUsers = $this->eligibleQuery()->count();

        return response()->json([
            'success' => true,
            'data' => $users->map(
                fn ($user, $index) => $this->mapUser($user, $offset + $index + 1, $badgeCounts)
            ),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
                'category' => $category,
                'system_total_xp' => (int) $this->eligibleQuery()->sum('total_xp'),
                'system_total_points' => (int) $this->eligibleQuery()->sum('total_xp'),
                'system_average_xp' => $systemUsers ? round($this->eligibleQuery()->avg('total_xp'), 2) : 0,
                'system_total_users' => $systemUsers,
                'user_rank' => $userRank,
            ],
        ]);
    }

    /**
     * Get the top 3 users (podium) for quick display.
     */
    public function topThree(Request $request)
    {
        $category = $this->normalizeCategory($request->input('category', 'xp'));

        $users = $this->rankedQuery($category)->take(3)->get();

        $badgeCounts = UserAchievement::whereIn('user_id', $users->pluck('id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as count')
            ->pluck('count', 'user_id');

        return response()->json([
            'success' => true,
            'data' => $users->map(
                fn ($user, $index) => $this->mapUser($user, $index + 1, $badgeCounts)
            ),
        ]);
    }

    /**
     * Users eligible to compete: regular users only, no staff, no system
     * accounts.
     */
    private function eligibleQuery(): Builder
    {
        return User::query()
            ->whereRelation('role', 'name', 'user')
            ->where('users.is_system', false);
    }

    /**
     * Eligible users with the aggregate counts/values needed for ranking and
     * for the row payloads, ordered by the requested category.
     */
    private function rankedQuery(string $category): Builder
    {
        $query = $this->eligibleQuery()
            ->select('users.*')
            ->withCount([
                'reports',
                'alerts',
                'reviews as reviews_count' => fn (Builder $q) => $q
                    ->where(fn (Builder $visible) => $visible
                        ->whereNull('moderation_status')
                        ->orWhere('moderation_status', 'approved')),
                'places as places_count' => fn (Builder $q) => $q
                    ->where('source', 'user_submitted')
                    ->where('is_verified', true),
                'placeCorrections as corrections_count' => fn (Builder $q) => $q
                    ->where('status', 'applied'),
            ])
            ->addSelect([
                'earned_coins' => OriporiCoinWallet::query()
                    ->selectRaw('COALESCE(total_earned, 0)')
                    ->whereColumn('oripori_coin_wallets.user_id', 'users.id')
                    ->limit(1),
            ]);

        switch ($category) {
            case 'reports':
                $query->orderByDesc('reports_count');
                break;
            case 'alerts':
                $query->orderByDesc('alerts_count');
                break;
            case 'earnings':
                $query->orderByDesc('earned_coins');
                break;
            case 'contributions':
                $query->orderByRaw('(places_count + corrections_count + reviews_count) DESC');
                break;
            case 'xp':
            default:
                $query->orderByDesc('total_xp');
                break;
        }

        return $query->orderBy('users.id');
    }

    /**
     * Narrow the board to users with activity inside the requested window.
     * Ranks still use the lifetime metric (existing behaviour) - the period
     * only decides who qualifies as active.
     */
    private function applyPeriodFilter(Builder $query, string $category, string $period): void
    {
        if (!in_array($period, ['weekly', 'monthly'], true)) {
            return;
        }

        $threshold = $period === 'weekly' ? now()->subWeek() : now()->subMonth();

        switch ($category) {
            case 'reports':
                $query->whereHas('reports', fn ($q) => $q->where('created_at', '>=', $threshold));
                break;
            case 'alerts':
                $query->whereHas('alerts', fn ($q) => $q->where('created_at', '>=', $threshold));
                break;
            case 'earnings':
                $query->whereHas('coinTransactions', fn ($q) => $q
                    ->where('created_at', '>=', $threshold)
                    ->where('amount', '>', 0));
                break;
            case 'contributions':
                $query->where(fn ($q) => $q
                    ->whereHas('places', fn ($p) => $p
                        ->where('created_at', '>=', $threshold)
                        ->where('source', 'user_submitted'))
                    ->orWhereHas('placeCorrections', fn ($c) => $c
                        ->where('created_at', '>=', $threshold)
                        ->where('status', 'applied'))
                    ->orWhereHas('reviews', fn ($r) => $r->where('created_at', '>=', $threshold)));
                break;
            case 'xp':
            default:
                $query->whereHas('xpTransactions', fn ($q) => $q
                    ->where('created_at', '>=', $threshold)
                    ->where('amount', '>', 0));
                break;
        }
    }

    /**
     * Competition rank of the given user (strictly-greater count + 1).
     * Returns null when the user is not eligible to compete.
     */
    private function rankFor(string $category, User $user): ?int
    {
        if (!$user->isRegularUser() || $user->is_system) {
            return null;
        }

        $query = $this->eligibleQuery();

        switch ($category) {
            case 'reports':
                $mine = $user->reports()->count();
                $query->whereRaw('(SELECT COUNT(*) FROM reports WHERE reports.user_id = users.id) > ?', [$mine]);
                break;
            case 'alerts':
                $mine = $user->alerts()->count();
                $query->whereRaw('(SELECT COUNT(*) FROM alerts WHERE alerts.created_by = users.id) > ?', [$mine]);
                break;
            case 'earnings':
                $mine = (float) ($user->wallet()->value('total_earned') ?? 0);
                $query->whereRaw(
                    '(SELECT COALESCE(total_earned, 0) FROM oripori_coin_wallets WHERE oripori_coin_wallets.user_id = users.id) > ?',
                    [$mine]
                );
                break;
            case 'contributions':
                $mine = $this->contributionScore($user);
                $query->whereRaw('(' . self::CONTRIBUTION_SCORE_SQL . ') > ?', [$mine]);
                break;
            case 'xp':
            default:
                $query->where('users.total_xp', '>', $user->total_xp);
                break;
        }

        return $query->count() + 1;
    }

    private function contributionScore(User $user): int
    {
        return $user->places()
                ->where('source', 'user_submitted')
                ->where('is_verified', true)
                ->count()
            + $user->placeCorrections()->where('status', 'applied')->count()
            + $user->reviews()
                ->where(fn (Builder $q) => $q
                    ->whereNull('moderation_status')
                    ->orWhere('moderation_status', 'approved'))
                ->count();
    }

    private function normalizeCategory(?string $category): string
    {
        return in_array($category, self::CATEGORIES, true) ? $category : 'xp';
    }

    private function mapUser(User $user, int $rank, $badgeCounts): array
    {
        $reviews = (int) ($user->reviews_count ?? 0);
        $places = (int) ($user->places_count ?? 0);
        $corrections = (int) ($user->corrections_count ?? 0);

        return [
            'rank' => $rank,
            'user_id' => (string) $user->id,
            'name' => $user->name,
            'avatar_url' => ($a = $user->avatar) ? (str_starts_with($a, 'http') ? $a : asset('storage/' . $a)) : null,
            'total_xp' => (int) ($user->total_xp ?? 0),
            'total_points' => (int) ($user->total_xp ?? 0),
            'current_level' => (int) ($user->current_level ?? 1),
            'level_name' => app(AchievementService::class)->getLevelName($user->current_level ?? 1),
            'approved_reports' => (int) ($user->approved_reports ?? 0),
            'total_reports' => (int) ($user->reports_count ?? 0),
            'total_alerts' => (int) ($user->alerts_count ?? 0),
            'total_reviews' => $reviews,
            'places_contributed' => $places,
            'corrections_applied' => $corrections,
            'contribution_score' => $places + $corrections + $reviews,
            'total_earned' => round((float) ($user->earned_coins ?? 0), 2),
            'badge_count' => (int) ($badgeCounts[$user->id] ?? 0),
            'verification_tick' => ($user->verification_tick && $user->verification_tick !== 'none') ? $user->verification_tick : 'gray',
        ];
    }
}
