<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportSatisfaction;
use Carbon\Carbon;

/**
 * Read-only analytics for the admin Support command center.
 *
 * Support volume is small, so the payload is computed per request rather than
 * cached: a new conversation, reply, escalation or CSAT review must appear on
 * the next refresh. The expensive work is limited to a handful of grouped
 * queries plus two 30-day daily-count queries.
 */
class AdminSupportService
{
    /** Status labels, colours and ordering. */
    public const STATUS_META = [
        'open' => ['label' => 'Open', 'color' => '#0ea5e9', 'dot' => 'bg-sky-500'],
        'ai_handling' => ['label' => 'AI handling', 'color' => '#6366f1', 'dot' => 'bg-indigo-500'],
        'awaiting_human' => ['label' => 'Awaiting human', 'color' => '#f59e0b', 'dot' => 'bg-amber-500'],
        'in_progress' => ['label' => 'In progress', 'color' => '#0f766e', 'dot' => 'bg-primary-600'],
        'resolved' => ['label' => 'Resolved', 'color' => '#10b981', 'dot' => 'bg-emerald-500'],
        'closed' => ['label' => 'Closed', 'color' => '#94a3b8', 'dot' => 'bg-slate-400'],
    ];

    /** Priority labels, colours and ordering (most urgent first). */
    public const PRIORITY_META = [
        'urgent' => ['label' => 'Urgent', 'color' => '#e11d48'],
        'high' => ['label' => 'High', 'color' => '#f97316'],
        'normal' => ['label' => 'Normal', 'color' => '#0ea5e9'],
        'low' => ['label' => 'Low', 'color' => '#94a3b8'],
    ];

    private const CATEGORY_PALETTE = ['#0f766e', '#f59e0b', '#6366f1', '#ec4899', '#0ea5e9', '#84cc16', '#f43f5e', '#8b5cf6'];

    private const RATING_META = [
        5 => ['label' => '5 stars', 'color' => '#10b981'],
        4 => ['label' => '4 stars', 'color' => '#84cc16'],
        3 => ['label' => '3 stars', 'color' => '#f59e0b'],
        2 => ['label' => '2 stars', 'color' => '#f97316'],
        1 => ['label' => '1 star', 'color' => '#e11d48'],
    ];

    public function __construct(
        private SupportService $supportService
    ) {}

    public function insights(): array
    {
        $stats = $this->supportService->getInboxStats();
        $now = now();
        $start30 = $now->copy()->subDays(29)->startOfDay();

        $trendConversations = $this->dailyCounts(SupportConversation::query(), $start30, $now);
        $trendReplies = $this->dailyCounts(
            SupportMessage::query()
                ->where('sender_type', SupportMessage::SENDER_SUPPORT)
                ->where('is_internal_note', false),
            $start30,
            $now
        );

        $labels = [];
        $conversationSeries = [];
        $replySeries = [];
        for ($day = $start30->copy(); $day->lte($now); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $conversationSeries[] = (int) ($trendConversations[$key] ?? 0);
            $replySeries[] = (int) ($trendReplies[$key] ?? 0);
        }

        $total = (int) $stats['total'];
        $active = (int) $stats['active'];
        $reviewed = (int) $stats['reviewed'];
        $avgRating = $stats['avg_rating'];

        return [
            'generated_at' => $now->format('M j, Y g:i A'),
            'stats' => $stats,
            'kpis' => $this->kpis($stats),
            'trend' => [
                'labels' => $labels,
                'conversations' => $conversationSeries,
                'replies' => $replySeries,
                'conversations_total' => array_sum($conversationSeries),
                'replies_total' => array_sum($replySeries),
            ],
            'statuses' => $this->statusRows($stats, $total),
            'categories' => $this->categoryRows($total),
            'priorities' => $this->priorityRows($total),
            'satisfaction' => $this->satisfactionRows($stats, $reviewed),
            'coverage' => [
                'ai_pct' => $total > 0 ? round((int) $stats['ai_handled'] / $total * 100, 1) : 0.0,
                'assigned_pct' => $active > 0 ? round((int) $stats['assigned_active'] / $active * 100, 1) : 0.0,
                'resolution_pct' => $total > 0 ? round(((int) $stats['resolved'] + (int) $stats['closed']) / $total * 100, 1) : 0.0,
                'pressure' => (int) $stats['urgent_active'] + (int) $stats['high_active'],
                'reviewed' => $reviewed,
                'avg_rating' => $avgRating,
            ],
        ];
    }

    /**
     * Four headline cards matching the Places/Alerts command-center pattern.
     */
    private function kpis(array $stats): array
    {
        $active = (int) $stats['active'];
        $awaiting = (int) $stats['awaiting_human'];
        $unassigned = (int) $stats['unassigned'];
        $reviewed = (int) $stats['reviewed'];
        $avg = $stats['avg_rating'];
        $unassignedPct = $active > 0 ? round($unassigned / $active * 100, 1) : 0.0;

        return [
            [
                'label' => 'Active queue',
                'value' => number_format($active),
                'hint' => number_format((int) $stats['open']).' open · '.number_format((int) $stats['in_progress']).' in progress',
                'hint_class' => $active > 0 ? 'text-primary-700' : 'text-emerald-600',
                'icon' => 'inbox',
                'icon_bg' => 'bg-primary-50 text-primary-700',
            ],
            [
                'label' => 'Awaiting human',
                'value' => number_format($awaiting),
                'hint' => $awaiting > 0 && $stats['oldest_waiting']
                    ? 'Oldest waiting '.$stats['oldest_waiting']
                    : 'No one waiting for a human',
                'hint_class' => $awaiting > 0 ? 'text-amber-600' : 'text-emerald-600',
                'icon' => 'headset',
                'icon_bg' => 'bg-amber-50 text-amber-600',
            ],
            [
                'label' => 'Unassigned',
                'value' => number_format($unassigned),
                'hint' => number_format($unassignedPct, 1).'% of the active queue',
                'hint_class' => $unassigned > 0 ? 'text-rose-600' : 'text-emerald-600',
                'icon' => 'user-clock',
                'icon_bg' => 'bg-rose-50 text-rose-600',
            ],
            [
                'label' => 'Satisfaction',
                'value' => $avg !== null ? number_format((float) $avg, 1).'/5' : '—',
                'hint' => $reviewed > 0
                    ? 'Based on '.number_format($reviewed).' review'.($reviewed === 1 ? '' : 's')
                    : 'No CSAT reviews yet',
                'hint_class' => $avg === null
                    ? 'text-slate-400'
                    : ((float) $avg >= 4 ? 'text-emerald-600' : ((float) $avg >= 3 ? 'text-amber-600' : 'text-rose-600')),
                'icon' => 'star',
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

    private function statusRows(array $stats, int $total): array
    {
        $rows = [];
        foreach (self::STATUS_META as $key => $meta) {
            $value = (int) ($stats[$key] ?? 0);
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

    private function categoryRows(int $total): array
    {
        $rows = SupportConversation::query()
            ->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return $rows->values()->map(function ($row, int $index) use ($total) {
            $value = (int) $row->total;
            $key = (string) $row->category;

            return [
                'key' => $key,
                'label' => SupportConversation::CATEGORIES[$key] ?? ucfirst(str_replace('_', ' ', $key)),
                'value' => $value,
                'pct' => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                'color' => self::CATEGORY_PALETTE[$index % count(self::CATEGORY_PALETTE)],
            ];
        })->all();
    }

    private function priorityRows(int $total): array
    {
        $counts = SupportConversation::query()
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $rows = [];
        foreach (self::PRIORITY_META as $key => $meta) {
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

    private function satisfactionRows(array $stats, int $reviewed): array
    {
        $rows = [];
        foreach (self::RATING_META as $rating => $meta) {
            $value = (int) ($stats['rating_distribution'][$rating] ?? 0);
            $rows[] = [
                'key' => (string) $rating,
                'label' => $meta['label'],
                'value' => $value,
                'pct' => $reviewed > 0 ? round($value / $reviewed * 100, 1) : 0.0,
                'color' => $meta['color'],
            ];
        }

        return $rows;
    }
}
