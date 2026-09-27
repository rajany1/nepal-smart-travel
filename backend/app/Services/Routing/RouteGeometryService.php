<?php

namespace App\Services\Routing;

use App\Models\CuratedRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resolves route geometry for curated routes (trekking + itinerary).
 *
 * For each consecutive pair of track waypoints, determines the routing mode
 * and dispatches to the appropriate router. Returns an array of segments
 * with geometry, status, and source metadata.
 *
 * Architecture:
 *   RouteGeometryService
 *       ├── DrivingRouter  (OSRM driving profile)
 *       └── TrekkingRouter  (approximate — no hiking router available)
 */
class RouteGeometryService
{
    private OsrmDrivingRouter $drivingRouter;
    private ApproximateTrekkingRouter $trekkingRouter;

    /** Cache TTL: 30 days — curated routes change rarely. */
    private const CACHE_TTL = 60 * 60 * 24 * 30;

    public function __construct()
    {
        $this->drivingRouter = new OsrmDrivingRouter();
        $this->trekkingRouter = new ApproximateTrekkingRouter();
    }

    /**
     * Resolve full geometry for a curated route.
     *
     * @return array{
     *     route_id: int,
     *     segments: array,
     *     total_distance_m: float,
     *     geometry_version: string
     * }|null
     */
    public function resolveRouteGeometry(CuratedRoute $route): ?array
    {
        $track = $route->trackPoints();
        if (count($track) < 2) {
            return null;
        }

        $cacheKey = $this->cacheKey($route);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $segments = [];
        $totalDistance = 0.0;

        for ($i = 0; $i < count($track) - 1; $i++) {
            $from = $track[$i];
            $to = $track[$i + 1];

            $segment = $this->resolveSegment($route, $from, $to, $i);
            $segments[] = $segment;
            $totalDistance += $segment['distance_m'];
        }

        $result = [
            'route_id' => $route->id,
            'segments' => $segments,
            'total_distance_m' => $totalDistance,
            'geometry_version' => $this->geometryVersion($route),
        ];

        Cache::put($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * Determine the mode for a segment and route it.
     */
    private function resolveSegment(CuratedRoute $route, array $from, array $to, int $index): array
    {
        $mode = $this->segmentMode($route, $index);

        $result = match ($mode) {
            'driving' => $this->drivingRouter->route(
                $from['lat'], $from['lng'],
                $to['lat'], $to['lng'],
            ),
            default => $this->trekkingRouter->route(
                $from['lat'], $from['lng'],
                $to['lat'], $to['lng'],
            ),
        };

        if ($result === null) {
            return [
                'from_name' => $from['name'] ?? "Waypoint $index",
                'to_name' => $to['name'] ?? "Waypoint " . ($index + 1),
                'mode' => $mode,
                'status' => 'unrouted',
                'source' => 'none',
                'points' => [],
                'distance_m' => 0.0,
                'duration_s' => 0.0,
            ];
        }

        return [
            'from_name' => $from['name'] ?? "Waypoint $index",
            'to_name' => $to['name'] ?? "Waypoint " . ($index + 1),
            'mode' => $mode,
            'status' => $result['status'],
            'source' => $result['source'],
            'points' => $result['points'],
            'distance_m' => $result['distance_m'],
            'duration_s' => $result['duration_s'],
        ];
    }

    /**
     * Determine routing mode for a segment between track[i] and track[i+1].
     *
     * Deterministic rules:
     * - route_type='itinerary' → 'driving' (all legs default to road routing)
     * - route_type='trekking'  → 'trekking' (all legs use approximate waypoints)
     * - Per-leg overrides via track segment_modes (future-proof, not breaking)
     */
    private function segmentMode(CuratedRoute $route, int $index): string
    {
        $modes = $route->track_segment_modes ?? null;
        if (is_array($modes) && isset($modes[$index])) {
            $m = strtolower($modes[$index]);
            if (in_array($m, ['driving', 'trekking', 'walking'])) {
                return $m === 'walking' ? 'trekking' : $m;
            }
        }

        return match ($route->route_type) {
            'itinerary' => 'driving',
            'trekking' => 'trekking',
            default => 'driving',
        };
    }

    /**
     * Cache key includes route ID + geometry version to invalidate on edits.
     */
    private function cacheKey(CuratedRoute $route): string
    {
        return 'route:geometry:' . $route->id . ':v' . $this->geometryVersion($route);
    }

    /**
     * Geometry version — changes when track or segment_modes are updated.
     */
    private function geometryVersion(CuratedRoute $route): string
    {
        $trackHash = md5(json_encode($route->track ?? []));
        $modesHash = md5(json_encode($route->track_segment_modes ?? []));
        return substr($trackHash, 0, 8) . substr($modesHash, 0, 8);
    }

    /**
     * Invalidate cached geometry for a route (call on save/update).
     */
    public function invalidateCache(CuratedRoute $route): void
    {
        $key = $this->cacheKey($route);
        Cache::forget($key);
    }
}
