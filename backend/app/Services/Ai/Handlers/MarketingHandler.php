<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Place;
use App\Services\MarketingTemplateEngine;

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
        $msg = count($results['posts']) . ' marketing post(s) generated (templates)';
        return $this->markComplete($task, $results);
    }

    protected function autoWork(): array
    {
        $engine = app(MarketingTemplateEngine::class);

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

        $posts = [];

        $posts[] = $engine->weeklyDigest($candidates);

        foreach ($candidates->take(5) as $place) {
            $posts[] = $engine->placePromo($place);
        }

        return ['posts' => $posts, 'message' => count($posts) . ' marketing post(s) generated'];
    }

    protected function singleCampaign(AiAgentTask $task, int $placeId): AiAgentTask
    {
        $place = Place::active()->find($placeId);
        if (!$place) {
            return $this->markFailed($task, "Place #{$placeId} not found");
        }

        return $this->markComplete($task, [
            'place_id' => $placeId,
            'place' => $place->name,
            'campaign' => app(MarketingTemplateEngine::class)->singleCampaign($place),
        ]);
    }
}
