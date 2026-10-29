<?php

namespace App\Services;

use App\Models\Place;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analytics for the admin Places command center.
 *
 * The places table is large (~70k MyISAM rows) and most of its content is
 * imported in a single OSM run, so every aggregate here is intentional:
 * cheap indexed counts, one grouped query per dimension, and a cached payload
 * keyed by the existing PlacesCache version. Any create/update/delete/import
 * bumps that version, so the next request rebuilds the snapshot immediately.
 */
class AdminPlacesService
{
    /** Cache lifetime in seconds. The version key is the real invalidator. */
    private const CACHE_TTL = 600;

    /** Chart colours. */
    private const PALETTE = ['#0f766e', '#f59e0b', '#6366f1', '#ec4899', '#0ea5e9', '#84cc16', '#f43f5e', '#8b5cf6'];

    /** Source labels and colours. */
    private const SOURCES = [
        'osm' => ['label' => 'OpenStreetMap', 'color' => '#0f766e'],
        'seed' => ['label' => 'Seed data', 'color' => '#f59e0b'],
        'user_submitted' => ['label' => 'User submitted', 'color' => '#6366f1'],
        'admin' => ['label' => 'Admin added', 'color' => '#0ea5e9'],
        'unknown' => ['label' => 'Unknown', 'color' => '#94a3b8'],
    ];

    /** Image discovery states, ordered by operational importance. */
    private const IMAGE_STATUSES = [
        'done' => ['label' => 'Discovered', 'color' => '#10b981'],
        'partial' => ['label' => 'Partial', 'color' => '#84cc16'],
        'pending' => ['label' => 'Pending discovery', 'color' => '#f59e0b'],
        'running' => ['label' => 'Running', 'color' => '#0ea5e9'],
        'none' => ['label' => 'No photo', 'color' => '#94a3b8'],
        'failed' => ['label' => 'Failed', 'color' => '#e11d48'],
    ];

    public function build(): array
    {
        $cacheKey = 'admin:places:insights:v' . PlacesCache::version();

        return Cache::remember($cacheKey, self::CACHE_TTL, fn () => $this->compute());
    }

    private function compute(): array
    {
        $trend = $this->trend();
        $latest = $trend['created'];
        $today = (int) ($latest[count($latest) - 1] ?? 0);
        $yesterday = (int) ($latest[count($latest) - 2] ?? 0);

        $total = (int) DB::table('places')->count();
        $active = (int) DB::table('places')->where('is_active', true)->count();
        $inactive = max(0, $total - $active);
        $pending = (int) DB::table('places')->where('is_verified', false)->where('is_active', false)->count();
        $verified = (int) DB::table('places')->where('is_verified', true)->count();
        $featured = (int) DB::table('places')->where('is_featured', true)->count();

        $districts = (int) DB::table('places')
            ->whereNotNull('district')
            ->where('district', '<>', '')
            ->distinct()
            ->count('district');

        $imagesTotal = (int) DB::table('place_images')->count();
        $withImages = (int) DB::table('place_images')->distinct()->count('place_id');

        $imageRows = DB::table('places')
            ->selectRaw("COALESCE(NULLIF(image_discovery_status, ''), 'none') as status, COUNT(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value);

        $imageStatus = [];
        foreach (self::IMAGE_STATUSES as $key => $meta) {
            $imageStatus[$key] = (int) ($imageRows[$key] ?? 0);
        }

        $sourceRows = DB::table('places')
            ->selectRaw("COALESCE(NULLIF(source, ''), 'unknown') as source, COUNT(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $sources = $sourceRows->map(function ($row, int $index) use ($total) {
            $key = (string) $row->source;
            $meta = self::SOURCES[$key] ?? ['label' => ucfirst(str_replace('_', ' ', $key)), 'color' => self::PALETTE[$index % count(self::PALETTE)]];

            return [
                'key' => $key,
                'label' => $meta['label'],
                'value' => (int) $row->total,
                'pct' => $total > 0 ? round((int) $row->total / $total * 100, 1) : 0.0,
                'color' => $meta['color'],
            ];
        })->values()->all();

        $osmCount = (int) ($sourceRows->firstWhere('source', 'osm')->total ?? 0);

        $categoryRows = DB::table('places')
            ->join('place_categories', 'places.category_id', '=', 'place_categories.id')
            ->selectRaw('place_categories.id as id, place_categories.name as name, COUNT(*) as total')
            ->groupBy('place_categories.id', 'place_categories.name')
            ->orderByDesc('total')
            ->get();

        $categories = $categoryRows->take(8)->values()->map(function ($row, int $index) use ($total) {
            return [
                'key' => (int) $row->id,
                'label' => (string) $row->name,
                'value' => (int) $row->total,
                'pct' => $total > 0 ? round((int) $row->total / $total * 100, 1) : 0.0,
                'color' => self::PALETTE[$index % count(self::PALETTE)],
            ];
        })->all();

        $categoryOptions = $categoryRows->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'count' => (int) $row->total,
        ])->values()->all();

        $districtRows = DB::table('places')
            ->selectRaw("district as label, COUNT(*) as total")
            ->whereNotNull('district')
            ->where('district', '<>', '')
            ->groupBy('district')
            ->orderByDesc('total')
            ->get();

        $districtsSplit = $districtRows->take(6)->values()->map(function ($row, int $index) use ($total) {
            return [
                'label' => (string) $row->label,
                'value' => (int) $row->total,
                'pct' => $total > 0 ? round((int) $row->total / $total * 100, 1) : 0.0,
                'color' => self::PALETTE[$index % count(self::PALETTE)],
            ];
        })->all();

        $districtOptions = $districtRows->map(fn ($row) => [
            'name' => (string) $row->label,
            'count' => (int) $row->total,
        ])->values()->all();

        $imageSplit = collect(self::IMAGE_STATUSES)->map(function (array $meta, string $key) use ($imageStatus, $total) {
            return [
                'key' => $key,
                'label' => $meta['label'],
                'value' => (int) ($imageStatus[$key] ?? 0),
                'pct' => $total > 0 ? round((int) ($imageStatus[$key] ?? 0) / $total * 100, 1) : 0.0,
                'color' => $meta['color'],
            ];
        })->values()->all();

        $oldestPendingAt = $pending > 0
            ? DB::table('places')
                ->where('is_verified', false)
                ->where('is_active', false)
                ->min('created_at')
            : null;

        $latestAt = $trend['latest'];

        return [
            'counts' => [
                'all' => $total,
                'active' => $active,
                'inactive' => $inactive,
                'pending' => $pending,
                'verified' => $verified,
                'featured' => $featured,
                'today' => $today,
                'yesterday' => $yesterday,
                'last_7_days' => (int) array_sum(array_slice($latest, -7)),
                'last_30_days' => (int) $trend['created_total'],
                'districts' => $districts,
                'with_images' => $withImages,
                'images_total' => $imagesTotal,
                'sources' => $sources,
                'image_status' => $imageStatus,
            ],
            'kpis' => $this->kpis(
                $total,
                $active,
                $inactive,
                $pending,
                $verified,
                $withImages,
                $imagesTotal,
                $districts,
                $oldestPendingAt,
            ),
            'trend' => $trend,
            'categories' => $categories,
            'category_options' => $categoryOptions,
            'districts' => $districtsSplit,
            'district_options' => $districtOptions,
            'sources' => $sources,
            'images' => $imageSplit,
            'coverage' => [
                'active_pct' => $total > 0 ? round($active / $total * 100, 1) : 0.0,
                'verified_pct' => $total > 0 ? round($verified / $total * 100, 2) : 0.0,
                'photo_pct' => $total > 0 ? round($withImages / $total * 100, 2) : 0.0,
                'osm_pct' => $total > 0 ? round($osmCount / $total * 100, 1) : 0.0,
                'mapped' => $total,
                'photo_pending' => (int) ($imageStatus['pending'] ?? 0),
                'photo_failed' => (int) ($imageStatus['failed'] ?? 0),
            ],
            'oldest_pending' => $oldestPendingAt
                ? Carbon::parse($oldestPendingAt)->diffForHumans(short: true)
                : null,
            'latest_place' => $latestAt
                ? Carbon::parse($latestAt)->diffForHumans()
                : null,
            'generated_at' => now()->format('g:i A'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kpis(
        int $total,
        int $active,
        int $inactive,
        int $pending,
        int $verified,
        int $withImages,
        int $imagesTotal,
        int $districts,
        ?string $oldestPendingAt,
    ): array {
        $pendingHint = $pending === 0
            ? 'Review queue is clear'
            : number_format($pending).' waiting'.($oldestPendingAt ? ' · oldest '.Carbon::parse($oldestPendingAt)->diffForHumans(short: true) : '');
        $pendingHintClass = $pending > 0 ? 'text-amber-600' : 'text-emerald-600';

        $verifiedPct = $total > 0 ? round($verified / $total * 100, 2) : 0.0;
        $verifiedHint = $verified === 0
            ? 'No places verified yet'
            : number_format($verifiedPct, 1).'% of the directory';
        $verifiedHintClass = $verified === 0 ? 'text-slate-500' : 'text-emerald-600';

        $photoPct = $total > 0 ? round($withImages / $total * 100, 2) : 0.0;
        $photoHint = number_format($imagesTotal).' photos · '.number_format($photoPct, 2).'% of places';
        $photoHintClass = $photoPct >= 10 ? 'text-emerald-600' : 'text-amber-600';

        return [
            [
                'key' => 'total',
                'label' => 'Directory size',
                'value' => number_format($total),
                'hint' => number_format($active).' active · '.number_format($inactive).' inactive',
                'hint_class' => 'text-slate-500',
                'icon' => 'map-location-dot',
                'icon_bg' => 'bg-primary-50 text-primary-700',
                'status' => 'all',
            ],
            [
                'key' => 'pending',
                'label' => 'Pending review',
                'value' => number_format($pending),
                'hint' => $pendingHint,
                'hint_class' => $pendingHintClass,
                'icon' => 'clock',
                'icon_bg' => 'bg-amber-50 text-amber-600',
                'status' => 'pending',
            ],
            [
                'key' => 'verified',
                'label' => 'Verified places',
                'value' => number_format($verified),
                'hint' => $verifiedHint,
                'hint_class' => $verifiedHintClass,
                'icon' => 'circle-check',
                'icon_bg' => 'bg-emerald-50 text-emerald-600',
                'status' => 'verified',
            ],
            [
                'key' => 'media',
                'label' => 'Photo coverage',
                'value' => number_format($withImages),
                'hint' => $photoHint,
                'hint_class' => $photoHintClass,
                'icon' => 'images',
                'icon_bg' => 'bg-indigo-50 text-indigo-600',
                'status' => null,
            ],
        ];
    }

    /**
     * Daily places added and OSM imports over the last 30 days.
     *
     * @return array{labels: array<int, string>, created: array<int, int>, osm: array<int, int>, created_total: int, osm_total: int, latest: ?string}
     */
    private function trend(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $created = DB::table('places')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $osm = DB::table('places')
            ->where('created_at', '>=', $start)
            ->where('source', 'osm')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day')
            ->map(fn ($value) => (int) $value);

        $labels = [];
        $createdSeries = [];
        $osmSeries = [];

        for ($i = 0; $i < 30; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();

            $labels[] = $day->format('M j');
            $createdSeries[] = (int) ($created[$key] ?? 0);
            $osmSeries[] = (int) ($osm[$key] ?? 0);
        }

        $latestAt = DB::table('places')->max('created_at');

        return [
            'labels' => $labels,
            'created' => $createdSeries,
            'osm' => $osmSeries,
            'created_total' => (int) array_sum($createdSeries),
            'osm_total' => (int) array_sum($osmSeries),
            'latest' => $latestAt ?: null,
        ];
    }
}
