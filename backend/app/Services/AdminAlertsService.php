<?php

namespace App\Services;

use App\Models\Alert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only analytics for the admin Alerts command center.
 *
 * The alert list itself is filtered and paginated by the controller. This
 * service builds the "at a glance" layer above it: active/expired counts,
 * severity and type splits, a 30-day creation trend, district coverage and
 * delivery readiness (broadcast, map pin, Nepali translation).
 */
class AdminAlertsService
{
    /** Severity order and chart colours. */
    private const SEVERITIES = [
        'critical' => ['label' => 'Critical', 'color' => '#e11d48'],
        'high' => ['label' => 'High', 'color' => '#f97316'],
        'medium' => ['label' => 'Medium', 'color' => '#f59e0b'],
        'low' => ['label' => 'Low', 'color' => '#84cc16'],
        'info' => ['label' => 'Info', 'color' => '#0ea5e9'],
    ];

    /** Type bar colours. */
    private const TYPE_COLORS = ['#0f766e', '#f59e0b', '#6366f1', '#ec4899', '#0ea5e9', '#84cc16', '#f43f5e', '#8b5cf6'];

    /** Human labels for the alert types used by the create form and BIPAD. */
    public const TYPE_LABELS = [
        'earthquake' => 'Earthquake',
        'flood' => 'Flood',
        'landslide' => 'Landslide',
        'weather' => 'Weather',
        'strike' => 'Strike / Bandh',
        'emergency' => 'Emergency',
        'system' => 'System notice',
    ];

    public function build(): array
    {
        $counts = $this->statusCounts();
        $severityCounts = $this->severityCounts();

        $total = (int) $counts['all'];
        $active = (int) $counts['active'];
        $expired = (int) $counts['expired'];

        $urgent = (int) (($severityCounts['critical'] ?? 0) + ($severityCounts['high'] ?? 0));
        $activeUrgent = Alert::query()
            ->active()
            ->whereIn('severity', ['critical', 'high'])
            ->count();

        $today = Alert::query()->whereDate('created_at', today())->count();
        $yesterday = Alert::query()->whereDate('created_at', today()->subDay())->count();
        $last7 = Alert::query()->where('created_at', '>=', now()->subDays(7))->count();

        $broadcast = Alert::query()->where('is_broadcast', true)->count();
        $activeBroadcast = Alert::query()->active()->where('is_broadcast', true)->count();
        $mapped = Alert::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();
        $bilingual = Alert::query()
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('title_ne')->where('title_ne', '<>', '');
                })->orWhere(function ($q) {
                    $q->whereNotNull('description_ne')->where('description_ne', '<>', '');
                });
            })
            ->count();

        $expiringSoon = Alert::query()
            ->active()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours(24)])
            ->count();

        $latestAt = Alert::query()->max('created_at');
        $oldestActiveAt = Alert::query()->active()->min('created_at');

        return [
            'counts' => [
                'all' => $total,
                'active' => $active,
                'expired' => $expired,
                'today' => $today,
                'yesterday' => $yesterday,
                'last_7_days' => $last7,
                'urgent' => $urgent,
                'active_urgent' => $activeUrgent,
                'broadcast' => $broadcast,
                'active_broadcast' => $activeBroadcast,
                'mapped' => $mapped,
                'bilingual' => $bilingual,
                'expiring_soon' => $expiringSoon,
                'severity' => $severityCounts,
            ],
            'kpis' => $this->kpis(
                $active,
                $expired,
                $total,
                $activeUrgent,
                $urgent,
                $today,
                $yesterday,
                $last7,
                $activeBroadcast,
                $mapped,
                $bilingual,
                $expiringSoon,
            ),
            'trend' => $this->trend(),
            'severities' => $this->severitySplit($severityCounts, $total),
            'types' => $this->typeSplit($total),
            'type_options' => $this->typeOptions(),
            'districts' => $this->districtSplit($total),
            'coverage' => [
                'active_pct' => $total > 0 ? round($active / $total * 100, 1) : 0.0,
                'broadcast' => $broadcast,
                'active_broadcast' => $activeBroadcast,
                'mapped' => $mapped,
                'mapped_pct' => $total > 0 ? round($mapped / $total * 100, 1) : 0.0,
                'bilingual' => $bilingual,
                'bilingual_pct' => $total > 0 ? round($bilingual / $total * 100, 1) : 0.0,
                'expiring_soon' => $expiringSoon,
            ],
            'latest_alert' => $latestAt ? Carbon::parse($latestAt)->diffForHumans() : null,
            'oldest_active' => $oldestActiveAt
                ? Carbon::parse($oldestActiveAt)->diffForHumans(short: true)
                : null,
            'generated_at' => now()->format('g:i A'),
        ];
    }

    /**
     * @return array{all: int, active: int, expired: int}
     */
    private function statusCounts(): array
    {
        $total = Alert::query()->count();
        $expired = Alert::query()->expired()->count();

        return [
            'all' => $total,
            'active' => $total - $expired,
            'expired' => $expired,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function severityCounts(): array
    {
        $raw = Alert::query()
            ->selectRaw('severity, COUNT(*) as total')
            ->groupBy('severity')
            ->pluck('total', 'severity')
            ->map(fn ($value) => (int) $value);

        $counts = [];
        foreach (array_keys(self::SEVERITIES) as $severity) {
            $counts[$severity] = (int) ($raw[$severity] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kpis(
        int $active,
        int $expired,
        int $total,
        int $activeUrgent,
        int $urgent,
        int $today,
        int $yesterday,
        int $last7,
        int $activeBroadcast,
        int $mapped,
        int $bilingual,
        int $expiringSoon,
    ): array {
        if ($total === 0) {
            $activeHint = 'No alerts published yet';
            $activeHintClass = 'text-slate-500';
        } elseif ($expiringSoon > 0) {
            $activeHint = number_format($expiringSoon).' expiring in the next 24h';
            $activeHintClass = 'text-amber-600';
        } elseif ($expired > 0) {
            $activeHint = number_format($expired).' expired · '.number_format($total).' all time';
            $activeHintClass = 'text-slate-500';
        } else {
            $activeHint = number_format($total).' all time · none expired';
            $activeHintClass = 'text-emerald-600';
        }

        $urgentShare = $total > 0 ? round($urgent / $total * 100, 1) : 0.0;
        $urgentHint = $total === 0
            ? 'No severity data yet'
            : number_format($activeUrgent).' active · '.number_format($urgentShare, 1).'% of all alerts';
        $urgentHintClass = $activeUrgent > 0
            ? 'text-rose-600'
            : ($urgent > 0 ? 'text-slate-500' : 'text-emerald-600');

        $delta = $today - $yesterday;
        if ($today === 0 && $yesterday === 0) {
            $todayHint = 'No alerts published yet today';
            $todayHintClass = 'text-slate-500';
        } elseif ($delta > 0) {
            $todayHint = '+'.number_format($delta).' vs yesterday';
            $todayHintClass = 'text-emerald-600';
        } elseif ($delta < 0) {
            $todayHint = number_format(abs($delta)).' fewer than yesterday';
            $todayHintClass = 'text-rose-600';
        } else {
            $todayHint = 'Same as yesterday';
            $todayHintClass = 'text-slate-500';
        }

        if ($total === 0) {
            $broadcastHint = 'Broadcast reaches every subscriber';
            $broadcastHintClass = 'text-slate-500';
        } else {
            $broadcastHint = number_format($mapped).' map-pinned · '.number_format($bilingual).' bilingual';
            $broadcastHintClass = $mapped > 0 || $bilingual > 0 ? 'text-primary-700' : 'text-slate-500';
        }

        return [
            [
                'key' => 'active',
                'label' => 'Active alerts',
                'value' => number_format($active),
                'hint' => $activeHint,
                'hint_class' => $activeHintClass,
                'icon' => 'bell',
                'icon_bg' => 'bg-primary-50 text-primary-700',
            ],
            [
                'key' => 'urgent',
                'label' => 'Critical & high',
                'value' => number_format($urgent),
                'hint' => $urgentHint,
                'hint_class' => $urgentHintClass,
                'icon' => 'triangle-exclamation',
                'icon_bg' => 'bg-rose-50 text-rose-600',
            ],
            [
                'key' => 'today',
                'label' => 'Published today',
                'value' => number_format($today),
                'hint' => $todayHint,
                'hint_class' => $todayHintClass,
                'icon' => 'bolt',
                'icon_bg' => 'bg-sky-50 text-sky-600',
            ],
            [
                'key' => 'broadcast',
                'label' => 'Broadcast alerts',
                'value' => number_format($activeBroadcast),
                'hint' => $broadcastHint,
                'hint_class' => $broadcastHintClass,
                'icon' => 'bullhorn',
                'icon_bg' => 'bg-indigo-50 text-indigo-600',
            ],
        ];
    }

    /**
     * Daily alerts created and urgent (critical/high) alerts for the last 30 days.
     *
     * @return array{labels: array<int, string>, created: array<int, int>, urgent: array<int, int>, created_total: int, urgent_total: int}
     */
    private function trend(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $created = Alert::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $urgent = Alert::query()
            ->where('created_at', '>=', $start)
            ->whereIn('severity', ['critical', 'high'])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $labels = [];
        $createdSeries = [];
        $urgentSeries = [];

        for ($i = 0; $i < 30; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();

            $labels[] = $day->format('M j');
            $createdSeries[] = (int) ($created[$key] ?? 0);
            $urgentSeries[] = (int) ($urgent[$key] ?? 0);
        }

        return [
            'labels' => $labels,
            'created' => $createdSeries,
            'urgent' => $urgentSeries,
            'created_total' => (int) array_sum($createdSeries),
            'urgent_total' => (int) array_sum($urgentSeries),
        ];
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array{key: string, label: string, value: int, color: string, pct: float}>
     */
    private function severitySplit(array $counts, int $total): array
    {
        return collect(self::SEVERITIES)
            ->map(function (array $meta, string $key) use ($counts, $total) {
                $value = (int) ($counts[$key] ?? 0);

                return [
                    'key' => $key,
                    'label' => $meta['label'],
                    'value' => $value,
                    'color' => $meta['color'],
                    'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Top alert types by count.
     *
     * @return array<int, array{key: string, label: string, value: int, pct: float, color: string}>
     */
    private function typeSplit(int $total): array
    {
        $rows = Alert::query()
            ->selectRaw('alert_type, COUNT(*) as total')
            ->groupBy('alert_type')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return $rows->values()->map(function ($row, int $index) use ($total) {
            $key = (string) ($row->alert_type ?: 'system');
            $value = (int) $row->total;

            return [
                'key' => $key,
                'label' => self::TYPE_LABELS[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => self::TYPE_COLORS[$index % count(self::TYPE_COLORS)],
            ];
        })->all();
    }

    /**
     * All type options present in the table, for the filter dropdown.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function typeOptions(): array
    {
        return Alert::query()
            ->selectRaw('alert_type, COUNT(*) as total')
            ->groupBy('alert_type')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $key = (string) $row->alert_type;

                return [
                    'key' => $key,
                    'label' => self::TYPE_LABELS[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                    'count' => (int) $row->total,
                ];
            })
            ->all();
    }

    /**
     * Top affected districts / coverage areas.
     *
     * @return array<int, array{label: string, value: int, pct: float, color: string}>
     */
    private function districtSplit(int $total): array
    {
        $rows = DB::table('alerts')
            ->selectRaw("COALESCE(NULLIF(affected_district, ''), 'Platform-wide') as label, COUNT(*) as total")
            ->groupByRaw("COALESCE(NULLIF(affected_district, ''), 'Platform-wide')")
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return $rows->values()->map(function ($row, int $index) use ($total) {
            $value = (int) $row->total;

            return [
                'label' => (string) $row->label,
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => self::TYPE_COLORS[$index % count(self::TYPE_COLORS)],
            ];
        })->all();
    }
}
