<?php

namespace App\Services;

use App\Models\BadWord;
use App\Models\ContentViolation;
use App\Models\GameSetting;
use App\Models\ModerationStrike;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics for the admin Content Safety command center.
 *
 * The safety tables are small (unlike places), so this payload is computed on
 * every request instead of cached: a new censor, warning or suspension must
 * show up on the next refresh. Every dimension is one grouped query and the
 * 30-day trend is two, keeping the page well under a dozen round-trips.
 */
class AdminSafetyService
{
    /** Severity labels, colours and ordering (worst first). */
    public const SEVERITIES = [
        'mild' => ['label' => 'Mild', 'color' => '#94a3b8'],
        'moderate' => ['label' => 'Moderate', 'color' => '#f59e0b'],
        'severe' => ['label' => 'Severe', 'color' => '#f43f5e'],
    ];

    /** Entity labels used by the violation log and heatmap. */
    public const ENTITY_LABELS = [
        'place_review' => 'Place review',
        'review' => 'Place review',
        'report' => 'Report',
        'report_comment' => 'Report comment',
        'alert' => 'Alert',
        'place_correction' => 'Place correction',
        'correction' => 'Place correction',
        'place' => 'Place',
        'reward_offer' => 'Reward offer',
        'offer' => 'Reward offer',
        'ad_campaign' => 'Ad campaign',
        'user_bio' => 'User bio',
        'content' => 'Other content',
    ];

    /** Detection sources written by ContentSafetyService + legacy rows. */
    public const SOURCE_LABELS = [
        'live' => 'Realtime guard',
        'sweep' => 'Sweep agent',
        'system' => 'System',
        'ai' => 'AI agent',
    ];

    private const ENTITY_PALETTE = ['#0f766e', '#f59e0b', '#6366f1', '#ec4899', '#0ea5e9', '#84cc16', '#f43f5e', '#8b5cf6'];

    private const SOURCE_COLORS = [
        'live' => '#0f766e',
        'sweep' => '#6366f1',
        'system' => '#94a3b8',
        'ai' => '#0ea5e9',
    ];

    /** Escalation colours. */
    private const ENFORCEMENT_COLORS = [
        'warning' => '#f59e0b',
        'suspend' => '#f97316',
        'block' => '#e11d48',
    ];

    public function insights(): array
    {
        $now = now();
        $start30 = $now->copy()->subDays(29)->startOfDay();
        $startPrev30 = $now->copy()->subDays(59)->startOfDay();

        $total = ContentViolation::count();
        $today = ContentViolation::whereDate('created_at', $now->toDateString())->count();
        $yesterday = ContentViolation::whereDate('created_at', $now->copy()->subDay()->toDateString())->count();
        $last7 = ContentViolation::where('created_at', '>=', $now->copy()->subDays(6)->startOfDay())->count();
        $last30 = ContentViolation::where('created_at', '>=', $start30)->count();
        $prev30 = ContentViolation::where('created_at', '>=', $startPrev30)
            ->where('created_at', '<', $start30)
            ->count();

        $severityAll = ContentViolation::query()
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $severity30 = ContentViolation::query()
            ->where('created_at', '>=', $start30)
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $entityRows = ContentViolation::query()
            ->select('entity_type', DB::raw('COUNT(*) as total'))
            ->groupBy('entity_type')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $sourceRows = ContentViolation::query()
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $strikeLevels = ModerationStrike::query()
            ->select('level', DB::raw('COUNT(*) as total'))
            ->groupBy('level')
            ->pluck('total', 'level');

        $strikeLevels30 = ModerationStrike::query()
            ->where('created_at', '>=', $start30)
            ->select('level', DB::raw('COUNT(*) as total'))
            ->groupBy('level')
            ->pluck('total', 'level');

        $autoStrikes = ModerationStrike::whereNull('issued_by')->count();
        $manualStrikes = ModerationStrike::whereNotNull('issued_by')->count();

        $uniqueOffenders = (int) ContentViolation::query()->whereNotNull('user_id')->distinct()->count('user_id');

        $repeatOffenders = (int) ContentViolation::query()
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $start30)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $suspendedNow = User::where('status', 'suspended')
            ->where(function ($query) use ($now) {
                $query->whereNull('suspended_until')->orWhere('suspended_until', '>', $now);
            })
            ->count();
        $bannedNow = User::where('status', 'banned')->count();
        $accountsTotal = User::count();

        $wordsTotal = BadWord::count();
        $wordsActive = BadWord::where('active', true)->count();
        $wordSeverity = BadWord::where('active', true)
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $trendViolations = $this->dailyCounts(ContentViolation::query(), $start30, $now);
        $trendStrikes = $this->dailyCounts(ModerationStrike::query(), $start30, $now);

        $labels = [];
        $violationSeries = [];
        $enforcementSeries = [];
        for ($day = $start30->copy(); $day->lte($now); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $violationSeries[] = (int) ($trendViolations[$key] ?? 0);
            $enforcementSeries[] = (int) ($trendStrikes[$key] ?? 0);
        }

        $thresholds = [
            'warn_at' => (int) GameSetting::getValue('safety_warn_at_strikes', 1),
            'suspend_at' => (int) GameSetting::getValue('safety_suspend_at_strikes', 2),
            'ban_at' => (int) GameSetting::getValue(
                'safety_ban_at_strikes',
                GameSetting::getValue('safety_block_at_strikes', 3)
            ),
            'window_days' => (int) GameSetting::getValue('safety_strike_window_days', 30),
        ];

        $severe30 = (int) ($severity30['severe'] ?? 0);
        $enforcementTotal = (int) $strikeLevels->sum();
        $autoPct = $enforcementTotal > 0 ? round($autoStrikes / $enforcementTotal * 100, 1) : 0.0;
        $repeatPct = $uniqueOffenders > 0 ? round($repeatOffenders / $uniqueOffenders * 100, 1) : 0.0;
        $severePct = $total > 0 ? round((int) ($severityAll['severe'] ?? 0) / $total * 100, 1) : 0.0;

        $latest = ContentViolation::orderByDesc('created_at')->first();

        return [
            'generated_at' => $now->format('M j, Y g:i A'),
            'latest_violation' => $latest?->created_at?->diffForHumans(),
            'thresholds' => $thresholds,
            'counts' => [
                'accounts_total' => $accountsTotal,
                'violations_total' => $total,
                'violations_today' => $today,
                'violations_yesterday' => $yesterday,
                'violations_7d' => $last7,
                'violations_30d' => $last30,
                'violations_prev_30d' => $prev30,
                'unique_offenders' => $uniqueOffenders,
                'repeat_offenders' => $repeatOffenders,
                'severe_total' => (int) ($severityAll['severe'] ?? 0),
                'severe_30d' => $severe30,
                'strikes_total' => $enforcementTotal,
                'strikes_30d' => (int) $strikeLevels30->sum(),
                'warnings_total' => (int) ($strikeLevels['warning'] ?? 0),
                'suspensions_total' => (int) ($strikeLevels['suspend'] ?? 0),
                'blocks_total' => (int) ($strikeLevels['block'] ?? 0),
                'auto_strikes' => $autoStrikes,
                'manual_strikes' => $manualStrikes,
                'suspended_now' => $suspendedNow,
                'banned_now' => $bannedNow,
                'words_total' => $wordsTotal,
                'words_active' => $wordsActive,
                'words_severe' => (int) ($wordSeverity['severe'] ?? 0),
                'words_moderate' => (int) ($wordSeverity['moderate'] ?? 0),
                'words_mild' => (int) ($wordSeverity['mild'] ?? 0),
                'entities_tracked' => $entityRows->count(),
            ],
            'kpis' => $this->kpis($last30, $prev30, $severe30, $today, $suspendedNow, $bannedNow, $wordsActive, $wordsTotal, $severePct),
            'trend' => [
                'labels' => $labels,
                'violations' => $violationSeries,
                'enforcement' => $enforcementSeries,
                'violations_total' => array_sum($violationSeries),
                'enforcement_total' => array_sum($enforcementSeries),
            ],
            'severities' => $this->severityRows($severityAll, $total),
            'entities' => $this->entityRows($entityRows, $total),
            'sources' => $this->sourceRows($sourceRows, $total),
            'enforcement' => $this->enforcementRows($strikeLevels),
            'top_offenders' => $this->topOffenders($start30),
            'coverage' => [
                'auto_pct' => $autoPct,
                'repeat_pct' => $repeatPct,
                'severe_pct' => $severePct,
                'auto_total' => $autoStrikes,
                'manual_total' => $manualStrikes,
                'repeat_total' => $repeatOffenders,
                'unique_offenders' => $uniqueOffenders,
            ],
        ];
    }

    /**
     * Four headline cards matching the Places/Alerts command-center pattern.
     */
    private function kpis(
        int $last30,
        int $prev30,
        int $severe30,
        int $today,
        int $suspendedNow,
        int $bannedNow,
        int $wordsActive,
        int $wordsTotal,
        float $severePct,
    ): array {
        $delta = $last30 - $prev30;
        $deltaLabel = $delta === 0
            ? 'No change vs previous 30 days'
            : ($delta > 0 ? '+' : '').number_format($delta).' vs previous 30 days';

        $watch = $suspendedNow + $bannedNow;

        return [
            [
                'label' => 'Safety events',
                'value' => number_format($last30),
                'hint' => $deltaLabel,
                'hint_class' => $delta > 0 ? 'text-rose-600' : ($delta < 0 ? 'text-emerald-600' : 'text-slate-400'),
                'icon' => 'shield-halved',
                'icon_bg' => 'bg-rose-50 text-rose-600',
            ],
            [
                'label' => 'Severe incidents',
                'value' => number_format($severe30),
                'hint' => number_format($severePct, 1).'% of all events · '.number_format($today).' today',
                'hint_class' => $severe30 > 0 ? 'text-rose-600' : 'text-emerald-600',
                'icon' => 'triangle-exclamation',
                'icon_bg' => 'bg-orange-50 text-orange-600',
            ],
            [
                'label' => 'Accounts under watch',
                'value' => number_format($watch),
                'hint' => number_format($suspendedNow).' suspended · '.number_format($bannedNow).' blocked',
                'hint_class' => $watch > 0 ? 'text-amber-600' : 'text-emerald-600',
                'icon' => 'user-slash',
                'icon_bg' => 'bg-amber-50 text-amber-600',
            ],
            [
                'label' => 'Dictionary coverage',
                'value' => number_format($wordsActive),
                'hint' => number_format($wordsActive).' active of '.number_format($wordsTotal).' entries',
                'hint_class' => $wordsActive > 0 ? 'text-sky-600' : 'text-rose-600',
                'icon' => 'spell-check',
                'icon_bg' => 'bg-sky-50 text-sky-600',
            ],
        ];
    }

    /** @return array<string,int> date => count */
    private function dailyCounts($query, Carbon $from, Carbon $to): array
    {
        return $query
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();
    }

    private function severityRows($counts, int $total): array
    {
        $rows = [];
        foreach (self::SEVERITIES as $key => $meta) {
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

    private function entityRows($rows, int $total): array
    {
        return $rows->values()->map(function ($row, $index) use ($total) {
            $value = (int) $row->total;
            $key = (string) $row->entity_type;

            return [
                'key' => $key,
                'label' => self::ENTITY_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => self::ENTITY_PALETTE[$index % count(self::ENTITY_PALETTE)],
            ];
        })->all();
    }

    private function sourceRows($rows, int $total): array
    {
        return $rows->values()->map(function ($row, $index) use ($total) {
            $value = (int) $row->total;
            $key = (string) $row->source;

            return [
                'key' => $key,
                'label' => self::SOURCE_LABELS[$key] ?? ucfirst($key),
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => self::SOURCE_COLORS[$key] ?? self::ENTITY_PALETTE[$index % count(self::ENTITY_PALETTE)],
            ];
        })->all();
    }

    private function enforcementRows($levels): array
    {
        $labels = ['warning' => 'Warnings', 'suspend' => 'Suspensions', 'block' => 'Blocks'];

        $rows = [];
        foreach ($labels as $key => $label) {
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'value' => (int) ($levels[$key] ?? 0),
                'color' => self::ENFORCEMENT_COLORS[$key],
            ];
        }

        return $rows;
    }

    /**
     * The five users with the most violations in the last 30 days, including
     * their active-window strike count so the list doubles as a watchlist.
     */
    private function topOffenders(Carbon $start30): array
    {
        $rows = ContentViolation::query()
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $start30)
            ->select('user_id', DB::raw('COUNT(*) as total'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->orderByDesc('last_at')
            ->limit(5)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');
        $windowStart = now()->subDays((int) GameSetting::getValue('safety_strike_window_days', 30));
        $strikes = ModerationStrike::query()
            ->whereIn('user_id', $rows->pluck('user_id'))
            ->where('created_at', '>=', $windowStart)
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return $rows->map(function ($row) use ($users, $strikes) {
            $user = $users->get($row->user_id);

            return [
                'id' => (int) $row->user_id,
                'name' => $user?->name ?? 'Deleted user',
                'email' => $user?->email,
                'status' => $user?->status ?? 'unknown',
                'violations' => (int) $row->total,
                'strikes' => (int) ($strikes[$row->user_id] ?? 0),
                'last_at' => $row->last_at ? Carbon::parse($row->last_at)->diffForHumans() : null,
            ];
        })->all();
    }
}
