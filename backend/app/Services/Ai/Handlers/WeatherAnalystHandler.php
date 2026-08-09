<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Place;
use App\Models\WeatherGrid;
use Illuminate\Support\Facades\Log;

class WeatherAnalystHandler extends BaseHandler
{
    private const WMO_CODES = [
        0 => ['Clear sky', 'low'], 1 => ['Mainly clear', 'low'], 2 => ['Partly cloudy', 'low'],
        3 => ['Overcast', 'low'], 45 => ['Fog', 'medium'], 48 => ['Rime fog', 'medium'],
        51 => ['Light drizzle', 'low'], 53 => ['Drizzle', 'medium'], 55 => ['Heavy drizzle', 'medium'],
        56 => ['Freezing drizzle', 'high'], 57 => ['Heavy freezing drizzle', 'high'],
        61 => ['Light rain', 'medium'], 63 => ['Rain', 'medium'], 65 => ['Heavy rain', 'high'],
        66 => ['Freezing rain', 'high'], 67 => ['Heavy freezing rain', 'high'],
        71 => ['Light snow', 'medium'], 73 => ['Snow', 'high'], 75 => ['Heavy snow', 'high'],
        77 => ['Snow grains', 'medium'], 80 => ['Light showers', 'medium'], 81 => ['Showers', 'medium'],
        82 => ['Violent showers', 'high'], 85 => ['Snow showers', 'high'], 86 => ['Heavy snow showers', 'high'],
        95 => ['Thunderstorm', 'high'], 96 => ['Thunderstorm with hail', 'high'], 99 => ['Severe thunderstorm', 'high'],
    ];

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

        if ($action === 'forecast' && isset($input['district'])) {
            return $this->districtForecast($task, $input['district']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $gridCount = WeatherGrid::count();
        $places = Place::active()->whereNotNull('latitude')->whereNotNull('longitude')->count();
        $msg = "{$gridCount} weather grid points available to analyze weather for {$places} places";
        return $this->markComplete($task, ['grid_points' => $gridCount, 'places' => $places, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results['places']) . ' place weather check(s), ' . count($results['advisories']) . ' advisory(ies) generated';
        return $this->markComplete($task, $results + ['message' => $msg]);
    }

    protected function autoWork(): array
    {
        $places = Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('total_reviews')
            ->take(10)
            ->get();

        $checkResults = [];
        $advisories = [];

        foreach ($places as $place) {
            $weather = $this->weatherFor((float) $place->latitude, (float) $place->longitude);
            if (!$weather) continue;

            $check = [
                'place_id' => $place->id,
                'place' => $place->name,
                'district' => $place->district,
                'temperature_c' => $weather['temperature'],
                'condition' => $weather['condition'],
                'risk' => $weather['risk'],
            ];
            $checkResults[] = $check;

            if ($weather['risk'] === 'high' && $place->district) {
                $advisories[] = [
                    'district' => $place->district,
                    'condition' => $weather['condition'],
                    'advice' => "Travel caution advised in {$place->district} — {$weather['condition']} conditions reported.",
                ];
            }
        }

        return ['places' => $checkResults, 'advisories' => $advisories];
    }

    protected function districtForecast(AiAgentTask $task, string $district): AiAgentTask
    {
        $places = Place::active()
            ->where('district', $district)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->take(5)
            ->get();

        if ($places->isEmpty()) {
            return $this->markFailed($task, "No located places found for district: {$district}");
        }

        $forecasts = [];
        foreach ($places as $place) {
            $weather = $this->weatherFor((float) $place->latitude, (float) $place->longitude);
            if ($weather) $forecasts[] = $weather + ['place' => $place->name];
        }

        $briefing = $this->briefing($district, $forecasts);
        return $this->markComplete($task, [
            'district' => $district,
            'forecasts' => $forecasts,
            'briefing' => $briefing,
        ]);
    }

    protected function weatherFor(float $lat, float $lng): ?array
    {
        $gridLat = round($lat / 0.05) * 0.05;
        $gridLng = round($lng / 0.05) * 0.05;

        $row = WeatherGrid::whereRaw('ABS(grid_lat - ?) < 0.0001', [$gridLat])
            ->whereRaw('ABS(grid_lng - ?) < 0.0001', [$gridLng])
            ->first();

        if (!$row) return null;

        $code = (int) $row->weather_code;
        [$condition, $risk] = self::WMO_CODES[$code] ?? ['Unknown', 'low'];

        if ($risk !== 'high' && $row->precipitation !== null && (float) $row->precipitation >= 10) {
            $risk = 'high';
        }
        if ($risk !== 'high' && $row->wind_speed !== null && (float) $row->wind_speed >= 60) {
            $risk = 'high';
        }

        return [
            'temperature' => $row->temperature,
            'precipitation_mm' => $row->precipitation,
            'wind_speed_kmh' => $row->wind_speed,
            'humidity' => $row->humidity,
            'condition' => $condition,
            'risk' => $risk,
        ];
    }

    protected function briefing(string $district, array $forecasts): string
    {
        if (empty($forecasts)) return 'No weather data available.';

        $llm = $this->ai();
        $json = json_encode($forecasts, JSON_UNESCAPED_UNICODE);
        try {
            return $llm->generate(
                "You are a Nepal travel weather analyst. Write a 2-sentence travel weather briefing in Nepali for {$district} based on: {$json}. Mention conditions and a practical tip. Return ONLY the Nepali text."
            );
        } catch (\Exception $e) {
            Log::warning("Weather analyst briefing LLM failed: " . $e->getMessage());
            $worst = collect($forecasts)->sortByDesc('risk')->first();
            return "{$district} ma aile {$worst['condition']} raheko cha — yatra garda satarkata apanaunu hola.";
        }
    }
}
