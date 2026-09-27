<?php

namespace App\Services\Routing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Routes via the public OSRM demo server using the driving profile.
 *
 * Returns real road-following geometry with 'routed' status.
 */
class OsrmDrivingRouter implements RoutingRouterInterface
{
    private const OSRM_BASE = 'https://router.project-osrm.org/route/v1/driving';
    private const TIMEOUT = 15;

    public function route(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $url = sprintf(
            '%s/%s,%s;%s,%s?overview=full&geometries=geojson&steps=false',
            self::OSRM_BASE,
            $fromLng,
            $fromLat,
            $toLng,
            $toLat,
        );

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => 'NepalSmartTravel/1.0'])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('OSRM driving: HTTP ' . $response->status(), [
                    'from' => "$fromLat,$fromLng",
                    'to' => "$toLat,$toLng",
                ]);
                return null;
            }

            $json = $response->json();
            if (($json['code'] ?? '') !== 'Ok' || empty($json['routes'][0])) {
                return null;
            }

            $route = $json['routes'][0];
            $coords = $route['geometry']['coordinates'] ?? [];

            $points = array_map(
                fn($c) => ['lat' => (float) $c[1], 'lng' => (float) $c[0]],
                $coords,
            );

            return [
                'points' => $points,
                'distance_m' => (float) ($route['distance'] ?? 0),
                'duration_s' => (float) ($route['duration'] ?? 0),
                'status' => 'routed',
                'source' => 'osrm_driving',
            ];
        } catch (\Exception $e) {
            Log::warning('OSRM driving error: ' . $e->getMessage(), [
                'from' => "$fromLat,$fromLng",
                'to' => "$toLat,$toLng",
            ]);
            return null;
        }
    }
}
