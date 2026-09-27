<?php

namespace App\Services\TravelContext;

use App\Models\Alert;
use App\Models\GameSetting;
use App\Models\Place;
use App\Models\Report;
use Illuminate\Support\Collection;

/**
 * Route relevance engine — ranks existing Oripori intelligence against a
 * TravelContext corridor.
 *
 * This is a READ-ONLY consumer of Reports / Alerts / Places. It never
 * mutates those models and never touches ads, coins, wallet, or SOS.
 *
 * Hard report eligibility (applied in SQL, never trusting the client):
 *   status = approved AND is_active = 1 AND not past expires_at
 *   AND not stale by existing time-state semantics (<= 7 days)
 *
 * Ranking signals (configurable via GameSetting, defaults baked in):
 *   corridor proximity → direction (ahead/passed) → freshness → severity → content type
 */
class RouteRelevanceService
{
    /** Report categories that are inherently journey-relevant. */
    private const ROUTE_CONTENT_CATEGORY_IDS = [
        2, // Road & Traffic
        3, // Safety & Hazards
        4, // Weather & Conditions
        5, // Transportation
        7, // Services & Utilities
        8, // Events & Notices
    ];

    /** Place categories useful along a trip (name substrings, lowercase). */
    private const ROUTE_PLACE_KEYWORDS = [
        'fuel', 'petrol', 'gas', 'hospital', 'clinic', 'pharmacy', 'police',
        'restaurant', 'food', 'cafe', 'hotel', 'lodge', 'guesthouse', 'rest',
        'bank', 'atm', 'parking', 'bus', 'transport', 'repair', 'mechanic',
        'hospital', 'health', 'camp', 'tea', 'noodle',
    ];

    public function corridorRadiusKm(): float
    {
        return (float) GameSetting::getValue('route_corridor_radius_km', 3);
    }

    /**
     * @return array<string, float>
     */
    public function weights(): array
    {
        $defaults = [
            'proximity' => 0.30,
            'direction' => 0.20,
            'freshness' => 0.20,
            'severity' => 0.20,
            'content' => 0.10,
        ];
        $configured = GameSetting::getValue('route_weights', []);
        if (!is_array($configured)) {
            return $defaults;
        }
        $merged = array_merge($defaults, array_intersect_key($configured, $defaults));
        $sum = array_sum($merged);
        if ($sum <= 0) {
            return $defaults;
        }
        // Normalize so weights always sum to 1.
        return array_map(fn ($v) => $v / $sum, $merged);
    }

    public function candidateLimit(): int
    {
        return (int) GameSetting::getValue('route_candidate_limit', 250);
    }

    public function maxResults(): int
    {
        return (int) GameSetting::getValue('route_max_results', 40);
    }

    /**
     * Hard eligibility for public route intelligence — reports only.
     * Applied server-side; client status filters are ignored by design.
     */
    public function eligibleReportsQuery()
    {
        return Report::query()
            ->where('status', 'approved')
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            // Existing freshness semantics: Report::getTimeStateAttribute → 'expired' after 7 days.
            ->where('created_at', '>', now()->subDays(7));
    }

    /**
     * Rank eligible reports along the corridor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rankReports(TravelContext $context, ?float $userLat = null, ?float $userLng = null): array
    {
        if (!$context->hasCorridor()) {
            return [];
        }

        $radiusKm = $this->corridorRadiusKm();
        $bbox = CorridorGeometry::bbox($context->geometry, $radiusKm);
        if ($bbox === null) {
            return [];
        }

        $candidates = $this->eligibleReportsQuery()
            ->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
            ->whereBetween('longitude', [$bbox['min_lng'], $bbox['max_lng']])
            ->with(['category', 'media', 'user'])
            ->latest()
            ->limit($this->candidateLimit())
            ->get();

        $journeyProgress = $context->journey['progress'] ?? null;
        // Pre-trip (no GPS near route) → treat everything as upcoming from origin.
        if ($journeyProgress === null) {
            $journeyProgress = 0.0;
        }

        $weights = $this->weights();
        $ranked = [];

        foreach ($candidates as $report) {
            $measure = CorridorGeometry::measure(
                $context->geometry,
                (float) $report->latitude,
                (float) $report->longitude
            );
            if ($measure === null) {
                continue;
            }
            $distanceKm = $measure['distance_m'] / 1000;
            if ($distanceKm > $radiusKm) {
                continue; // outside corridor — geographically near but not on-route
            }

            $itemProgress = $measure['progress'];
            $severity = $this->severityScore($report->priority);
            $isSevere = in_array($report->priority, ['critical', 'high'], true);

            $proximity = max(0.0, 1.0 - ($distanceKm / max($radiusKm, 0.1)));
            $direction = $this->directionScore($itemProgress, $journeyProgress, $isSevere);
            $freshness = $this->freshnessScore($report->created_at);
            $content = $this->reportContentScore($report);

            $score =
                $weights['proximity'] * $proximity
                + $weights['direction'] * $direction
                + $weights['freshness'] * $freshness
                + $weights['severity'] * $severity
                + $weights['content'] * $content;

            $ranked[] = [
                'item' => $report,
                'type' => 'report',
                'relevance_score' => round($score, 4),
                'corridor_distance_km' => round($distanceKm, 2),
                'route_progress' => round($itemProgress, 4),
                'direction' => $this->directionLabel($itemProgress, $journeyProgress, $isSevere),
            ];
        }

        return $this->formatAndSort($ranked, 'report');
    }

    /**
     * Rank non-expired alerts that are broadcast or near the corridor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rankAlerts(TravelContext $context): array
    {
        if (!$context->hasCorridor()) {
            return [];
        }

        $radiusKm = $this->corridorRadiusKm();
        $bbox = CorridorGeometry::bbox($context->geometry, $radiusKm);
        if ($bbox === null) {
            return [];
        }

        $candidates = Alert::where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($bbox) {
                $q->where('is_broadcast', true)
                    ->orWhere(function ($geo) use ($bbox) {
                        $geo->whereNotNull('latitude')
                            ->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
                            ->whereBetween('longitude', [$bbox['min_lng'], $bbox['max_lng']]);
                    });
            })
            ->latest()
            ->limit($this->candidateLimit())
            ->get();

        $weights = $this->weights();
        $ranked = [];

        foreach ($candidates as $alert) {
            $hasGeo = $alert->latitude !== null && $alert->longitude !== null;
            $distanceKm = null;
            $measure = null;
            if ($hasGeo) {
                $measure = CorridorGeometry::measure(
                    $context->geometry,
                    (float) $alert->latitude,
                    (float) $alert->longitude
                );
                $distanceKm = $measure !== null ? $measure['distance_m'] / 1000 : null;
                if ($distanceKm !== null && $distanceKm > $radiusKm && !$alert->is_broadcast) {
                    continue;
                }
            }

            $severity = $this->severityScore($alert->severity);
            $isSevere = in_array($alert->severity, ['critical', 'high'], true);
            $proximity = $distanceKm === null
                ? ($alert->is_broadcast ? 0.5 : 0.0)
                : max(0.0, 1.0 - ($distanceKm / max($radiusKm, 0.1)));
            $direction = $measure !== null && isset($context->journey['progress'])
                ? $this->directionScore($measure['progress'], (float) $context->journey['progress'], $isSevere)
                : 1.0;
            $freshness = $this->freshnessScore($alert->created_at);

            $score =
                $weights['proximity'] * $proximity
                + $weights['direction'] * $direction
                + $weights['freshness'] * $freshness
                + $weights['severity'] * $severity
                + $weights['content'] * 1.0; // alerts are inherently journey-relevant

            $ranked[] = [
                'item' => $alert,
                'type' => 'alert',
                'relevance_score' => round($score, 4),
                'corridor_distance_km' => $distanceKm !== null ? round($distanceKm, 2) : null,
                'route_progress' => $measure !== null ? round($measure['progress'], 4) : null,
                'direction' => $distanceKm === null ? 'broadcast' : $this->directionLabel(
                    $measure['progress'],
                    (float) ($context->journey['progress'] ?? 0.0),
                    $isSevere
                ),
            ];
        }

        return $this->formatAndSort($ranked, 'alert');
    }

    /**
     * Rank contextually useful places along the corridor (not every business).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rankPlaces(TravelContext $context): array
    {
        if (!$context->hasCorridor()) {
            return [];
        }

        $radiusKm = $this->corridorRadiusKm();
        $bbox = CorridorGeometry::bbox($context->geometry, $radiusKm);
        if ($bbox === null) {
            return [];
        }

        $candidates = Place::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$bbox['min_lat'], $bbox['max_lat']])
            ->whereBetween('longitude', [$bbox['min_lng'], $bbox['max_lng']])
            ->with('category')
            ->limit($this->candidateLimit())
            ->get();

        $weights = $this->weights();
        $ranked = [];

        foreach ($candidates as $place) {
            $categoryName = mb_strtolower((string) ($place->category?->name ?? ''));
            $name = mb_strtolower((string) $place->name);
            if (!$this->isRouteUsefulPlace($categoryName, $name)) {
                continue;
            }

            $measure = CorridorGeometry::measure(
                $context->geometry,
                (float) $place->latitude,
                (float) $place->longitude
            );
            if ($measure === null) {
                continue;
            }
            $distanceKm = $measure['distance_m'] / 1000;
            if ($distanceKm > $radiusKm) {
                continue;
            }

            $proximity = max(0.0, 1.0 - ($distanceKm / max($radiusKm, 0.1)));
            $rating = min(1.0, ((float) ($place->average_rating ?? 0)) / 5.0);
            $content = 0.7 + (0.3 * $rating);

            $score =
                $weights['proximity'] * $proximity
                + (1 - $weights['proximity']) * $content;

            $ranked[] = [
                'item' => $place,
                'type' => 'place',
                'relevance_score' => round($score, 4),
                'corridor_distance_km' => round($distanceKm, 2),
                'route_progress' => round($measure['progress'], 4),
                'direction' => $this->directionLabel(
                    $measure['progress'],
                    (float) ($context->journey['progress'] ?? 0.0),
                    false
                ),
            ];
        }

        return $this->formatAndSort($ranked, 'place');
    }

    /**
     * Full intelligence bundle for the corridor.
     *
     * @return array{summary: array<string, int|float|null>, reports: array, alerts: array, places: array}
     */
    public function intelligence(TravelContext $context, ?float $userLat = null, ?float $userLng = null): array
    {
        $reports = $this->rankReports($context, $userLat, $userLng);
        $alerts = $this->rankAlerts($context);
        $places = $this->rankPlaces($context);

        $max = $this->maxResults();
        $reports = array_slice($reports, 0, $max);
        $alerts = array_slice($alerts, 0, min(20, $max));
        $places = array_slice($places, 0, min(20, $max));

        return [
            'summary' => [
                'reports_count' => count($reports),
                'alerts_count' => count($alerts),
                'places_count' => count($places),
                'total' => count($reports) + count($alerts) + count($places),
                'corridor_radius_km' => $this->corridorRadiusKm(),
                'journey_progress' => $context->journey['progress'] ?? null,
            ],
            'reports' => $reports,
            'alerts' => $alerts,
            'places' => $places,
        ];
    }

    // ── Scoring helpers ──────────────────────────────────────────────

    private function directionScore(float $itemProgress, float $journeyProgress, bool $isSevere): float
    {
        // Behind the user: severe stays somewhat relevant; routine items demoted hard.
        if ($itemProgress < $journeyProgress - 0.02) {
            return $isSevere ? 0.6 : 0.1;
        }
        // Upcoming: sooner-along-route gets a mild boost (you'll hit it first).
        return 1.0 - (0.25 * min($itemProgress, 1.0));
    }

    private function directionLabel(float $itemProgress, float $journeyProgress, bool $isSevere): string
    {
        if ($itemProgress < $journeyProgress - 0.02) {
            return $isSevere ? 'behind_severe' : 'passed';
        }
        return 'ahead';
    }

    private function severityScore(?string $priority): float
    {
        return match ($priority) {
            'critical' => 1.0,
            'high' => 0.75,
            'medium' => 0.4,
            'low' => 0.2,
            default => 0.3,
        };
    }

    private function freshnessScore($createdAt): float
    {
        if (!$createdAt) {
            return 0.0;
        }

        // Carbon 3 may return signed diffs; always use absolute age.
        $ageMinutes = (int) abs($createdAt->diffInMinutes(now()));

        if ($ageMinutes < 60) {
            return 1.0;   // live
        }
        if ($createdAt->isToday()) {
            return 0.8;   // today
        }
        if ((int) abs($createdAt->diffInDays(now())) <= 7) {
            return 0.5;   // recent
        }
        return 0.0;       // expired (reports already filtered at SQL)
    }

    private function reportContentScore(Report $report): float
    {
        $categoryId = (int) $report->category_id;
        if (in_array($categoryId, self::ROUTE_CONTENT_CATEGORY_IDS, true)) {
            return 1.0;
        }
        // Community confirmations boost trust→relevance slightly.
        $confidence = min(1.0, ((int) $report->confirmed_by_count * 15 + (int) ($report->authenticity_score ?? 0)) / 100);
        return 0.35 + (0.25 * $confidence);
    }

    private function isRouteUsefulPlace(string $categoryName, string $name): bool
    {
        $haystack = $categoryName . ' ' . $name;
        foreach (self::ROUTE_PLACE_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int, array{item: mixed, type: string, relevance_score: float, corridor_distance_km: float|null, route_progress: float|null, direction: string}> $ranked
     * @return array<int, array<string, mixed>>
     */
    private function formatAndSort(array $ranked, string $type): array
    {
        usort($ranked, function ($a, $b) {
            // Severe upcoming first via score; tie-break by corridor distance.
            if ($a['relevance_score'] !== $b['relevance_score']) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            }
            return ($a['corridor_distance_km'] ?? PHP_FLOAT_MAX) <=> ($b['corridor_distance_km'] ?? PHP_FLOAT_MAX);
        });

        return array_map(fn ($row) => $this->formatItem($row, $type), $ranked);
    }

    /**
     * @param array{item: mixed, type: string, relevance_score: float, corridor_distance_km: float|null, route_progress: float|null, direction: string} $row
     * @return array<string, mixed>
     */
    private function formatItem(array $row, string $type): array
    {
        $base = [
            'type' => $type,
            'relevance_score' => $row['relevance_score'],
            'corridor_distance_km' => $row['corridor_distance_km'],
            'route_progress' => $row['route_progress'],
            'direction' => $row['direction'],
        ];

        $item = $row['item'];

        if ($type === 'report') {
            $mediaUrls = [];
            if ($item->relationLoaded('media')) {
                $mediaUrls = $item->media
                    ->filter(fn ($m) => $m->type === 'image')
                    ->map(fn ($m) => asset('storage/' . $m->media_url))
                    ->values()
                    ->all();
            }
            return $base + [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'title' => $item->title,
                'description' => $item->description,
                'category_id' => $item->category_id,
                'category_name' => $item->category?->name ?? 'General',
                'category_icon' => $item->category?->icon,
                'priority' => $item->priority,
                'status' => $item->status,
                'latitude' => (float) $item->latitude,
                'longitude' => (float) $item->longitude,
                'district' => $item->district,
                'helpful_count' => (int) $item->helpful_count,
                'unhelpful_count' => (int) ($item->unhelpful_count ?? 0),
                'comments_count' => (int) ($item->comments_count ?? 0),
                'confirmed_by_count' => (int) ($item->confirmed_by_count ?? 0),
                'reporter_name' => $item->user?->name ?? 'Anonymous',
                'reporter_avatar' => ($a = ($item->user?->avatar ?? null))
                    ? (str_starts_with($a, 'http') ? $a : asset('storage/' . $a))
                    : null,
                'image_urls' => $mediaUrls,
                'image_url' => $mediaUrls[0] ?? null,
                'time_ago' => $item->created_at?->diffForHumans(),
                'time_state' => $item->time_state,
                'created_at' => $item->created_at,
            ];
        }

        if ($type === 'alert') {
            return $base + [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'title' => $item->title,
                'description' => $item->description,
                'alert_type' => $item->alert_type,
                'severity' => $item->severity,
                'latitude' => $item->latitude !== null ? (float) $item->latitude : null,
                'longitude' => $item->longitude !== null ? (float) $item->longitude : null,
                'district' => $item->affected_district,
                'time_ago' => $item->created_at?->diffForHumans(),
                'time_state' => $this->timeStateLabel($item->created_at),
                'created_at' => $item->created_at,
            ];
        }

        // place
        return $base + [
            'id' => $item->id,
            'uuid' => $item->uuid,
            'name' => $item->name,
            'title' => $item->name,
            'description' => $item->description,
            'category' => $item->category?->name ?? 'Other',
            'category_icon' => $item->category?->icon ?? 'place',
            'address' => $item->address,
            'latitude' => (float) $item->latitude,
            'longitude' => (float) $item->longitude,
            'district' => $item->district,
            'average_rating' => (float) ($item->average_rating ?? 0),
            'total_reviews' => (int) ($item->total_reviews ?? 0),
            'is_verified' => (bool) ($item->is_verified ?? false),
            'phone' => $item->phone,
            'image_url' => $item->relationLoaded('images')
                ? ($item->images->first()?->image_url)
                : null,
        ];
    }

    private function timeStateLabel($createdAt): string
    {
        if (!$createdAt) {
            return 'expired';
        }
        $minutes = (int) abs(now()->diffInMinutes($createdAt));
        if ($minutes < 60) {
            return 'live';
        }
        if ($createdAt->isToday()) {
            return 'today';
        }
        if ((int) abs($createdAt->diffInDays(now())) <= 7) {
            return 'recent';
        }
        return 'expired';
    }
}
