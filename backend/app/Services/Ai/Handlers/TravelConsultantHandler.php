<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Services\ItineraryBuilder;
use Illuminate\Support\Facades\Log;

class TravelConsultantHandler extends BaseHandler
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

        if ($action === 'itinerary' && isset($input['district'])) {
            return $this->buildItinerary($task, $input['district']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $builder = app(ItineraryBuilder::class);
        $count = \App\Models\Place::active()->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $districts = $builder->topDistricts()->count();
        $msg = "{$count} active places across {$districts} districts ready for itinerary planning";
        return $this->markComplete($task, ['places' => $count, 'districts' => $districts, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results) . ' itinerary(ies) generated (rules-based)';
        return $this->markComplete($task, ['itineraries' => count($results), 'items' => $results, 'message' => $msg]);
    }

    protected function autoWork(): array
    {
        $builder = app(ItineraryBuilder::class);
        $results = [];

        foreach ($builder->topDistricts() as $row) {
            try {
                $itinerary = $builder->buildForDistrict($row->district);
                $results[] = [
                    'district' => $row->district,
                    'days' => $itinerary,
                    'places_used' => $row->places_count,
                ];
            } catch (\Exception $e) {
                Log::error("Travel consultant failed for {$row->district}: " . $e->getMessage());
            }
        }

        return $results;
    }

    protected function buildItinerary(AiAgentTask $task, string $district): AiAgentTask
    {
        $itinerary = app(ItineraryBuilder::class)->buildForDistrict($district);
        if (empty($itinerary)) {
            return $this->markFailed($task, "No places found for district: {$district}");
        }
        return $this->markComplete($task, ['district' => $district, 'itinerary' => $itinerary]);
    }
}
