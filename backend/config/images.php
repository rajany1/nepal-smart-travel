<?php

/*
|--------------------------------------------------------------------------
| Place image discovery (Wikimedia-first)
|--------------------------------------------------------------------------
|
| Tuning knobs for the automatic multi-image system. Nothing here is a
| secret — Wikimedia APIs are anonymous — and nothing here is a source of
| truth: every accepted image, licence and attribution is persisted in the
| `place_images` table. Redis is used only for rate limiting and short-lived
| response caching.
|
*/

return [

    /*
    |--------------------------------------------------------------------
    | Volume targets
    |--------------------------------------------------------------------
    | A place is "satisfied" once it has >= min images. Discovery stops as
    | soon as max is reached. min is also the backfill/retry threshold:
    | places below it get re-queued, places at or above it are never
    | re-scanned (no wasted API requests).
    */
    'min_per_place' => (int) env('PLACE_IMAGES_MIN', 3),
    'max_per_place' => (int) env('PLACE_IMAGES_MAX', 10),

    // Wikimedia thumbnail width we store as the display URL. Never the
    // original file — those can be tens of MB.
    'thumb_width' => (int) env('PLACE_IMAGES_THUMB_WIDTH', 1200),

    // Candidate scoring floor. Below this a photo is dropped, even if it
    // came from a "trusted" strategy — see PlaceImageMatcher.
    'min_score' => (float) env('PLACE_IMAGES_MIN_SCORE', 0.45),

    // Hard reject: Commons hosts maps, diagrams, tiny crops and logos too.
    'min_width' => 400,
    'min_height' => 300,

    /*
    |--------------------------------------------------------------------
    | Wikimedia HTTP
    |--------------------------------------------------------------------
    */
    'user_agent' => env(
        'WIKIMEDIA_USER_AGENT',
        'Oripori/1.0 (https://oripori.com; contact@oripori.com) Laravel image-discovery'
    ),

    'timeout' => (int) env('WIKIMEDIA_TIMEOUT', 20),

    // Global ceiling shared by every worker/job via Redis. 60/min is well
    // inside Wikimedia's anonymous guidance while still finishing a 20-place
    // test run in well under a minute.
    'rate_limit' => [
        'max' => (int) env('WIKIMEDIA_RATE_MAX', 60),
        'seconds' => (int) env('WIKIMEDIA_RATE_SECONDS', 60),
    ],

    // Raw response cache (query hash -> payload). Purely a traffic saver:
    // redis is a cache here, never the record of what we stored.
    'cache_ttl' => (int) env('WIKIMEDIA_CACHE_TTL', 86400),

    // After this many consecutive transport failures the circuit opens and
    // jobs release themselves instead of burning retry attempts.
    'circuit_breaker' => [
        'threshold' => 5,
        'cooldown' => 600,
    ],

    'endpoints' => [
        'commons' => 'https://commons.wikimedia.org/w/api.php',
        'wikidata' => 'https://www.wikidata.org/w/api.php',
        'wikipedia' => 'https://en.wikipedia.org/w/api.php',
        'wikipedia_ne' => 'https://ne.wikipedia.org/w/api.php',
    ],

    /*
    |--------------------------------------------------------------------
    | Discovery strategies, in priority order
    |--------------------------------------------------------------------
    | Each entry is a strategy name handled by PlaceImageDiscoveryService.
    | Discovery walks down the list until min_per_place is satisfied (or
    | max_per_place reached). Stronger identifiers first: an OSM-sourced
    | wikidata id beats any text search.
    */
    'strategies' => [
        'wikidata_id',
        'commons_category',
        'wikidata_link',
        'geosearch',
        'text_search',
    ],

    // Geosearch radius per strategy step (km).
    'geo_radius_km' => [
        // Defaults keyed by place category family; fall back to 'default'.
        'default' => 10,
        'nature' => 25,
        'trekking' => 50,
    ],

    // A photo whose own EXIF/GPS is farther away than this from the place is
    // treated as a wrong match (unless the place is a large natural feature).
    'max_photo_distance_km' => [
        'default' => 15,
        'nature' => 60,
    ],

    /*
    |--------------------------------------------------------------------
    | Licence allow-list
    |--------------------------------------------------------------------
    | Anything not matching an allow rule, or matching a deny rule, is
    | rejected with status=rejected. Non-free / fair-use / NC / ND are out.
    */
    'licenses' => [
        'allow' => [
            '/\bcc[\s-]?0\b/i',
            '/\bcc[\s-]?zero\b/i',
            '/\bpublic\s*domain\b/i',
            '/\bpd[-\s]/i',
            '/\bcc[\s-]?by[\s-]?sa\b/i',
            '/\bcc[\s-]?by\b(?![-\s]?(nc|nd))/i',
            '/\bcc[\s-]?by[\s-]?sa[\s-]?(3|4)\.\d\b/i',
        ],
        'deny' => [
            '/\bnc\b/i',
            '/\bnd\b/i',
            '/non[-\s]?free/i',
            '/fair[-\s]?use/i',
            '/permission\s*required/i',
            '/copyrighted/i',
            '/non[-\s]?commercial/i',
            '/no[-\s]?derivative/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------
    | Wrong-match guards
    |--------------------------------------------------------------------
    | Title/description tokens that usually mean "this is a generic or
    | cartographic image, not a photo of this place".
    */
    'reject_title_keywords' => [
        'locator map', 'location map', 'relief map', 'elevation', 'topographic',
        'satellite image', 'google earth', 'openstreetmap', 'osm logo',
        'coat of arms', 'flag of', 'stamp of', 'banknote', 'currency',
        'logo', 'wordmark', 'diagram', 'chart', 'screenshot', 'qr code',
        'blank map', 'seal of', 'emblem', 'placeholder',
    ],

    // Case-normalised place-name tokens shorter than this are ignored when
    // scoring (they match everything otherwise).
    'name_token_min_length' => 3,

    // Minimum share of the place's significant name tokens a candidate title
    // must contain before it counts as a name match.
    'name_match_ratio' => 0.5,

    /*
    |--------------------------------------------------------------------
    | Retry / backoff policy (per place)
    |--------------------------------------------------------------------
    */
    'job' => [
        'tries' => 4,
        'timeout' => 80,          // must stay < queue.connections.database.retry_after (90s)
        'backoff' => [60, 300, 1800],
        // How long a place that genuinely has no Commons coverage waits
        // before we are willing to look again (files do get uploaded later).
        'negative_ttl_days' => 7,
        // Hard ceiling on attempts for one place, regardless of job retries.
        'max_attempts' => 6,
    ],

    // Places with these sources are eligible for discovery.
    'eligible_sources' => ['seed', 'osm', 'admin', 'user_submitted'],

    // OSM tags captured during import and used as discovery signals.
    'osm_tags' => ['wikidata', 'wikipedia', 'wikimedia_commons'],
];
