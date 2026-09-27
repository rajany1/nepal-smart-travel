<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Redis cache for Nepal map boundary data (ADM0/ADM1/ADM2).
 *
 * All GeoJSON content is stored as raw JSON strings in Redis so both the
 * admin web map and the Flutter mobile app can fetch them without hitting
 * the filesystem on every request.
 *
 * TTL: configurable via GameSetting `map_boundary_ttl_days` (default 3 days).
 * On place mutations the places cache is bumped via PlacesCache::bump().
 * Boundary data is invalidated manually or via artisan map:refresh-cache.
 */
class MapCacheService
{
    private const PREFIX = 'map:boundary:';

    public static function boundaryKey(): string { return self::PREFIX . 'adm0'; }
    public static function provincesKey(): string { return self::PREFIX . 'adm1'; }
    public static function districtsKey(): string { return self::PREFIX . 'adm2'; }

    public static function ttl(): int
    {
        $days = (int) (\App\Models\GameSetting::getValue('map_boundary_ttl_days', 3) ?? 3);
        return $days * 86400;
    }

    /** Get cached boundary JSON or load from file and cache. */
    public static function getBoundary(): ?string
    {
        return self::cached(self::boundaryKey(), 'nepal_boundary.geojson');
    }

    public static function getProvinces(): ?string
    {
        return self::cached(self::provincesKey(), 'nepal_adm1.geojson');
    }

    public static function getDistricts(): ?string
    {
        return self::cached(self::districtsKey(), 'nepal_adm2.geojson');
    }

    /** Force-refresh all boundary caches from filesystem. */
    public static function refreshAll(): void
    {
        self::refresh(self::boundaryKey(), 'nepal_boundary.geojson');
        self::refresh(self::provincesKey(), 'nepal_adm1.geojson');
        self::refresh(self::districtsKey(), 'nepal_adm2.geojson');
    }

    /** Invalidate a single key. */
    public static function invalidate(string ...$keys): void
    {
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /** Invalidate all boundary caches. */
    public static function invalidateAll(): void
    {
        self::invalidate(self::boundaryKey(), self::provincesKey(), self::districtsKey());
    }

    // ── internals ──────────────────────────────────────────────────────

    private static function cached(string $redisKey, string $fileName): ?string
    {
        $cached = Cache::get($redisKey);
        if ($cached !== null) {
            return $cached;
        }

        $path = resource_path("views/admin/partials/{$fileName}");
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        Cache::put($redisKey, $content, self::ttl());
        return $content;
    }

    private static function refresh(string $redisKey, string $fileName): void
    {
        $path = resource_path("views/admin/partials/{$fileName}");
        if (!file_exists($path)) {
            return;
        }

        $content = file_get_contents($path);
        if ($content !== false) {
            Cache::put($redisKey, $content, self::ttl());
        }
    }
}
