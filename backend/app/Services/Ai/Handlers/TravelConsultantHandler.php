<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Place;
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
        $count = Place::active()->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $districts = $this->topDistricts()->count();
        $msg = "{$count} active places across {$districts} districts ready for itinerary planning";
        return $this->markComplete($task, ['places' => $count, 'districts' => $districts, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results) . ' itinerary(ies) generated';
        return $this->markComplete($task, ['itineraries' => count($results), 'items' => $results, 'message' => $msg]);
    }

    protected function autoWork(): array
    {
        $results = [];

        foreach ($this->topDistricts()->get()->sortByDesc('places_count')->take(5) as $row) {
            try {
                $itinerary = $this->buildItineraryForDistrict($row->district);
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
        $itinerary = $this->buildItineraryForDistrict($district);
        if (empty($itinerary)) {
            return $this->markFailed($task, "No places found for district: {$district}");
        }
        return $this->markComplete($task, ['district' => $district, 'itinerary' => $itinerary]);
    }

    protected function topDistricts()
    {
        return Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->selectRaw('district, COUNT(*) as places_count')
            ->groupBy('district');
    }

    protected function buildItineraryForDistrict(string $district): array
    {
        $places = Place::active()
            ->with('category:id,name')
            ->where('district', $district)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('average_rating')
            ->take(8)
            ->get(['id', 'name', 'category_id', 'description', 'average_rating', 'latitude', 'longitude']);

        if ($places->isEmpty()) return [];

        $llm = $this->ai();
        $payload = $places->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'category' => $p->category?->name ?? 'attraction',
            'rating' => $p->average_rating ?? 'n/a',
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        try {
            $result = $llm->generateJson(
                "You are a Nepal travel expert. Plan a realistic multi-day itinerary for {$district}, Nepal using ONLY these places.\n\nPlaces: {$payload}\n\nReturn JSON: {\"days\": [{\"day\": 1, \"theme\": \"string\", \"stops\": [{\"place_id\": id, \"name\": \"string\", \"tip\": \"string\"}]}]}\nUse 2-3 days max, order stops logically by geography, never invent places."
            );
            $days = $result['days'] ?? null;
            if (is_array($days) && count($days) > 0) return $days;
        } catch (\Exception $e) {
            Log::warning("Travel consultant LLM failed for {$district}, using template: " . $e->getMessage());
        }

        $grouped = $places->groupBy(fn($p) => $p->category?->name ?? 'attraction');
        $days = [];
        $day = 1;
        foreach ($grouped->take(3) as $category => $group) {
            $days[] = [
                'day' => $day++,
                'theme' => ucfirst($category) . ' highlights',
                'stops' => $group->take(3)->values()->map(fn($p) => [
                    'place_id' => $p->id,
                    'name' => $p->name,
                    'tip' => 'Visit this ' . ($p->category?->name ?? 'spot') . ' — rated ' . ($p->average_rating ?? 'n/a'),
                ])->toArray(),
            ];
        }
        return $days;
    }
}
