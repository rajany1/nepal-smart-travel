<?php

namespace App\Services;

/**
 * Lightweight, deterministic report auto-classification.
 *
 * With the mobile app now collecting ONLY a free-text description + a live
 * photo, the system must decide the missing metadata itself. This service
 * derives, from the description text:
 *
 *   - category_id  : best match against the 8 seeded report_categories
 *   - priority     : critical / high / medium / low based on severity keywords
 *   - title        : a concise, human-readable headline from the first sentence
 *
 * This is intentionally rule-based (no AI / network / rate-limit dependency)
 * so report submission stays instant and reliable. Words are matched
 * case-insensitively against a small glossary.
 */
class ReportAutoClassifyService
{
    /** report_categories seed order (see DatabaseSeeder). */
    private const CATEGORY_IDS = [
        'General' => 1,
        'Road & Traffic' => 2,
        'Safety & Hazards' => 3,
        'Weather & Conditions' => 4,
        'Transportation' => 5,
        'Hidden Destinations' => 6,
        'Services & Utilities' => 7,
        'Events & Notices' => 8,
    ];

    private const CATEGORY_KEYWORDS = [
        'Road & Traffic' => [
            'road', 'highway', 'traffic', 'jam', 'pothole', 'bridge', 'lane',
            'zebra', 'culvert', 'speed bump', 'raasta', 'sadak', 'petrol',
        ],
        'Safety & Hazards' => [
            'landslide', 'flood', 'fire', 'accident', 'crime', 'theft',
            'robbery', 'violence', 'unsafe', 'hazard', 'slippery', 'earthquake',
            'storm', 'sinkhole', 'snake', 'electrical', 'loot', 'safe', 'risk',
            'danger', 'khadghar', 'pahiro', 'aaago', 'durgathana',
        ],
        'Weather & Conditions' => [
            'rain', 'storm', 'wind', 'fog', 'snow', 'hail', 'weather', 'drizzle',
            'temperature', 'sunny', 'cloud', 'rhimjha', 'pani',
        ],
        'Transportation' => [
            'bus', 'taxi', 'vehicle', 'motorcycle', 'transport', 'parking',
            'route', 'fare', 'micro', 'truck', 'cycle', 'yas', 'car',
        ],
        'Hidden Destinations' => [
            'hidden', 'destination', 'unexplored', 'scenic', 'viewpoint',
            'trek', 'trail', 'secret', 'offbeat', 'landmark', 'beautiful',
        ],
        'Services & Utilities' => [
            'electricity', 'power', 'water', 'sewage', 'waste', 'garbage',
            'telecom', 'internet', 'plumbing', 'supply', 'outage', 'khol',
            'bijuli', 'paani',
        ],
        'Events & Notices' => [
            'event', 'festival', 'jatra', 'mela', 'notice', 'meeting', 'gathering',
            'ceremony', 'strike', 'bandh', 'protest', 'announcement', 'free',
        ],
    ];

    /** Words that push a report to critical / high priority. */
    private const CRITICAL_WORDS = [
        'fire', 'landslide', 'flood', 'earthquake', 'accident', 'collapse',
        'explosion', 'gas leak', 'emergency', 'drowning', 'burning', 'injured',
        'death', 'dead', 'aaago', 'pahiro',
    ];

    private const HIGH_WORDS = [
        'robbery', 'theft', 'crime', 'storm', 'outage', 'blocked', 'bandh',
        'protest', 'disease', 'health', 'injured', 'broken', 'leak', 'scam',
        'slippery', 'pothole', 'suspicious',
    ];

    /**
     * Infer { title, category_id, category_name, priority } from a description.
     *
     * @param  string $description
     * @return array{title: string, category_id: int, category_name: string, priority: string}
     */
    public function classify(string $description): array
    {
        $title = $this->makeTitle($description);
        $categoryName = $this->pickCategory($description);
        $categoryId = self::CATEGORY_IDS[$categoryName] ?? self::CATEGORY_IDS['General'];
        $priority = $this->pickPriority($description);

        return [
            'title' => $title,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'priority' => $priority,
        ];
    }

    /** A concise headline: first sentence, title-cased, capped ~90 chars. */
    public function makeTitle(string $description): string
    {
        $text = trim($description);

        // Cut at the first sentence terminator if present.
        if (preg_match('/^([^.!?]+)[.!?]/u', $text, $m)) {
            $text = trim($m[1]);
        }

        // Collapse whitespace / newlines and cap length.
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = mb_substr($text, 0, 90);

        if ($text === '') {
            return 'Community report';
        }

        // Nice title-case (first letter of each word uppercase, rest lower).
        $titleCased = mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');

        return $titleCased;
    }

    /** Best matching report category name for a description. */
    public function pickCategory(string $description): string
    {
        $text = mb_strtolower($description, 'UTF-8');
        $best = 'General';
        $bestScore = 0;

        foreach (self::CATEGORY_KEYWORDS as $catName => $keywords) {
            $score = 0;
            foreach ($keywords as $kw) {
                if (mb_stripos($text, $kw, 0, 'UTF-8') !== false) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $catName;
            }
        }

        return $best;
    }

    /** Priority inferred from severity keywords (critical > high > medium). */
    public function pickPriority(string $description): string
    {
        $text = mb_strtolower($description, 'UTF-8');

        foreach (self::CRITICAL_WORDS as $kw) {
            if (mb_stripos($text, $kw, 0, 'UTF-8') !== false) {
                return 'critical';
            }
        }

        foreach (self::HIGH_WORDS as $kw) {
            if (mb_stripos($text, $kw, 0, 'UTF-8') !== false) {
                return 'high';
            }
        }

        return 'medium';
    }
}