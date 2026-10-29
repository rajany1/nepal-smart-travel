<?php

namespace App\Services;

use App\Models\ModerationQueue;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics for the admin Reports screen.
 *
 * The Reports list itself is paginated by the controller. This service only
 * builds the small "at a glance" layer above it: status counts, KPI cards,
 * a 30-day submission/approval trend, priority and category splits, moderation
 * queue counts, and GPS coverage.
 */
class AdminReportsService
{
    /** Statuses shown in the report tabs. */
    private const STATUSES = ['pending', 'approved', 'rejected'];

    /** Priority order and chart colours. */
    private const PRIORITIES = [
        'critical' => ['label' => 'Critical', 'color' => '#e11d48'],
        'high' => ['label' => 'High', 'color' => '#f97316'],
        'medium' => ['label' => 'Medium', 'color' => '#f59e0b'],
        'low' => ['label' => 'Low', 'color' => '#94a3b8'],
    ];

    /** Category bar colours. */
    private const CATEGORY_COLORS = ['#0f766e', '#f59e0b', '#6366f1', '#ec4899', '#0ea5e9', '#84cc16'];

    public function build(): array
    {
        $counts = $this->statusCounts();
        $total = (int) array_sum($counts);
        $pending = (int) ($counts['pending'] ?? 0);
        $approved = (int) ($counts['approved'] ?? 0);
        $rejected = (int) ($counts['rejected'] ?? 0);

        $today = Report::query()->whereDate('created_at', today())->count();
        $yesterday = Report::query()->whereDate('created_at', today()->subDay())->count();
        $overdue = Report::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(48))
            ->count();

        $oldestPendingAt = Report::query()->where('status', 'pending')->min('created_at');
        $latestReportAt = Report::query()->max('created_at');

        $decisionCount = Report::query()->whereNotNull('verified_at')->count();
        $avgReviewSeconds = $this->averageReviewSeconds();

        $decided = $approved + $rejected;
        $approvalRate = $decided > 0 ? round($approved / $decided * 100, 1) : null;

        return [
            'counts' => [
                'all' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'today' => $today,
                'yesterday' => $yesterday,
            ],
            'kpis' => $this->kpis(
                $pending,
                $overdue,
                $oldestPendingAt,
                $today,
                $yesterday,
                $approved,
                $rejected,
                $approvalRate,
                $avgReviewSeconds,
                $decisionCount,
            ),
            'trend' => $this->trend(),
            'priority' => $this->prioritySplit($total),
            'categories' => $this->categorySplit($total),
            'queues' => $this->queueCounts(),
            'gps' => $this->gpsSplit($total),
            'oldest_pending' => $oldestPendingAt
                ? Carbon::parse($oldestPendingAt)->diffForHumans(short: true)
                : null,
            'latest_report' => $latestReportAt
                ? Carbon::parse($latestReportAt)->diffForHumans()
                : null,
            'generated_at' => now()->format('g:i A'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        $raw = Report::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value);

        $counts = [];
        foreach (self::STATUSES as $status) {
            $counts[$status] = (int) ($raw[$status] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kpis(
        int $pending,
        int $overdue,
        ?string $oldestPendingAt,
        int $today,
        int $yesterday,
        int $approved,
        int $rejected,
        ?float $approvalRate,
        ?float $avgReviewSeconds,
        int $decisionCount,
    ): array {
        $todayDelta = $today - $yesterday;
        if ($today === 0 && $yesterday === 0) {
            $todayHint = 'No submissions yet today';
            $todayHintClass = 'text-slate-500';
        } elseif ($todayDelta > 0) {
            $todayHint = '+'.number_format($todayDelta).' vs yesterday';
            $todayHintClass = 'text-emerald-600';
        } elseif ($todayDelta < 0) {
            $todayHint = number_format(abs($todayDelta)).' fewer than yesterday';
            $todayHintClass = 'text-rose-600';
        } else {
            $todayHint = 'Same as yesterday';
            $todayHintClass = 'text-slate-500';
        }

        if ($pending === 0) {
            $pendingHint = 'Queue is clear — nothing to review';
            $pendingHintClass = 'text-emerald-600';
        } elseif ($overdue > 0) {
            $pendingHint = number_format($overdue).' waiting over 48h';
            $pendingHintClass = 'text-rose-600';
        } else {
            $pendingHint = $oldestPendingAt
                ? 'Oldest: '.Carbon::parse($oldestPendingAt)->diffForHumans(short: true)
                : 'Within the 48h review window';
            $pendingHintClass = 'text-slate-500';
        }

        $approvalHint = $approvalRate === null
            ? 'No decisions yet'
            : number_format($approved).' approved · '.number_format($rejected).' rejected';
        $approvalHintClass = $approvalRate === null
            ? 'text-slate-500'
            : ($approvalRate >= 80 ? 'text-emerald-600' : ($approvalRate >= 60 ? 'text-amber-600' : 'text-rose-600'));

        $avgReviewValue = $avgReviewSeconds !== null
            ? $this->formatDuration((int) round($avgReviewSeconds))
            : '—';
        $avgReviewHint = $avgReviewSeconds === null
            ? 'No reviewed reports to measure yet'
            : 'Measured across '.number_format($decisionCount).' reviewed '.($decisionCount === 1 ? 'report' : 'reports');
        $avgReviewHintClass = $avgReviewSeconds === null
            ? 'text-slate-500'
            : ($avgReviewSeconds <= 48 * 3600 ? 'text-emerald-600' : 'text-rose-600');

        return [
            [
                'key' => 'pending',
                'label' => 'Pending review',
                'value' => number_format($pending),
                'hint' => $pendingHint,
                'hint_class' => $pendingHintClass,
                'icon' => 'hourglass-half',
                'icon_bg' => 'bg-amber-50 text-amber-600',
            ],
            [
                'key' => 'today',
                'label' => 'Submitted today',
                'value' => number_format($today),
                'hint' => $todayHint,
                'hint_class' => $todayHintClass,
                'icon' => 'bolt',
                'icon_bg' => 'bg-sky-50 text-sky-600',
            ],
            [
                'key' => 'approval',
                'label' => 'Approval rate',
                'value' => $approvalRate === null ? '—' : number_format($approvalRate, 1).'%',
                'hint' => $approvalHint,
                'hint_class' => $approvalHintClass,
                'icon' => 'check-double',
                'icon_bg' => 'bg-emerald-50 text-emerald-600',
            ],
            [
                'key' => 'review_time',
                'label' => 'Avg review time',
                'value' => $avgReviewValue,
                'hint' => $avgReviewHint,
                'hint_class' => $avgReviewHintClass,
                'icon' => 'stopwatch',
                'icon_bg' => 'bg-indigo-50 text-indigo-600',
            ],
        ];
    }

    /**
     * Daily submissions and approvals for the last 30 days.
     *
     * @return array{labels: array<int, string>, submitted: array<int, int>, approved: array<int, int>, submitted_total: int, approved_total: int}
     */
    private function trend(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $submitted = Report::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $approved = Report::query()
            ->whereNotNull('verified_at')
            ->where('verified_at', '>=', $start)
            ->selectRaw('DATE(verified_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(verified_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $labels = [];
        $submittedSeries = [];
        $approvedSeries = [];

        for ($i = 0; $i < 30; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();

            $labels[] = $day->format('M j');
            $submittedSeries[] = (int) ($submitted[$key] ?? 0);
            $approvedSeries[] = (int) ($approved[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'submitted' => $submittedSeries,
            'approved' => $approvedSeries,
            'submitted_total' => (int) array_sum($submittedSeries),
            'approved_total' => (int) array_sum($approvedSeries),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, value: int, color: string, pct: float}>
     */
    private function prioritySplit(int $total): array
    {
        $raw = Report::query()
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->map(fn ($value) => (int) $value);

        $rows = [];
        $known = 0;

        foreach (self::PRIORITIES as $key => $meta) {
            $value = (int) ($raw[$key] ?? 0);
            $known += $value;

            $rows[] = [
                'key' => $key,
                'label' => $meta['label'],
                'value' => $value,
                'color' => $meta['color'],
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
            ];
        }

        if ($known < $total) {
            $value = $total - $known;
            $rows[] = [
                'key' => 'other',
                'label' => 'Unspecified',
                'value' => $value,
                'color' => '#cbd5e1',
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * Top categories by report count.
     *
     * @return array<int, array{label: string, value: int, pct: float, color: string}>
     */
    private function categorySplit(int $total): array
    {
        $rows = DB::table('reports')
            ->leftJoin('report_categories', 'reports.category_id', '=', 'report_categories.id')
            ->selectRaw("COALESCE(report_categories.name, 'Uncategorised') as label, COUNT(*) as total")
            ->groupBy('report_categories.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return $rows->values()->map(function ($row, int $index) use ($total) {
            $value = (int) $row->total;

            return [
                'label' => (string) $row->label,
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => self::CATEGORY_COLORS[$index % count(self::CATEGORY_COLORS)],
            ];
        })->all();
    }

    /**
     * @return array{pending: int, approved: int, rejected: int}
     */
    private function queueCounts(): array
    {
        return [
            'pending' => ModerationQueue::pending()->byType('report')->count(),
            'approved' => ModerationQueue::where('status', 'approved')->byType('report')->count(),
            'rejected' => ModerationQueue::where('status', 'rejected')->byType('report')->count(),
        ];
    }

    /**
     * @return array{verified: int, mismatched: int, no_gps_data: int, none: int, verified_pct: float}
     */
    private function gpsSplit(int $total): array
    {
        $raw = Report::query()
            ->selectRaw('gps_verification_status as status, COUNT(*) as total')
            ->groupBy('gps_verification_status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value);

        $verified = (int) ($raw['verified'] ?? 0);

        return [
            'verified' => $verified,
            'mismatched' => (int) ($raw['mismatched'] ?? 0),
            'no_gps_data' => (int) ($raw['no_gps_data'] ?? 0),
            'none' => (int) ($raw['none'] ?? 0),
            'verified_pct' => $total > 0 ? round($verified / $total * 100, 1) : 0.0,
        ];
    }

    private function averageReviewSeconds(): ?float
    {
        $rows = Report::query()
            ->whereNotNull('verified_at')
            ->latest('verified_at')
            ->limit(500)
            ->get(['created_at', 'verified_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $seconds = $rows
            ->filter(fn ($row) => $row->created_at && $row->verified_at)
            ->map(fn ($row) => max(0, $row->created_at->diffInSeconds($row->verified_at)));

        return $seconds->isEmpty() ? null : (float) $seconds->avg();
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return '<1m';
        }

        if ($seconds < 3600) {
            return (int) round($seconds / 60).'m';
        }

        if ($seconds < 86400) {
            $hours = intdiv($seconds, 3600);
            $minutes = (int) round(($seconds % 3600) / 60);

            return $hours.'h'.($minutes > 0 ? ' '.$minutes.'m' : '');
        }

        $days = intdiv($seconds, 86400);
        $hours = (int) round(($seconds % 86400) / 3600);

        return $days.'d'.($hours > 0 ? ' '.$hours.'h' : '');
    }
}
