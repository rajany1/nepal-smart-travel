<?php

namespace App\Services\TravelContext;

use App\Models\CuratedRoute;
use App\Models\Place;
use App\Services\Routing\ApproximateTrekkingRouter;
use App\Services\Routing\OsrmDrivingRouter;
use App\Services\Routing\RoutingRouterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Builds a TravelContext from origin / checkpoints / destination.
 *
 * Reuses the existing RoutingRouterInterface providers (OSRM driving +
 * approximate fallback) — no new routing engine. Legs are chained the same
 * way RouteGeometryService chains curated-route track segments.
 */
class TravelContextService
{
    private RoutingRouterInterface $drivingRouter;
    private RoutingRouterInterface $approxRouter;

    /** Resolved corridor cache: 1 hour (route paths for a city pair are stable). */
    private const CACHE_TTL = 3600;

    /** OSRM failure → straight-line legs, clearly marked approximate. */
    public function __construct()
    {
        $this->drivingRouter = new OsrmDrivingRouter();
        $this->approxRouter = new ApproximateTrekkingRouter();
    }

    /**
     * Resolve free-form place text (or lat,lng) to a point.
     *
     * Order: explicit coordinates → curated-route waypoint → place name →
     * district HQ/centroid from boundary data → district place average.
     *
     * @return array{name: string, lat: float, lng: float, resolved_via: string}|null
     */
    public function resolvePoint(string $input): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // "lat,lng" or "lat, lng"
        if (preg_match('/^(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)$/', $input, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return ['name' => $input, 'lat' => $lat, 'lng' => $lng, 'resolved_via' => 'coordinates'];
            }
        }

        $cacheKey = 'travel:point:' . md5(mb_strtolower($input));
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $resolved = $this->resolvePointInternal($input);
        if ($resolved !== null) {
            Cache::put($cacheKey, $resolved, 60 * 60 * 24 * 7);
        }
        return $resolved;
    }

    private function resolvePointInternal(string $input): ?array
    {
        $lower = mb_strtolower($input);

        // Curated-route named track waypoints (e.g. trail heads, passes).
        foreach (CuratedRoute::active()->get() as $route) {
            foreach ($route->trackPoints() as $pt) {
                $name = trim((string) ($pt['name'] ?? ''));
                if ($name !== '' && mb_strtolower($name) === $lower) {
                    return [
                        'name' => $name,
                        'lat' => (float) $pt['lat'],
                        'lng' => (float) $pt['lng'],
                        'resolved_via' => 'curated_waypoint',
                    ];
                }
            }
        }

        // Exact place name (most-reviewed first).
        $place = Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw('LOWER(name) = ?', [$lower])
            ->orderByDesc('total_reviews')
            ->first(['name', 'latitude', 'longitude']);
        if ($place) {
            return [
                'name' => $place->name,
                'lat' => (float) $place->latitude,
                'lng' => (float) $place->longitude,
                'resolved_via' => 'place',
            ];
        }

        // Partial place name (e.g. "Pokhara International Airport").
        $place = Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . $lower . '%'])
            ->orderByDesc('total_reviews')
            ->first(['name', 'latitude', 'longitude']);
        if ($place) {
            return [
                'name' => $place->name,
                'lat' => (float) $place->latitude,
                'lng' => (float) $place->longitude,
                'resolved_via' => 'place_partial',
            ];
        }

        // District / HQ from bundled ADM2 boundary (DISTRICT + HQ properties).
        $districtPt = $this->resolveFromBoundary($lower);
        if ($districtPt !== null) {
            return $districtPt;
        }

        // District average of known places (works when boundary cache is cold).
        $agg = Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('district')
            ->whereRaw('LOWER(district) = ?', [$lower])
            ->selectRaw('AVG(latitude) AS avg_lat, AVG(longitude) AS avg_lng, COUNT(*) AS c')
            ->first();
        if ($agg && $agg->c > 0 && $agg->avg_lat !== null) {
            return [
                'name' => $input,
                'lat' => (float) $agg->avg_lat,
                'lng' => (float) $agg->avg_lng,
                'resolved_via' => 'district_places',
            ];
        }

        return null;
    }

    /**
     * Resolve a district name via nepal_adm2 GeoJSON (DISTRICT match →
     * polygon centroid; HQ name may match a place later). Read-only file.
     *
     * @return array{name: string, lat: float, lng: float, resolved_via: string}|null
     */
    private function resolveFromBoundary(string $lower): ?array
    {
        try {
            $path = base_path('resources/views/admin/partials/nepal_adm2.geojson');
            if (!is_file($path)) {
                return null;
            }
            $json = Cache::remember('travel:adm2_index', 60 * 60 * 24, function () use ($path) {
                $raw = json_decode((string) file_get_contents($path), true);
                $index = [];
                foreach (($raw['features'] ?? []) as $feature) {
                    $name = (string) ($feature['properties']['DISTRICT'] ?? '');
                    $hq = (string) ($feature['properties']['HQ'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $centroid = $this->polygonCentroid($feature['geometry'] ?? null);
                    if ($centroid === null) {
                        continue;
                    }
                    $index[mb_strtolower($name)] = [
                        'name' => $name,
                        'hq' => $hq,
                        'lat' => $centroid[0],
                        'lng' => $centroid[1],
                    ];
                    if ($hq !== '') {
                        $key = 'hq:' . mb_strtolower($hq);
                        $index[$key] = [
                            'name' => $hq,
                            'hq' => $hq,
                            'district' => $name,
                            'lat' => $centroid[0],
                            'lng' => $centroid[1],
                            'via_hq' => true,
                        ];
                    }
                }
                return $index;
            });

            if (!is_array($json)) {
                return null;
            }

            // Prefer exact district, then exact HQ name.
            $hit = $json[$lower] ?? $json['hq:' . $lower] ?? null;
            if ($hit === null) {
                return null;
            }

            // HQ hit: refine with a place in that HQ/district if available.
            if (!empty($hit['via_hq'])) {
                $place = Place::active()
                    ->whereNotNull('latitude')
                    ->whereRaw('LOWER(name) = ?', [$lower])
                    ->first(['name', 'latitude', 'longitude']);
                if ($place) {
                    return [
                        'name' => $place->name,
                        'lat' => (float) $place->latitude,
                        'lng' => (float) $place->longitude,
                        'resolved_via' => 'hq_place',
                    ];
                }
            }

            return [
                'name' => $hit['name'],
                'lat' => (float) $hit['lat'],
                'lng' => (float) $hit['lng'],
                'resolved_via' => 'district_boundary',
            ];
        } catch (\Throwable $e) {
            Log::warning('travel-context boundary resolve failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @return array{0: float, 1: float}|null [lat, lng]
     */
    private function polygonCentroid(?array $geometry): ?array
    {
        if (!$geometry) {
            return null;
        }
        $type = $geometry['type'] ?? '';
        $coords = $geometry['coordinates'] ?? null;
        if ($coords === null) {
            return null;
        }

        // Take the outer ring of the largest polygon.
        $ring = null;
        if ($type === 'Polygon') {
            $ring = $coords[0] ?? null;
        } elseif ($type === 'MultiPolygon') {
            $best = null;
            $bestCount = 0;
            foreach ($coords as $poly) {
                $r = $poly[0] ?? null;
                if (is_array($r) && count($r) > $bestCount) {
                    $bestCount = count($r);
                    $best = $r;
                }
            }
            $ring = $best;
        }
        if (!is_array($ring) || count($ring) === 0) {
            return null;
        }

        $sumLat = 0.0;
        $sumLng = 0.0;
        $n = 0;
        // Sample up to 200 vertices for speed.
        $step = max(1, (int) ceil(count($ring) / 200));
        for ($i = 0; $i < count($ring); $i += $step) {
            $pt = $ring[$i];
            if (isset($pt[0], $pt[1])) {
                $sumLng += (float) $pt[0];
                $sumLat += (float) $pt[1];
                $n++;
            }
        }
        if ($n === 0) {
            return null;
        }
        return [$sumLat / $n, $sumLng / $n];
    }

    /**
     * Build a travel context.
     *
     * @param array{name?: string, lat?: float|string, lng?: float|string}|string $origin
     * @param array{name?: string, lat?: float|string, lng?: float|string}|string $destination
     * @param array<int, array{name?: string, lat?: float|string, lng?: float|string}|string> $checkpoints
     */
    public function resolve(
        array|string $origin,
        array|string $destination,
        array $checkpoints = [],
        ?float $userLat = null,
        ?float $userLng = null,
        bool $detectAmbiguity = true,
    ): TravelContext {
        $unresolved = [];

        $originPt = $this->normalizePoint($origin, $unresolved);
        $destPt = $this->normalizePoint($destination, $unresolved);

        $checkpointPts = [];
        foreach ($checkpoints as $cp) {
            $pt = $this->normalizePoint($cp, $unresolved);
            if ($pt !== null) {
                $checkpointPts[] = $pt;
            }
        }

        if ($originPt === null || $destPt === null) {
            return $this->emptyContext(
                is_array($origin) ? (string) ($origin['name'] ?? 'Origin') : (string) $origin,
                is_array($destination) ? (string) ($destination['name'] ?? 'Destination') : (string) $destination,
                $checkpointPts,
                $unresolved,
            );
        }

        $nodes = array_merge([$originPt], $checkpointPts, [$destPt]);
        $cacheKey = 'travel:corridor:' . md5(json_encode(array_map(
            fn ($n) => round($n['lat'], 5) . ',' . round($n['lng'], 5),
            $nodes
        )));

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['segments'], $cached['status'])) {
            $context = $this->assembleContext($originPt, $destPt, $checkpointPts, $cached['segments'], $cached['status'], $unresolved);
            // Ambiguity is cheap enough to re-check live (uses OSRM alternatives).
            if ($detectAmbiguity && $checkpointPts === []) {
                $context = $this->attachAmbiguity($context, $originPt, $destPt);
            }
            return $this->withJourney($context, $userLat, $userLng);
        }

        $segments = [];
        $statuses = [];
        $totalDist = 0.0;
        $totalDur = 0.0;

        for ($i = 0; $i < count($nodes) - 1; $i++) {
            $from = $nodes[$i];
            $to = $nodes[$i + 1];
            $leg = $this->drivingRouter->route($from['lat'], $from['lng'], $to['lat'], $to['lng']);
            if ($leg === null) {
                $leg = $this->approxRouter->route($from['lat'], $from['lng'], $to['lat'], $to['lng']);
            }
            if ($leg === null) {
                $leg = [
                    'points' => [
                        ['lat' => $from['lat'], 'lng' => $from['lng']],
                        ['lat' => $to['lat'], 'lng' => $to['lng']],
                    ],
                    'distance_m' => 0.0,
                    'duration_s' => 0.0,
                    'status' => 'unrouted',
                    'source' => 'none',
                ];
            }

            $segments[] = [
                'from_name' => $from['name'],
                'to_name' => $to['name'],
                'points' => $leg['points'],
                'status' => $leg['status'],
                'source' => $leg['source'],
                'distance_m' => (float) $leg['distance_m'],
                'duration_s' => (float) $leg['duration_s'],
            ];
            $statuses[] = $leg['status'];
            $totalDist += (float) $leg['distance_m'];
            $totalDur += (float) $leg['duration_s'];
        }

        $uniqueStatuses = array_values(array_unique($statuses));
        $status = match (true) {
            $uniqueStatuses === [] || $uniqueStatuses === ['unrouted'] => TravelContext::STATUS_UNROUTED,
            $uniqueStatuses === ['routed'] => TravelContext::STATUS_ROUTED,
            $uniqueStatuses === ['approximate'] => TravelContext::STATUS_APPROXIMATE,
            default => TravelContext::STATUS_MIXED,
        };

        Cache::put($cacheKey, ['segments' => $segments, 'status' => $status, 'total_dist' => $totalDist, 'total_dur' => $totalDur], self::CACHE_TTL);

        $context = $this->assembleContext($originPt, $destPt, $checkpointPts, $segments, $status, $unresolved);

        if ($detectAmbiguity && $checkpointPts === []) {
            $context = $this->attachAmbiguity($context, $originPt, $destPt);
        }

        return $this->withJourney($context, $userLat, $userLng);
    }

    /**
     * @param array<int, string> $unresolved
     * @return array{name: string, lat: float, lng: float, resolved_via: string}|null
     */
    private function normalizePoint(array|string $point, array &$unresolved): ?array
    {
        if (is_string($point)) {
            $resolved = $this->resolvePoint($point);
            if ($resolved === null) {
                $unresolved[] = $point;
            }
            return $resolved;
        }

        if (isset($point['lat'], $point['lng']) && is_numeric($point['lat']) && is_numeric($point['lng'])) {
            return [
                'name' => (string) ($point['name'] ?? ($point['lat'] . ',' . $point['lng'])),
                'lat' => (float) $point['lat'],
                'lng' => (float) $point['lng'],
                'resolved_via' => (string) ($point['resolved_via'] ?? 'coordinates'),
            ];
        }

        $name = (string) ($point['name'] ?? '');
        if ($name === '') {
            return null;
        }
        $resolved = $this->resolvePoint($name);
        if ($resolved === null) {
            $unresolved[] = $name;
        }
        return $resolved;
    }

    /**
     * @param array<int, array{name: string, lat: float, lng: float, resolved_via?: string}> $checkpointPts
     * @param array<int, array<string, mixed>> $segments
     * @param array<int, string> $unresolved
     */
    private function assembleContext(
        array $originPt,
        array $destPt,
        array $checkpointPts,
        array $segments,
        string $status,
        array $unresolved,
    ): TravelContext {
        $geometry = [];
        foreach ($segments as $seg) {
            foreach ($seg['points'] as $p) {
                $geometry[] = ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
            }
        }
        $geometry = CorridorGeometry::simplify($geometry);

        $waypoints = [['name' => $originPt['name'], 'lat' => $originPt['lat'], 'lng' => $originPt['lng'], 'kind' => 'origin']];
        foreach ($checkpointPts as $cp) {
            $waypoints[] = ['name' => $cp['name'], 'lat' => $cp['lat'], 'lng' => $cp['lng'], 'kind' => 'checkpoint'];
        }
        $waypoints[] = ['name' => $destPt['name'], 'lat' => $destPt['lat'], 'lng' => $destPt['lng'], 'kind' => 'destination'];

        $totalDist = array_sum(array_column($segments, 'distance_m'));
        $totalDur = array_sum(array_column($segments, 'duration_s'));

        return new TravelContext(
            origin: $originPt,
            destination: $destPt,
            checkpoints: $checkpointPts,
            segments: $segments,
            waypoints: $waypoints,
            geometry: $geometry,
            status: $status,
            needsClarification: false,
            routeOptions: [],
            totalDistanceM: $totalDist > 0 ? $totalDist : null,
            totalDurationS: $totalDur > 0 ? $totalDur : null,
            journey: null,
            unresolved: $unresolved === [] ? null : implode(', ', $unresolved),
        );
    }

    /**
     * Detect material route ambiguity via OSRM alternatives (same provider,
     * not a new engine). Only flags when alternative paths meaningfully diverge.
     *
     * @return TravelContext
     */
    private function attachAmbiguity(TravelContext $context, array $originPt, array $destPt): TravelContext
    {
        if (!$context->hasCorridor()) {
            return $context;
        }

        try {
            $url = sprintf(
                'https://router.project-osrm.org/route/v1/driving/%s,%s;%s,%s?overview=full&geometries=geojson&alternatives=true&number=2&steps=false',
                $originPt['lng'],
                $originPt['lat'],
                $destPt['lng'],
                $destPt['lat']
            );
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'NepalSmartTravel/1.0'])
                ->get($url);
            if (!$response->successful()) {
                return $context;
            }
            $json = $response->json();
            if (($json['code'] ?? '') !== 'Ok' || empty($json['routes']) || count($json['routes']) < 2) {
                return $context;
            }

            $routes = $json['routes'];
            $primary = $routes[0];
            $alt = $routes[1];

            $primaryMid = $this->midpointOf($primary);
            $altMid = $this->midpointOf($alt);
            if ($primaryMid === null || $altMid === null) {
                return $context;
            }

            $divergenceKm = \App\Helpers\GeoHelper::haversineKm(
                $primaryMid['lat'],
                $primaryMid['lng'],
                $altMid['lat'],
                $altMid['lng']
            );
            $distA = (float) ($primary['distance'] ?? 0);
            $distB = (float) ($alt['distance'] ?? 0);
            $ratio = min($distA, $distB) / max(max($distA, $distB), 1);

            // Material only when paths clearly diverge and both are plausible lengths.
            if ($divergenceKm < 20 || $ratio < 0.6) {
                return $context;
            }

            $options = [];
            foreach ([$primary, $alt] as $i => $route) {
                $points = [];
                foreach (($route['geometry']['coordinates'] ?? []) as $c) {
                    $points[] = ['lat' => (float) $c[1], 'lng' => (float) $c[0]];
                }
                $points = CorridorGeometry::simplify($points, 60);
                $options[] = [
                    'label' => sprintf(
                        'Route %d · %s km · ~%s',
                        $i + 1,
                        round(((float) ($route['distance'] ?? 0)) / 1000, 0),
                        $this->formatDuration((float) ($route['duration'] ?? 0))
                    ),
                    'distance_m' => (float) ($route['distance'] ?? 0),
                    'duration_s' => (float) ($route['duration'] ?? 0),
                    'points' => $points,
                    'via' => $this->sampleViaPoints($points, 3),
                ];
            }

            return new TravelContext(
                origin: $context->origin,
                destination: $context->destination,
                checkpoints: $context->checkpoints,
                segments: $context->segments,
                waypoints: $context->waypoints,
                geometry: $context->geometry,
                status: $context->status,
                needsClarification: true,
                routeOptions: $options,
                totalDistanceM: $context->totalDistanceM,
                totalDurationS: $context->totalDurationS,
                journey: $context->journey,
                unresolved: $context->unresolved,
            );
        } catch (\Throwable $e) {
            Log::debug('travel-context ambiguity check skipped: ' . $e->getMessage());
            return $context;
        }
    }

    private function midpointOf(array $route): ?array
    {
        $coords = $route['geometry']['coordinates'] ?? [];
        if (count($coords) === 0) {
            return null;
        }
        $mid = $coords[(int) (count($coords) / 2)];
        return ['lat' => (float) $mid[1], 'lng' => (float) $mid[0]];
    }

    /**
     * Sample intermediate coordinates to use as optional checkpoints.
     *
     * @return array<int, array{name: string, lat: float, lng: float, resolved_via: string}>
     */
    private function sampleViaPoints(array $points, int $count): array
    {
        $via = [];
        $n = count($points);
        if ($n < 3) {
            return $via;
        }
        for ($i = 1; $i <= $count; $i++) {
            $idx = (int) round($i * ($n - 1) / ($count + 1));
            $idx = max(1, min($n - 2, $idx));
            $p = $points[$idx];
            $via[] = [
                'name' => sprintf('Via point %d', $i),
                'lat' => $p['lat'],
                'lng' => $p['lng'],
                'resolved_via' => 'route_sample',
            ];
        }
        return $via;
    }

    private function formatDuration(float $seconds): string
    {
        $mins = (int) round($seconds / 60);
        if ($mins < 60) {
            return $mins . 'm';
        }
        return intdiv($mins, 60) . 'h ' . ($mins % 60) . 'm';
    }

    private function withJourney(TravelContext $context, ?float $userLat, ?float $userLng): TravelContext
    {
        if ($userLat === null || $userLng === null || !$context->hasCorridor()) {
            return $context;
        }

        $measure = CorridorGeometry::measure($context->geometry, $userLat, $userLng);
        if ($measure === null) {
            return $context;
        }

        // Only trust progress when the user is reasonably near the corridor.
        $maxSnapM = (float) \App\Models\GameSetting::getValue('route_max_snap_km', 25) * 1000;
        if ($measure['distance_m'] > $maxSnapM) {
            return new TravelContext(
                origin: $context->origin,
                destination: $context->destination,
                checkpoints: $context->checkpoints,
                segments: $context->segments,
                waypoints: $context->waypoints,
                geometry: $context->geometry,
                status: $context->status,
                needsClarification: $context->needsClarification,
                routeOptions: $context->routeOptions,
                totalDistanceM: $context->totalDistanceM,
                totalDurationS: $context->totalDurationS,
                journey: null,
                unresolved: $context->unresolved,
            );
        }

        return new TravelContext(
            origin: $context->origin,
            destination: $context->destination,
            checkpoints: $context->checkpoints,
            segments: $context->segments,
            waypoints: $context->waypoints,
            geometry: $context->geometry,
            status: $context->status,
            needsClarification: $context->needsClarification,
            routeOptions: $context->routeOptions,
            totalDistanceM: $context->totalDistanceM,
            totalDurationS: $context->totalDurationS,
            journey: [
                'progress' => round($measure['progress'], 4),
                'distance_to_route_m' => (float) round($measure['distance_m']),
                'distance_along_m' => round($measure['progress'] * ($context->totalDistanceM ?: 0), 0),
            ],
            unresolved: $context->unresolved,
        );
    }

    /**
     * @param array<int, string> $unresolved
     */
    private function emptyContext(string $originName, string $destName, array $checkpointPts, array $unresolved): TravelContext
    {
        return new TravelContext(
            origin: ['name' => $originName, 'lat' => 0.0, 'lng' => 0.0, 'resolved_via' => 'unresolved'],
            destination: ['name' => $destName, 'lat' => 0.0, 'lng' => 0.0, 'resolved_via' => 'unresolved'],
            checkpoints: $checkpointPts,
            segments: [],
            waypoints: [],
            geometry: [],
            status: TravelContext::STATUS_UNROUTED,
            needsClarification: false,
            routeOptions: [],
            totalDistanceM: null,
            totalDurationS: null,
            journey: null,
            unresolved: $unresolved === [] ? 'Could not resolve location' : implode(', ', $unresolved),
        );
    }
}
