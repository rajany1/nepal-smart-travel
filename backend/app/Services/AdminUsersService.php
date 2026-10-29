<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFraudProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics for the admin Users command center.
 *
 * The directory itself is filtered and paginated by the controller. This
 * service builds the "at a glance" layer above it: account/role/status
 * counts, a 30-day registration and verification trend, verification
 * coverage and the fraud-risk split. Everything is computed per request so
 * a new signup, role change or fraud flag appears on the next refresh.
 */
class AdminUsersService
{
    /** Role labels and chart colours, ordered from widest to most privileged. */
    public const ROLE_META = [
        'user' => ['label' => 'Users', 'color' => '#0ea5e9'],
        'business' => ['label' => 'Business partners', 'color' => '#10b981'],
        'moderator' => ['label' => 'Moderators', 'color' => '#f59e0b'],
        'admin' => ['label' => 'Admins', 'color' => '#6366f1'],
        'super_admin' => ['label' => 'Super admins', 'color' => '#e11d48'],
    ];

    /** Account status labels and colours. */
    public const STATUS_META = [
        'active' => ['label' => 'Active', 'color' => '#10b981'],
        'suspended' => ['label' => 'Suspended', 'color' => '#f59e0b'],
        'banned' => ['label' => 'Banned', 'color' => '#e11d48'],
    ];

    /** Verification signals shown in the breakdown card. */
    public const VERIFICATION_META = [
        'email_verified' => ['label' => 'Email verified', 'color' => '#0f766e'],
        'email_unverified' => ['label' => 'Email unverified', 'color' => '#94a3b8'],
        'phone' => ['label' => 'Phone on file', 'color' => '#0ea5e9'],
        'profile' => ['label' => 'Profile complete', 'color' => '#6366f1'],
    ];

    /** Fraud-score bands. "Clean" means no profile or a zero score. */
    public const RISK_META = [
        'clean' => ['label' => 'No fraud score', 'color' => '#10b981'],
        'low' => ['label' => 'Low (1–49)', 'color' => '#84cc16'],
        'medium' => ['label' => 'Medium (50–79)', 'color' => '#f59e0b'],
        'high' => ['label' => 'High (80–100)', 'color' => '#e11d48'],
    ];

    public function build(): array
    {
        $total = User::query()->count();
        $statusCounts = User::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $roleCounts = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->selectRaw('roles.name as role, COUNT(*) as total')
            ->groupBy('roles.name')
            ->pluck('total', 'role');
        $withoutRole = User::query()->whereNull('role_id')->count();

        $now = now();
        $start30 = $now->copy()->subDays(29)->startOfDay();
        $startPrev30 = $now->copy()->subDays(59)->startOfDay();
        $todayStart = $now->copy()->startOfDay();
        $yesterdayStart = $todayStart->copy()->subDay();

        $new30 = User::query()->where('created_at', '>=', $start30)->count();
        $prev30 = User::query()
            ->where('created_at', '>=', $startPrev30)
            ->where('created_at', '<', $start30)
            ->count();
        $today = User::query()->where('created_at', '>=', $todayStart)->count();
        $yesterday = User::query()
            ->where('created_at', '>=', $yesterdayStart)
            ->where('created_at', '<', $todayStart)
            ->count();

        $emailVerified = User::query()->whereNotNull('email_verified_at')->count();
        $phoneOnFile = User::query()->whereNotNull('phone')->count();
        $phoneVerified = User::query()->whereNotNull('phone_verified_at')->count();
        $profileComplete = User::query()->where('profile_completed', true)->count();

        $risk = UserFraudProfile::query()->selectRaw(
            "SUM(CASE WHEN fraud_score BETWEEN 1 AND 49 THEN 1 ELSE 0 END) as low,
             SUM(CASE WHEN fraud_score BETWEEN 50 AND 79 THEN 1 ELSE 0 END) as medium,
             SUM(CASE WHEN fraud_score >= 80 THEN 1 ELSE 0 END) as high,
             SUM(CASE WHEN fraud_score > 0 THEN 1 ELSE 0 END) as scored,
             SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END) as suspicious"
        )->first();

        $lowRisk = (int) ($risk->low ?? 0);
        $mediumRisk = (int) ($risk->medium ?? 0);
        $highRisk = (int) ($risk->high ?? 0);
        $scored = (int) ($risk->scored ?? 0);
        $suspicious = (int) ($risk->suspicious ?? 0);
        $clean = max(0, $total - $scored);

        $withXp = User::query()->where('total_xp', '>', 0)->count();
        $contributors30 = User::query()->where('last_contribution_at', '>=', $start30)->count();

        $admins = (int) ($roleCounts['admin'] ?? 0) + (int) ($roleCounts['super_admin'] ?? 0);
        $moderators = (int) ($roleCounts['moderator'] ?? 0);
        $business = (int) ($roleCounts['business'] ?? 0);
        $regularUsers = (int) ($roleCounts['user'] ?? 0) + $withoutRole;

        $latestSignup = User::query()->max('created_at');

        $counts = [
            'total' => $total,
            'active' => (int) ($statusCounts['active'] ?? 0),
            'suspended' => (int) ($statusCounts['suspended'] ?? 0),
            'banned' => (int) ($statusCounts['banned'] ?? 0),
            'new_30' => $new30,
            'prev_30' => $prev30,
            'today' => $today,
            'yesterday' => $yesterday,
            'email_verified' => $emailVerified,
            'email_unverified' => max(0, $total - $emailVerified),
            'phone' => $phoneOnFile,
            'phone_verified' => $phoneVerified,
            'profile' => $profileComplete,
            'clean' => $clean,
            'low_risk' => $lowRisk,
            'medium_risk' => $mediumRisk,
            'high_risk' => $highRisk,
            'scored' => $scored,
            'suspicious' => $suspicious,
            'with_xp' => $withXp,
            'contributors_30' => $contributors30,
            'role_user' => $regularUsers,
            'role_business' => $business,
            'role_moderator' => $moderators,
            'role_admin' => $admins,
            'role_admin_only' => (int) ($roleCounts['admin'] ?? 0),
            'role_super_admin' => (int) ($roleCounts['super_admin'] ?? 0),
            'staff' => $admins + $moderators,
        ];

        return [
            'generated_at' => $now->format('M j, Y g:i A'),
            'counts' => $counts,
            'kpis' => $this->kpis($counts),
            'trend' => $this->trend($start30, $now),
            'roles' => $this->roleSplit($counts, $total),
            'statuses' => $this->statusSplit($counts, $total),
            'verification' => $this->verificationSplit($counts, $total),
            'risk' => $this->riskSplit($counts, $total),
            'coverage' => [
                'email_pct' => $total > 0 ? round($emailVerified / $total * 100, 1) : 0.0,
                'phone_pct' => $total > 0 ? round($phoneOnFile / $total * 100, 1) : 0.0,
                'profile_pct' => $total > 0 ? round($profileComplete / $total * 100, 1) : 0.0,
                'watch_pct' => $total > 0 ? round($suspicious / $total * 100, 1) : 0.0,
            ],
            'latest_signup' => $latestSignup ? Carbon::parse($latestSignup)->diffForHumans() : null,
        ];
    }

    /**
     * Four headline cards matching the Places/Alerts/Support pattern.
     */
    private function kpis(array $counts): array
    {
        $total = (int) $counts['total'];
        $active = (int) $counts['active'];
        $suspended = (int) $counts['suspended'];
        $banned = (int) $counts['banned'];
        $new30 = (int) $counts['new_30'];
        $prev30 = (int) $counts['prev_30'];
        $today = (int) $counts['today'];
        $yesterday = (int) $counts['yesterday'];
        $suspicious = (int) $counts['suspicious'];
        $highRisk = (int) $counts['high_risk'];
        $scored = (int) $counts['scored'];

        $activePct = $total > 0 ? round($active / $total * 100, 1) : 0.0;
        $activeHint = ($suspended + $banned) > 0
            ? number_format($suspended).' suspended · '.number_format($banned).' banned'
            : 'All accounts active';

        $delta = $new30 - $prev30;
        if ($new30 === 0 && $prev30 === 0) {
            $newHint = 'No new accounts in 30 days';
            $newHintClass = 'text-slate-400';
        } elseif ($delta > 0) {
            $newHint = '+'.number_format($delta).' vs the previous 30 days';
            $newHintClass = 'text-emerald-600';
        } elseif ($delta < 0) {
            $newHint = number_format(abs($delta)).' fewer than the previous 30 days';
            $newHintClass = 'text-rose-600';
        } else {
            $newHint = 'Same as the previous 30 days';
            $newHintClass = 'text-slate-500';
        }

        return [
            [
                'label' => 'Total users',
                'value' => number_format($total),
                'hint' => number_format($new30).' new in the last 30 days',
                'hint_class' => $new30 > 0 ? 'text-primary-700' : 'text-slate-400',
                'icon' => 'users',
                'icon_bg' => 'bg-primary-50 text-primary-700',
            ],
            [
                'label' => 'Active accounts',
                'value' => number_format($active),
                'hint' => number_format($activePct, 1).'% of all accounts · '.$activeHint,
                'hint_class' => ($suspended + $banned) > 0 ? 'text-amber-600' : 'text-emerald-600',
                'icon' => 'circle-check',
                'icon_bg' => 'bg-emerald-50 text-emerald-600',
            ],
            [
                'label' => 'New accounts',
                'value' => number_format($new30),
                'hint' => number_format($today).' today · '.number_format($yesterday).' yesterday · '.$newHint,
                'hint_class' => $newHintClass,
                'icon' => 'user-plus',
                'icon_bg' => 'bg-sky-50 text-sky-600',
            ],
            [
                'label' => 'Under watch',
                'value' => number_format($suspicious),
                'hint' => number_format($highRisk).' high-risk · '.number_format($scored).' with a fraud score',
                'hint_class' => $suspicious > 0 ? 'text-rose-600' : 'text-emerald-600',
                'icon' => 'shield-halved',
                'icon_bg' => 'bg-rose-50 text-rose-600',
            ],
        ];
    }

    /**
     * Daily registrations and email verifications for the last 30 days.
     *
     * @return array{labels: array<int, string>, signups: array<int, int>, verifications: array<int, int>, signups_total: int, verifications_total: int, today: int}
     */
    private function trend(Carbon $start, Carbon $now): array
    {
        $signups = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $verifications = User::query()
            ->whereNotNull('email_verified_at')
            ->where('email_verified_at', '>=', $start)
            ->selectRaw('DATE(email_verified_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(email_verified_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $labels = [];
        $signupSeries = [];
        $verificationSeries = [];

        for ($day = $start->copy(); $day->lte($now); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $signupSeries[] = (int) ($signups[$key] ?? 0);
            $verificationSeries[] = (int) ($verifications[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'signups' => $signupSeries,
            'verifications' => $verificationSeries,
            'signups_total' => (int) array_sum($signupSeries),
            'verifications_total' => (int) array_sum($verificationSeries),
            'today' => (int) ($signups[$now->toDateString()] ?? 0),
        ];
    }

    private function roleSplit(array $counts, int $total): array
    {
        $rows = [];
        foreach (self::ROLE_META as $key => $meta) {
            $value = $key === 'admin'
                ? (int) ($counts['role_admin_only'] ?? 0)
                : (int) ($counts['role_'.$key] ?? 0);
            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => $meta['color'],
            ];
        }

        return $rows;
    }

    private function statusSplit(array $counts, int $total): array
    {
        $rows = [];
        foreach (self::STATUS_META as $key => $meta) {
            $value = (int) ($counts[$key] ?? 0);
            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => $meta['color'],
            ];
        }

        return $rows;
    }

    private function verificationSplit(array $counts, int $total): array
    {
        $rows = [];
        foreach (self::VERIFICATION_META as $key => $meta) {
            $value = (int) ($counts[$key] ?? 0);
            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => $meta['color'],
            ];
        }

        return $rows;
    }

    private function riskSplit(array $counts, int $total): array
    {
        $countKeys = [
            'clean' => 'clean',
            'low' => 'low_risk',
            'medium' => 'medium_risk',
            'high' => 'high_risk',
        ];

        $rows = [];
        foreach (self::RISK_META as $key => $meta) {
            $value = (int) ($counts[$countKeys[$key] ?? $key] ?? 0);
            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => $meta['color'],
            ];
        }

        return $rows;
    }
}
