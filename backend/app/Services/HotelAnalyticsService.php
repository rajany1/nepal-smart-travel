<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlaceReview;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic hotel performance analysis (no LLM). Computes review
 * metrics from the database and derives insights from fixed thresholds.
 */
class HotelAnalyticsService
{
    public function analyze(Place $hotel): array
    {
        $stats = $this->metrics($hotel);

        $insights = $this->deriveInsights($hotel, $stats);

        return [
            'place_id' => $hotel->id,
            'hotel' => $hotel->name,
            'district' => $hotel->district,
            'average_rating' => $hotel->average_rating,
            'total_reviews' => $hotel->total_reviews,
            'reviews_30d' => $stats['reviews_30d'],
            'negative_90d' => $stats['negative_90d'],
            'rating_trend' => $stats['trend'],
            'district_average' => $stats['district_average'],
            'guest_sentiment' => $this->sentiment($hotel, $stats),
            'needs_attention' => $stats['negative_90d'] > 0 || (float) $hotel->average_rating < 3.5 || $stats['trend'] < -0.2,
            'strengths' => $insights['strengths'],
            'improvements' => $insights['improvements'],
            'summary' => $this->summary($hotel, $stats, $insights),
        ];
    }

    protected function metrics(Place $hotel): array
    {
        $reviews30 = PlaceReview::where('place_id', $hotel->id)
            ->where('created_at', '>=', now()->subDays(30));

        $recentAvg = (float) (clone $reviews30)->avg('rating') ?? 0;

        $older = PlaceReview::where('place_id', $hotel->id)
            ->whereBetween('created_at', [now()->subDays(90), now()->subDays(30)])
            ->avg('rating');
        $olderAvg = (float) $older;

        $trend = round($recentAvg - $olderAvg, 2);

        $districtAvg = (float) Place::where('district', $hotel->district)
            ->where('id', '!=', $hotel->id)
            ->whereNotNull('average_rating')
            ->whereHas('category', function ($q) {
                $q->whereRaw("LOWER(name) LIKE '%hotel%' OR LOWER(name) LIKE '%lodge%' OR LOWER(name) LIKE '%resort%' OR LOWER(name) LIKE '%guesthouse%'");
            })
            ->avg('average_rating');

        return [
            'reviews_30d' => (int) (clone $reviews30)->count(),
            'recent_avg' => $recentAvg,
            'older_avg' => $olderAvg,
            'trend' => $trend,
            'negative_90d' => (int) PlaceReview::where('place_id', $hotel->id)
                ->where('rating', '<=', 2)
                ->where('created_at', '>=', now()->subDays(90))
                ->count(),
            'district_average' => round($districtAvg, 2),
        ];
    }

    protected function deriveInsights(Place $hotel, array $stats): array
    {
        $strengths = [];
        $improvements = [];

        if ((float) $hotel->average_rating >= 4.0) {
            $strengths[] = "strong overall rating of {$hotel->average_rating}";
        }
        if ($stats['reviews_30d'] > 0 && $stats['recent_avg'] >= 4.0) {
            $strengths[] = 'recent guests are rating stays highly';
        }
        if ($stats['negative_90d'] === 0 && (float) $hotel->average_rating >= 3.5) {
            $strengths[] = 'no negative reviews in the last 90 days';
        }

        if ((float) $hotel->average_rating < 3.5) {
            $improvements[] = 'overall rating is below 3.5 — investigate core service issues';
        }
        if ($stats['negative_90d'] > 0) {
            $improvements[] = "{$stats['negative_90d']} negative review(s) in the last 90 days — respond and fix recurring complaints";
        }
        if ($stats['trend'] < -0.2) {
            $improvements[] = 'recent rating trend is declining — follow up with recent guests';
        }
        if ($stats['district_average'] > 0 && (float) $hotel->average_rating < $stats['district_average']) {
            $improvements[] = 'rating is below the district average of ' . $stats['district_average'];
        }

        return [
            'strengths' => $strengths ?: ['no standout strengths detected'],
            'improvements' => $improvements ?: ['keep current performance steady'],
        ];
    }

    protected function sentiment(Place $hotel, array $stats): string
    {
        if ($stats['trend'] >= 0.2) return 'improving';
        if ($stats['trend'] <= -0.2) return 'declining';
        if ((float) $hotel->average_rating >= 4.0) return 'positive';
        if ((float) $hotel->average_rating < 3.0) return 'negative';
        return 'mixed';
    }

    protected function summary(Place $hotel, array $stats, array $insights): string
    {
        $needsAttention = $stats['negative_90d'] > 0 || (float) $hotel->average_rating < 3.5 || $stats['trend'] < -0.2;
        $part = "{$hotel->name} ({$hotel->district}) carries a {$hotel->average_rating}-star average from {$hotel->total_reviews} review(s).";

        if ($needsAttention) {
            return $part . ' Performance needs attention: ' . implode('; ', $insights['improvements']) . '.';
        }

        return $part . ' Performance looks steady: ' . implode('; ', $insights['strengths']) . '.';
    }
}
