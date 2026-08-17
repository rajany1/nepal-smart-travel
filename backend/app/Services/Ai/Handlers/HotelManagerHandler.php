<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Place;
use App\Services\HotelAnalyticsService;

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
        $msg = count($results) . ' hotel performance report(s) generated (rules-based)';
        return $this->markComplete($task, ['analyzed' => count($results), 'items' => $results, 'message' => $msg]);
    }

    protected function autoWork(): array
    {
        $results = [];

        $hotels = $this->hotelsQuery()
            ->orderByDesc('total_reviews')
            ->take(10)
            ->get();

        foreach ($hotels as $hotel) {
            $results[] = app(HotelAnalyticsService::class)->analyze($hotel);
        }

        return $results;
    }

    protected function analyzeHotel(AiAgentTask $task, int $placeId): AiAgentTask
    {
        $hotel = $this->hotelsQuery()->find($placeId);
        if (!$hotel) {
            return $this->markFailed($task, "Hotel place #{$placeId} not found");
        }
        return $this->markComplete($task, app(HotelAnalyticsService::class)->analyze($hotel));
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
}
