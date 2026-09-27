<?php

namespace App\Services\Routing;

/**
 * Contract for a routing provider that resolves geometry between two points.
 *
 * Implementations may return real routed geometry (e.g. OSRM driving)
 * or approximate geometry (e.g. curated trekking waypoints).
 */
interface RoutingRouterInterface
{
    /**
     * Resolve route geometry between origin and destination.
     *
     * @param float $fromLat
     * @param float $fromLng
     * @param float $toLat
     * @param float $toLng
     * @return array{points: array, distance_m: float, duration_s: float, status: string, source: string}|null
     *         status: 'routed' | 'approximate' | 'unrouted'
     *         source: routing provider identifier
     *         points: [['lat' => float, 'lng' => float], ...]
     */
    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array;
}
