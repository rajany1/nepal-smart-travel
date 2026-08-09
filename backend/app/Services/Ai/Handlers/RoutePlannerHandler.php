<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Place;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RoutePlannerHandler extends BaseHandler
{
    private const OSRM_BASE = 'https://router.project-osrm.org/route/v1/driving';

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

        if ($action === 'route' && isset($input['origin_lat'], $input['origin_lng'], $input['dest_lat'], $input['dest_lng'])) {
            return $this->planRoute($task, (float) $input['origin_lat'], (float) $input['origin_lng'], (float) $input['dest_lat'], (float) $input['dest_lng']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $districts = Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->distinct('district')
            ->count('district');
        $msg = "{$districts} districts available for route planning between top destinations";
        return $this->markComplete($task, ['districts' => $districts, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results['legs']) . ' route leg(s) computed across ' . count($results['anchors']) . ' districts';
        return $this->markComplete($task, $results);
    }

    protected function autoWork(): array
    {
        $anchors = $this->topDistrictAnchors(5);

        $legs = [];
        foreach ($anchors as $i => $anchor) {
            if (!isset($anchors[$i + 1])) break;
            $leg = $this->routeLeg($anchor, $anchors[$i + 1]);
            if ($leg) $legs[] = $leg;
        }

        $totalKm = array_sum(array_column($legs, 'distance_km'));
        $totalHours = array_sum(array_column($legs, 'duration_hours'));

        return [
            'anchors' => $anchors,
            'legs' => $legs,
            'total_distance_km' => round($totalKm, 1),
            'total_duration_hours' => round($totalHours, 1),
            'message' => count($legs) . ' leg(s) planned, ' . round($totalKm, 1) . ' km total',
        ];
    }

    protected function planRoute(AiAgentTask $task, float $originLat, float $originLng, float $destLat, float $destLng): AiAgentTask
    {
        $leg = $this->osrmRoute($originLat, $originLng, $destLat, $destLng);
        if ($leg === null) {
            return $this->markFailed($task, 'Routing service unavailable (OSRM).');
        }
        return $this->markComplete($task, $leg);
    }

    protected function topDistrictAnchors(int $count): array
    {
        $rows = Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->selectRaw('district, MAX(total_reviews) as max_reviews')
            ->groupBy('district')
            ->orderByDesc('max_reviews')
            ->take($count)
            ->get();

        $anchors = [];
        foreach ($rows as $row) {
            $place = Place::active()
                ->where('district', $row->district)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderByDesc('total_reviews')
                ->first(['id', 'name', 'district', 'latitude', 'longitude']);

            if ($place) {
                $anchors[] = [
                    'place_id' => $place->id,
                    'name' => $place->name,
                    'district' => $place->district,
                    'latitude' => (float) $place->latitude,
                    'longitude' => (float) $place->longitude,
                ];
            }
        }
        return $anchors;
    }

    protected function routeLeg(array $from, array $to): ?array
    {
        $result = $this->osrmRoute($from['latitude'], $from['longitude'], $to['latitude'], $to['longitude']);
        if ($result === null) return null;

        return [
            'from' => $from['name'] . ' (' . $from['district'] . ')',
            'to' => $to['name'] . ' (' . $to['district'] . ')',
            'distance_km' => $result['distance_km'],
            'duration_hours' => $result['duration_hours'],
        ];
    }

    protected function osrmRoute(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        try {
            $url = sprintf('%s/%s,%s;%s,%s?overview=false', self::OSRM_BASE, $originLng, $originLat, $destLng, $destLat);
            $response = Http::timeout(20)->get($url);

            if (!$response->successful()) {
                Log::warning('OSRM request failed: HTTP ' . $response->status());
                return null;
            }

            $data = $response->json();
            $route = $data['routes'][0] ?? null;
            if (!$route) return null;

            return [
                'distance_km' => round($route['distance'] / 1000, 1),
                'duration_hours' => round($route['duration'] / 3600, 1),
            ];
        } catch (\Exception $e) {
            Log::warning('OSRM error: ' . $e->getMessage());
            return null;
        }
    }
}
