<?php

namespace App\Services\Routing;

use App\Helpers\GeoHelper;

/**
 * Returns approximate geometry between two trekking waypoints.
 *
 * This does NOT attempt to route via any trail/path network.
 * It returns the two endpoints as a straight-line segment with
 * status='approximate' and source='curated_waypoints'.
 *
 * When a hiking routing engine becomes available (Valhalla, GraphHopper, etc.),
 * replace this implementation without changing the interface.
 */
class ApproximateTrekkingRouter implements RoutingRouterInterface
{
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $distanceM = GeoHelper::haversineMeters($fromLat, $fromLng, $toLat, $toLng);

        return [
            'points' => [
                ['lat' => $fromLat, 'lng' => $fromLng],
                ['lat' => $toLat, 'lng' => $toLng],
            ],
            'distance_m' => $distanceM,
            'duration_s' => 0,
            'status' => 'approximate',
            'source' => 'curated_waypoints',
        ];
    }
}
