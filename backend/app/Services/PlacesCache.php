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

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    /** Cache key for the Nepal-wide places payload. */
    public static function allKey(): string
    {
        return 'places:all:v' . self::version();
    }

    /** Invalidate every versioned places cache (create/update/delete/import...). */
    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}