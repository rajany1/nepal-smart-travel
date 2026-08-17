<?php

namespace App\Services;

use App\Models\Place;
use Illuminate\Support\Collection;

/**
 * Deterministic marketing copy engine (no LLM). Template posts are assembled
 * from real place data with a stable rotation key so the same place always
 * produces the same campaign.
 */
class MarketingTemplateEngine
{
    public function weeklyDigest(Collection $places): array
    {
        $top = $places->sortByDesc('average_rating')->first();

        $names = $places->take(3)->pluck('name')->values()->implode(', ');
        $district = $top?->district ?: 'नेपाल';

        $text = "घुम्न जाने समय भयो! {$district} का यी उत्कृष्ट स्थानहरूको भ्रमण गर्नुहोस्: {$names}।"
            . ($top && $top->average_rating > 0
                ? " {$top->name} ले {$top->average_rating} रेटिङ पाएको छ — हाम्रा प्रयोगकर्ताहरूको पहिलो रोजाइ।"
                : '');

        return [
            'type' => 'weekly_digest',
            'language' => 'ne',
            'text' => $text,
            'hashtags' => ['#NepalTravel', '#Nepal', '#GhumaNepal', '#VisitNepal'],
        ];
    }

    public function placePromo(Place $place): array
    {
        $rating = $place->average_rating > 0 ? "rated {$place->average_rating} stars" : 'loved by visitors';
        $district = $place->district ? " in {$place->district}" : ' in Nepal';

        return [
            'type' => 'place_promo',
            'language' => 'en',
            'place_id' => $place->id,
            'place' => $place->name,
            'tagline' => "Discover {$place->name}{$district} — {$rating}.",
        ];
    }

    public function singleCampaign(Place $place): array
    {
        $rating = $place->average_rating > 0 ? (float) $place->average_rating : null;
        $district = $place->district ?: 'Nepal';
        $category = $place->category?->name ?? 'destination';

        $nepali = "{$place->name} — {$district}को एक उत्कृष्ट {$category}।"
            . ($rating !== null
                ? " यसले {$rating} रेटिङ पाएको छ र आगन्तुकहरूको रोजाइमा पर्ने गर्छ।"
                : '')
            . ' घुम्न जाने योजना बनाउनुहोस्!';

        $english = $rating !== null
            ? "{$place->name} in {$district} — rated {$rating} by visitors. Add it to your travel plan!"
            : "{$place->name} in {$district} — a must-visit {$category}. Plan your trip!";

        return [
            'nepali_text' => $nepali,
            'english_text' => $english,
            'target_audience' => "Travelers planning a trip to {$district}",
            'suggested_channels' => ['Social Media', 'In-App Banner', 'Home Screen Feature'],
        ];
    }

    /** Stable rotation key so campaigns repeat predictably per place. */
    public function rotationKey(int $placeId): int
    {
        return crc32((string) $placeId) % 4;
    }
}
