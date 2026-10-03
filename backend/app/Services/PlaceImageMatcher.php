<?php

namespace App\Services;

use App\Models\Place;

/**
 * Decides whether a discovered Commons file is really a photo of *this*
 * place, and how confident we are.
 *
 * Hard gates (licence, resolution, junk keywords) run first and can never be
 * outvoted by a high score. After that a weighted score combines:
 *
 *   strategy weight  — how directly the source identified the entity
 *   name similarity  — significant tokens of the place name in the file's
 *                      title / ObjectName / description / categories
 *   geographic fit   — distance between the photo's own GPS and the place
 *   local context    — district appearing near the file
 *
 * Anything under config('images.min_score') is dropped: a wrong photo is
 * worse than no photo.
 */
class PlaceImageMatcher
{
    /** Per-strategy confidence before any content is inspected. */
    private const STRATEGY_WEIGHT = [
        'wikidata_id' => 1.0,
        'commons_category' => 0.9,
        'wikidata_link' => 0.85,
        'wikipedia' => 0.8,
        'geosearch' => 0.7,
        'text_search' => 0.6,
    ];

    /** Strategies where an empty name match is not suspicious by itself. */
    private const ENTITY_LINKED = ['wikidata_id', 'commons_category', 'wikidata_link', 'wikipedia'];

    private const ALLOWED_MIME = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/tiff', 'image/tif'];

    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'near', 'into',
        'de', 'la', 'el', 'los', 'las', 'der', 'die', 'das',
        'को', 'मा', 'र', 'का', 'के',
    ];

    /**
     * @param array $candidate normalised candidate (see PlaceImageDiscoveryService)
     * @return array{accepted: bool, score: float, reasons: array<string>}
     */
    public function evaluate(Place $place, array $candidate): array
    {
        $reasons = [];

        if (!$this->licenseAllowed($candidate['license'] ?? null)) {
            return $this->reject('licence-not-allowed: ' . ($candidate['license'] ?? 'unknown'));
        }

        $width = (int) ($candidate['width'] ?? 0);
        $height = (int) ($candidate['height'] ?? 0);
        if ($width < (int) config('images.min_width', 400) || $height < (int) config('images.min_height', 300)) {
            return $this->reject("too-small: {$width}x{$height}");
        }

        $mime = strtolower((string) ($candidate['mime'] ?? 'image/jpeg'));
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return $this->reject("mime-not-allowed: {$mime}");
        }

        if ($this->titleLooksLikeJunk($candidate['title'] ?? '')) {
            return $this->reject('junk-keyword-in-title');
        }

        $via = (string) ($candidate['via'] ?? 'text_search');

        // An entity identifier we asked for directly settles the question.
        $candidateWikidata = (string) ($candidate['wikidata_id'] ?? '');
        if ($candidateWikidata !== '' && strcasecmp($candidateWikidata, (string) $place->wikidata_id) === 0) {
            return $this->accept(0.99, 'wikidata-match');
        }

        $haystack = $this->haystack($candidate);
        $nameScore = $this->nameScore($place->name, $haystack);
        $distanceKm = $this->distanceToPhoto($place, $candidate);
        $geoScore = $this->geoScore($place, $distanceKm, $via);
        $contextScore = $this->contextScore($place, $haystack);

        // --- strategy-specific must-holds ----------------------------------
        if ($via === 'text_search' && $nameScore < (float) config('images.name_match_ratio', 0.5)) {
            return $this->reject('name-mismatch-on-text-search: ' . round($nameScore, 2));
        }

        if ($distanceKm !== null && $distanceKm > $this->maxPhotoDistance($place)) {
            return $this->reject('photo-too-far: ' . round($distanceKm, 1) . 'km');
        }

        $signalPresent = $nameScore > 0.0 || $distanceKm !== null || in_array($via, self::ENTITY_LINKED, true);
        if (!$signalPresent) {
            return $this->reject('no-name-no-gps-no-entity-link');
        }

        $score = $this->clamp(
            ($this->strategyWeight($via) * 0.30)
            + ($nameScore * 0.35)
            + ($geoScore * 0.25)
            + ($contextScore * 0.10)
        );

        $min = (float) config('images.min_score', 0.45);
        if ($score < $min) {
            return $this->reject('low-score: ' . round($score, 3) . " < {$min}");
        }

        return $this->accept($score, 'ok');
    }

    /**
     * Licence allow-list. Deny rules win; then at least one allow rule must
     * hit. Unknown/missing licence is always rejected — without it we cannot
     * attribute, and attribution is the price of using the photo.
     */
    public function licenseAllowed(?string $license): bool
    {
        $license = trim((string) $license);
        if ($license === '') {
            return false;
        }

        foreach ((array) config('images.licenses.deny', []) as $pattern) {
            if (preg_match($pattern, $license)) {
                return false;
            }
        }

        foreach ((array) config('images.licenses.allow', []) as $pattern) {
            if (preg_match($pattern, $license)) {
                return true;
            }
        }

        return false;
    }

    private function strategyWeight(string $via): float
    {
        return self::STRATEGY_WEIGHT[$via] ?? 0.5;
    }

    private function haystack(array $candidate): string
    {
        $parts = [
            $candidate['title'] ?? '',
            $candidate['object_name'] ?? '',
            $candidate['description'] ?? '',
            is_array($candidate['categories'] ?? null) ? implode(' ', $candidate['categories']) : '',
            $candidate['source_page_url'] ?? '',
        ];

        return mb_strtolower($this->stripHtml(implode(' ', $parts)));
    }

    /** Fraction of the place's significant name tokens found in $haystack. */
    public function nameScore(?string $placeName, string $haystack): float
    {
        $tokens = $this->significantTokens($placeName);
        if ($tokens === []) {
            return 0.0;
        }

        $found = 0;
        foreach ($tokens as $token) {
            if (mb_strpos($haystack, $token) !== false) {
                $found++;
            }
        }

        return $found / count($tokens);
    }

    /** @return string[] lowercased, de-punctuated, stopword-free tokens */
    public function significantTokens(?string $name): array
    {
        $min = (int) config('images.name_token_min_length', 3);
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim((string) $name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < $min || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $tokens[] = $word;
        }

        return array_values(array_unique($tokens));
    }

    private function geoScore(Place $place, ?float $distanceKm, string $via): float
    {
        if ($distanceKm === null) {
            // No GPS on the file. Entity-linked and category strategies are
            // still trustworthy; geosearch is bounded by its own radius.
            return in_array($via, self::ENTITY_LINKED, true) ? 1.0 : 0.85;
        }

        $max = $this->maxPhotoDistance($place);
        if ($max <= 0) {
            return 0.0;
        }
        if ($distanceKm <= 1.0) {
            return 1.0;
        }

        return $this->clamp(1.0 - (($distanceKm - 1.0) / max($max - 1.0, 0.1)));
    }

    private function contextScore(Place $place, string $haystack): float
    {
        $district = mb_strtolower(trim((string) $place->district));
        if ($district !== '' && mb_strlen($district) >= 3 && mb_strpos($haystack, $district) !== false) {
            return 1.0;
        }

        return 0.3;
    }

    private function maxPhotoDistance(Place $place): float
    {
        $group = $this->distanceGroup($place);

        return (float) config("images.max_photo_distance_km.{$group}", config('images.max_photo_distance_km.default', 15));
    }

    private function distanceGroup(Place $place): string
    {
        $category = mb_strtolower((string) ($place->category?->name ?? ''));
        if (in_array($category, ['nature', 'viewpoint', 'trekking', 'peak'], true)) {
            return 'nature';
        }

        return 'default';
    }

    /** Haversine km between place and the photo's own GPS, or null. */
    public function distanceToPhoto(Place $place, array $candidate): ?float
    {
        $lat = isset($candidate['photo_lat']) && $candidate['photo_lat'] !== null ? (float) $candidate['photo_lat'] : null;
        $lng = isset($candidate['photo_lng']) && $candidate['photo_lng'] !== null ? (float) $candidate['photo_lng'] : null;
        if ($lat === null || $lng === null) {
            return null;
        }

        return self::haversineKm((float) $place->latitude, (float) $place->longitude, $lat, $lng);
    }

    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function titleLooksLikeJunk(string $title): bool
    {
        $title = mb_strtolower($this->stripHtml($title));
        foreach ((array) config('images.reject_title_keywords', []) as $keyword) {
            if (str_contains($title, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    private function stripHtml(string $value): string
    {
        $stripped = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace('/\s+/u', ' ', $stripped);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    private function accept(float $score, string $reason): array
    {
        return ['accepted' => true, 'score' => round($score, 3), 'reasons' => [$reason]];
    }

    private function reject(string $reason): array
    {
        return ['accepted' => false, 'score' => 0.0, 'reasons' => [$reason]];
    }
}
