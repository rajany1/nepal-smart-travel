<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Place;
use Illuminate\Support\Facades\Log;

class MarketingHandler extends BaseHandler
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

        if ($action === 'campaign' && isset($input['place_id'])) {
            return $this->singleCampaign($task, (int) $input['place_id']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $featured = Place::active()->featured()->count();
        $top = Place::active()->whereNotNull('average_rating')->where('average_rating', '>=', 4)->count();
        $msg = "{$featured} featured and {$top} top-rated place(s) available for marketing copy";
        return $this->markComplete($task, ['featured' => $featured, 'top_rated' => $top, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results['posts']) . ' marketing post(s) generated';
        return $this->markComplete($task, $results);
    }

    protected function autoWork(): array
    {
        $featured = Place::active()
            ->featured()
            ->orderByDesc('average_rating')
            ->take(5)
            ->get(['id', 'name', 'district', 'category_id', 'average_rating', 'description']);

        $topRated = Place::active()
            ->whereNotNull('average_rating')
            ->orderByDesc('average_rating')
            ->take(5)
            ->get(['id', 'name', 'district', 'category_id', 'average_rating']);

        $candidates = $featured->count() >= 3 ? $featured : $topRated;
        if ($candidates->isEmpty()) {
            return ['posts' => [], 'message' => 'No places available for marketing copy'];
        }

        $payload = $candidates->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'district' => $p->district,
            'category' => $p->category?->name ?? 'attraction',
            'rating' => $p->average_rating ?? 'n/a',
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        $posts = [];
        try {
            $llm = $this->ai();

            $nepali = $llm->generateJson(
                "You are a Nepal travel marketing writer. Create a short social-media post (Nepali, max 3 sentences) promoting the BEST of Nepal using these places.\nPlaces: {$payload}\n\nReturn JSON: {\"text\": \"string\", \"hashtags\": [\"string\"]}"
            );
            $posts[] = [
                'type' => 'weekly_digest',
                'language' => 'ne',
                'text' => $nepali['text'] ?? '',
                'hashtags' => $nepali['hashtags'] ?? [],
            ];

            $english = $llm->generateJson(
                "You are a Nepal travel marketing writer. Write a catchy one-liner promo (English) for each of these places.\nPlaces: {$payload}\n\nReturn JSON: [{\"place_id\": id, \"name\": \"string\", \"tagline\": \"string\"}]"
            );
            foreach ((array) ($english ?? []) as $item) {
                $posts[] = [
                    'type' => 'place_promo',
                    'language' => 'en',
                    'place_id' => $item['place_id'] ?? null,
                    'place' => $item['name'] ?? '',
                    'tagline' => $item['tagline'] ?? '',
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Marketing handler LLM failed: " . $e->getMessage());
            foreach ($candidates as $p) {
                $posts[] = [
                    'type' => 'place_promo',
                    'language' => 'en',
                    'place_id' => $p->id,
                    'place' => $p->name,
                    'tagline' => 'Discover ' . $p->name . ' in ' . ($p->district ?? 'Nepal') . ' — rated ' . ($p->average_rating ?? 'n/a') . ' stars.',
                ];
            }
        }

        return ['posts' => $posts, 'message' => count($posts) . ' marketing post(s) generated'];
    }

    protected function singleCampaign(AiAgentTask $task, int $placeId): AiAgentTask
    {
        $place = Place::active()->find($placeId);
        if (!$place) {
            return $this->markFailed($task, "Place #{$placeId} not found");
        }

        try {
            $llm = $this->ai();
            $result = $llm->generateJson(
                "Write a promotional campaign for this Nepal travel place.\nName: {$place->name}\nDistrict: {$place->district}\nCategory: " . ($place->category?->name ?? 'attraction') . "\nRating: {$place->average_rating}\n\nReturn JSON: {\"nepali_text\": \"string\", \"english_text\": \"string\", \"target_audience\": \"string\", \"suggested_channels\": [\"string\"]}"
            );

            return $this->markComplete($task, [
                'place_id' => $placeId,
                'place' => $place->name,
                'campaign' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->markFailed($task, $e->getMessage());
        }
    }
}
