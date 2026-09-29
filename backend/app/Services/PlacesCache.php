<?php

namespace App\Services;

use App\Models\User;
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

    /** Shared cache for the "show_on_map = false" author ids. */
    public const HIDDEN_AUTHORS_KEY = 'places:hidden_authors';

    /** TTL for the Nepal-wide /places/all payload. */
    public const ALL_TTL = 600; // 10 minutes

    /** TTL for viewport bbox queries (shorter — viewport changes frequently). */
    public const BBOX_TTL = 300; // 5 minutes

    /** TTL for radius-based nearby queries (same cadence as bbox). */
    public const NEARBY_TTL = 300; // 5 minutes

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

    /**
     * Cache key for a radius-based nearby query.
     *
     * The centre is snapped to the same 0.05° grid used by [bboxKey] so panning
     * inside one cell shares a single entry, the radius is bucketed to whole
     * kilometres (zoom-derived radii are otherwise a different key on every
     * zoom tick), and the remaining filters are hashed verbatim.
     *
     * @param string $endpoint Distinguishes /places/nearby from
     *                         /places/nearby-combined, which return different
     *                         payloads for the same coordinates.
     */
    public static function nearbyKey(
        string $endpoint,
        float $lat,
        float $lng,
        float $radiusKm,
        ?int $categoryId = null,
        ?string $search = null,
        int $limit = 50
    ): string {
        $grid = 0.05; // 0.05° grid ≈ 5.5 km (shared with bboxKey)
        $gLat = round($lat / $grid) * $grid;
        $gLng = round($lng / $grid) * $grid;
        $radius = max(1, (int) round($radiusKm));
        $category = $categoryId ?? 0;
        $query = ($search !== null && $search !== '')
            ? substr(sha1($search), 0, 12)
            : '-';
        return "places:{$endpoint}:v" . self::version()
            . ":{$gLat},{$gLng},r{$radius},c{$category},l{$limit},s{$query}";
    }

    /**
     * Ids of users who opted out of the public map.
     *
     * Cached because the JSON_EXTRACT scan over `users` would otherwise run on
     * every listing request; every place endpoint shares this one entry, and
     * [bump] plus the settings endpoint invalidate it.
     *
     * @return array<int>
     */
    public static function hiddenAuthors(): array
    {
        return Cache::remember(self::HIDDEN_AUTHORS_KEY, self::ALL_TTL, function () {
            return User::whereRaw("JSON_EXTRACT(settings, '$.show_on_map') = 'false'")
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });
    }

    /**
     * Drop the hidden-author cache.
     *
     * Called when a user changes `show_on_map` so their privacy choice takes
     * effect immediately instead of waiting out [ALL_TTL].
     */
    public static function forgetHiddenAuthors(): void
    {
        Cache::forget(self::HIDDEN_AUTHORS_KEY);
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
        self::forgetHiddenAuthors();
    }
}