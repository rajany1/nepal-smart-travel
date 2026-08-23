<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shared Overpass API client with a Redis bbox cache + rate-limit guard.
 *
 * Flow:
 *   Flutter -> Laravel -> Redis hit  -> respond
 *   Flutter -> Laravel -> Redis miss -> Overpass -> Redis store -> respond
 *
 * Equivalent viewport requests normalize to the same bbox key, so repeated
 * map movement never hits Overpass twice for the same area.
 */
class OsmOverpassService
{
    /** TTL for raw Overpass responses (24h — spec allows 24h..7d). */
    public const RAW_TTL = 86400;

    private const ENDPOINTS = [
        'https://overpass-api.de/api/interpreter',
        'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
    ];

    private const RATE_LIMIT_KEY = 'osm:overpass:global';
    private const RATE_LIMIT_MAX = 30; // requests per minute (public Overpass instances)
    private const RATE_LIMIT_WINDOW = 60;

    /**
     * Normalized bbox cache key. Coordinates are rounded to 4 decimals
     * (~11m), so equivalent viewport requests share one cache entry.
     */
    public static function bboxCacheKey(
        float $minLat,
        float $minLng,
        float $maxLat,
        float $maxLng,
        float $radiusKm = 0.0
    ): string {
        $minLat = max(-90, min(90, $minLat));
        $minLng = max(-180, min(180, $minLng));
        $maxLat = max(-90, min(90, $maxLat));
        $maxLng = max(-180, min(180, $maxLng));

        $r = fn($v) => round($v, 4);
        return sprintf(
            'osm:bbox:%s_%s_%s_%s:r%s',
            $r($minLat),
            $r($minLng),
            $r($maxLat),
            $r($maxLng),
            round($radiusKm, 1)
        );
    }

    /** Get a cached raw Overpass response, or null on miss. */
    public static function getCachedRaw(string $cacheKey): ?array
    {
        $raw = Cache::get($cacheKey);
        if (!is_string($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /** Store a raw Overpass response (only non-empty payloads). */
    public static function putCachedRaw(string $cacheKey, array $data, ?int $ttl = self::RAW_TTL): void
    {
        if (!empty($data['elements'])) {
            Cache::put($cacheKey, json_encode($data), $ttl);
        }
    }

    /**
     * Fetch a raw Overpass response: Redis cache first, then Overpass
     * mirrors (with 429 backoff). Returns decoded JSON or null.
     */
    public function fetchRaw(string $query, string $cacheKey = null, ?int $ttl = self::RAW_TTL, ?int $httpTimeout = 30): ?array
    {
        if ($cacheKey !== null) {
            $cached = self::getCachedRaw($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $this->waitForRateLimit();

        foreach (self::ENDPOINTS as $endpoint) {
            try {
                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: NepalSmartTravel/1.0",
                        'content' => 'data=' . urlencode($query),
                        'timeout' => $httpTimeout,
                        'ignore_errors' => true,
                    ],
                ];
                $context = stream_context_create($opts);
                $responseBody = @file_get_contents($endpoint, false, $context);

                if ($responseBody === false) {
                    Log::warning('OSM Overpass: connection failed', ['endpoint' => $endpoint]);
                    continue;
                }

                $httpCode = 200;
                if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
                    $httpCode = (int) $m[0];
                }

                if ($httpCode === 429) {
                    Log::warning('OSM Overpass: rate limited', ['endpoint' => $endpoint]);
                    usleep(700000);
                    continue;
                }

                if ($httpCode !== 200) {
                    Log::warning('OSM Overpass: request failed', ['status' => $httpCode, 'endpoint' => $endpoint]);
                    continue;
                }

                $data = json_decode($responseBody, true);
                if (!is_array($data) || !isset($data['elements'])) {
                    Log::warning('OSM Overpass: invalid payload', ['endpoint' => $endpoint]);
                    continue;
                }

                if ($cacheKey !== null) {
                    self::putCachedRaw($cacheKey, $data, $ttl);
                }

                return $data;
            } catch (\Exception $e) {
                Log::error('OSM Overpass error: ' . $e->getMessage() . ' @ ' . $endpoint);
            }
        }

        Log::warning('OSM Overpass: all endpoints failed');
        return null;
    }

    /** Rough lat/lng bbox around a point for a radius (km). */
    public static function radiusToBbox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * max(0.2, cos(deg2rad($lat))));

        return [
            'minLat' => max(-90, $lat - $latDelta),
            'maxLat' => min(90, $lat + $latDelta),
            'minLng' => max(-180, $lng - $lngDelta),
            'maxLng' => min(180, $lng + $lngDelta),
        ];
    }

    /** Throttle Outgoing Overpass calls via the Redis-backed rate limiter. */
    private function waitForRateLimit(): void
    {
        $attempts = 0;
        while (RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, self::RATE_LIMIT_MAX)) {
            $attempts++;
            if ($attempts > 10) {
                Log::warning('OSM Overpass: rate-limit wait exceeded budget, proceeding anyway');
                break;
            }
            usleep(500000);
        }
        RateLimiter::hit(self::RATE_LIMIT_KEY, self::RATE_LIMIT_WINDOW);
    }
}