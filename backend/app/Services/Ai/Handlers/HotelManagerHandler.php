<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Place;
use App\Models\PlaceReview;
use Illuminate\Support\Facades\Log;

class HotelManagerHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'auto';

        if ($action === 'assess') {
            return $this->assess($task);
        }

        if (in_array($action, ['auto', 'auto-work'])) {
            return $this->handleAutoWork($task);
        }

        if ($action === 'analyze' && isset($input['place_id'])) {
            return $this->analyzeHotel($task, (int) $input['place_id']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $count = $this->hotelsQuery()->count();
        $msg = "{$count} hotel/lodge/resort place(s) available for performance analysis";
        return $this->markComplete($task, ['hotels' => $count, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results) . ' hotel performance report(s) generated';
        return $this->markComplete($task, ['analyzed' => count($results), 'items' => $results, 'message' => $msg]);
    }    protected function autoWork(): array
    {
        $results = [];

        $hotels = $this->hotelsQuery()
            ->orderByDesc('total_reviews')
            ->take(5)
            ->get();

        foreach ($hotels as $hotel) {
            $report = $this->analyzeHotelData($hotel);
            $results[] = $report;
        }

        return $results;
    }

    protected function analyzeHotel(AiAgentTask $task, int $placeId): AiAgentTask
    {
        $hotel = $this->hotelsQuery()->find($placeId);
        if (!$hotel) {
            return $this->markFailed($task, "Hotel place #{$placeId} not found");
        }
        return $this->markComplete($task, $this->analyzeHotelData($hotel));
    }

    protected function hotelsQuery()
    {
        return Place::active()->whereHas('category', function ($q) {
            $q->where(function ($q2) {
                $q2->whereRaw("LOWER(name) LIKE '%hotel%'")
                    ->orWhereRaw("LOWER(name) LIKE '%lodge%'")
                    ->orWhereRaw("LOWER(name) LIKE '%resort%'")
                    ->orWhereRaw("LOWER(name) LIKE '%guesthouse%'");
            });
        });
    }

    protected function analyzeHotelData(Place $hotel): array
    {
        $reviews = PlaceReview::where('place_id', $hotel->id)
            ->latest()
            ->take(10)
            ->get(['rating', 'title', 'description', 'created_at']);

        $recent = PlaceReview::where('place_id', $hotel->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $negative = PlaceReview::where('place_id', $hotel->id)
            ->where('rating', '<=', 2)
            ->where('created_at', '>=', now()->subDays(90))
            ->count();

        $llm = $this->ai();
        $reviewJson = $reviews->map(fn($r) => [
            'rating' => $r->rating,
            'text' => trim(($r->title ? $r->title . ' — ' : '') . ($r->description ?? '')),
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        $analysis = ['needs_attention' => $negative > 0 || ($hotel->average_rating ?? 0) < 3.5];
        try {
            $result = $llm->generateJson(
                "You are a hotel performance analyst. Analyze this Nepal hotel.\nName: {$hotel->name}\nDistrict: {$hotel->district}\nAvg rating: {$hotel->average_rating} ({$hotel->total_reviews} reviews)\nRecent reviews (30d): {$recent}\n\nRecent reviews:\n{$reviewJson}\n\nReturn JSON: {\"summary\": \"string\", \"strengths\": [\"string\"], \"improvements\": [\"string\"], \"guest_sentiment\": \"positive|mixed|negative\"}"
            );
            $analysis['summary'] = $result['summary'] ?? '';
            $analysis['strengths'] = $result['strengths'] ?? [];
            $analysis['improvements'] = $result['improvements'] ?? [];
            $analysis['guest_sentiment'] = $result['guest_sentiment'] ?? 'mixed';
        } catch (\Exception $e) {
            Log::warning("Hotel manager LLM failed for #{$hotel->id}: " . $e->getMessage());
            $analysis['summary'] = 'AI analysis temporarily unavailable.';
            $analysis['strengths'] = [];
            $analysis['improvements'] = [];
            $analysis['guest_sentiment'] = $negative > 0 ? 'mixed' : 'positive';
        }

        return [
            'place_id' => $hotel->id,
            'hotel' => $hotel->name,
            'district' => $hotel->district,
            'average_rating' => $hotel->average_rating,
            'total_reviews' => $hotel->total_reviews,
            'reviews_30d' => $recent,
            'negative_90d' => $negative,
        ] + $analysis;
    }
}
