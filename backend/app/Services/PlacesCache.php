<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Central place-cache key management.
 *
 * Versioned keys (places:all:v{n}) allow endpoint-specific cache
 * architecture to evolve: any place mutation bumps the version, which
 * immediately invalidates every versioned place cache at once.
 */
class PlacesCache
{
    private const VERSION_KEY = 'places:cache:version';

    /** TTL for the Nepal-wide /places/all payload. */
    public const ALL_TTL = 600; // 10 minutes

    /** TTL for viewport bbox queries (shorter — viewport changes frequently). */
    public const BBOX_TTL = 300; // 5 minutes

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    /** Cache key for the Nepal-wide public API places payload. */
    public static function allKey(): string
    {
        return 'places:all:v' . self::version();
    }

    /** Cache key for the admin live-map places (wider limit, different shape). */
    public static function adminAllKey(): string
    {
        return 'places:admin_all:v' . self::version();
    }

    /**
     * Cache key for a viewport bbox query.
     *
     * Rounds bounds to 0.05° grid (~5.5 km cells) so nearby pan gestures
     * share the same cache entry. Includes zoom bucket for density variants.
     */
    public static function bboxKey(float $minLat, float $maxLat, float $minLng, float $maxLng, int $zoom): string
    {
        $grid = 0.05; // 0.05° grid ≈ 5.5 km
        $gMinLat = round($minLat / $grid) * $grid;
        $gMaxLat = round($maxLat / $grid) * $grid;
        $gMinLng = round($minLng / $grid) * $grid;
        $gMaxLng = round($maxLng / $grid) * $grid;
        $zoomBucket = (int) $zoom;
        return "places:bbox:v" . self::version() . ":{$gMinLat},{$gMaxLat},{$gMinLng},{$gMaxLng}:z{$zoomBucket}";
    }

    /** Invalidate every versioned places cache (create/update/delete/import...). */
    public static function bump(): void
    {
        $oldVersion = self::version();
        Cache::forever(self::VERSION_KEY, $oldVersion + 1);

        // Clean up orphaned old version keys (best-effort, non-blocking)
        if ($oldVersion > 0) {
            Cache::forget('places:all:v' . $oldVersion);
            Cache::forget('places:admin_all:v' . $oldVersion);
        }

        // Invalidate related caches that depend on places data
        Cache::forget('places:hidden_authors');
    }
}