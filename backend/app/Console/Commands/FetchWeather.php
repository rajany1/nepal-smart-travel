<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WeatherGrid;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class FetchWeather extends Command
{
    protected $signature = 'weather:fetch';
    protected $description = 'Fetch weather forecast from Open-Meteo for Nepal grid points';

    private const GRID_STEP = 0.05;
    private const BATCH_SIZE = 500;
    private const RATE_LIMIT_DELAY = 10;
    private const MIN_LAT = 26.35;
    private const MAX_LAT = 30.45;
    private const MIN_LNG = 80.05;
    private const MAX_LNG = 88.20;

    // From Natural Earth 1:10m admin-0 countries (Douglas-Peucker tol=0.05, 81 verts)
    private const NEPAL_BOUNDARY = [
        [27.8609, 88.1182], [27.7741, 88.1597], [27.4949, 88.0233],
        [27.0951, 87.9755], [26.8628, 88.1514], [26.4539, 88.0742],
        [26.3699, 88.0066], [26.4515, 87.7690], [26.4270, 87.4165],
        [26.3533, 87.3262], [26.3942, 87.1350], [26.5443, 87.0450],
        [26.4315, 86.7964], [26.6120, 86.2844], [26.6444, 85.9757],
        [26.5799, 85.8664], [26.6376, 85.7270], [26.8510, 85.6094],
        [26.7589, 85.1949], [26.8657, 85.1228], [27.0284, 84.6402],
        [27.2034, 84.6577], [27.3290, 84.5770], [27.3761, 84.2894],
        [27.5169, 84.0995], [27.3659, 83.8020], [27.4705, 83.3870],
        [27.3309, 83.2824], [27.4312, 83.1696], [27.4950, 82.7521],
        [27.6944, 82.6797], [27.6664, 82.4407], [27.9052, 82.0516],
        [27.8509, 81.8557], [28.1606, 81.3960], [28.1283, 81.2962],
        [28.3722, 81.1463], [28.6643, 80.5574], [28.6701, 80.4975],
        [28.5756, 80.4934], [28.6401, 80.3183], [28.8777, 80.0303],
        [29.2681, 80.2787], [29.4169, 80.2137], [29.5842, 80.3733],
        [29.7579, 80.3690], [30.2103, 80.8837], [30.0045, 81.1945],
        [30.1435, 81.3383], [30.3739, 81.3875], [30.3319, 81.5207],
        [30.4143, 81.5916], [30.3392, 82.0499], [30.0582, 82.1538],
        [29.9324, 82.5242], [29.6598, 82.8366], [29.5788, 83.0649],
        [29.6256, 83.1500], [29.1792, 83.5360], [29.1548, 83.6393],
        [29.2918, 83.9332], [29.2566, 84.0897], [28.9407, 84.2067],
        [28.7533, 84.4378], [28.5589, 84.7818], [28.5950, 85.1610],
        [28.4455, 85.0837], [28.2920, 85.1342], [28.2508, 85.5989],
        [28.3247, 85.6760], [27.8852, 85.9803], [27.9228, 86.1072],
        [28.1565, 86.1560], [28.0026, 86.2030], [27.9099, 86.4253],
        [28.0782, 86.5413], [28.1012, 86.6495], [27.8245, 87.1820],
        [27.8056, 87.7005], [27.9066, 87.8259], [27.8609, 88.1182],
    ];

    public function handle(): int
    {
        $this->info('Generating Nepal weather grid...');

        $gridPoints = $this->generateGridPoints();
        $this->info('Generated ' . count($gridPoints) . ' grid points inside Nepal.');

        if (empty($gridPoints)) {
            $this->error('No grid points generated.');
            return 1;
        }

        $batches = array_chunk($gridPoints, self::BATCH_SIZE);
        $this->info('Fetching weather for ' . count($batches) . ' batch(es)...');

        $allRows = [];
        $fetchedAt = now();

        foreach ($batches as $batchIndex => $batch) {
            $this->info('Batch ' . ($batchIndex + 1) . '/' . count($batches) . ' (' . count($batch) . ' points)...');

            $lats = array_map(fn($p) => $p[0], $batch);
            $lngs = array_map(fn($p) => $p[1], $batch);

            $retries = 3;
            $success = false;

            while ($retries > 0 && !$success) {
                try {
                    $response = Http::timeout(30)->get(
                        'https://api.open-meteo.com/v1/forecast',
                        [
                            'latitude' => implode(',', $lats),
                            'longitude' => implode(',', $lngs),
                            'current' => 'weather_code,temperature_2m,precipitation,wind_speed_10m,relative_humidity_2m',
                            'timezone' => 'auto',
                        ]
                    );

                    if ($response->status() === 429) {
                        $retries--;
                        if ($retries > 0) {
                            $this->warn('Rate limited, waiting 65s...');
                            sleep(65);
                        }
                        continue;
                    }

                    if (!$response->successful()) {
                        $this->warn('Batch ' . ($batchIndex + 1) . ' failed: HTTP ' . $response->status());
                        break;
                    }

                    $data = $response->json();
                    $items = is_array($data) && array_is_list($data) ? $data : [$data];

                    foreach ($items as $item) {
                        if (!isset($item['current'])) continue;
                        $allRows[] = [
                            'grid_lat' => $item['latitude'],
                            'grid_lng' => $item['longitude'],
                            'weather_code' => (int) ($item['current']['weather_code'] ?? 0),
                            'temperature' => $item['current']['temperature_2m'] ?? null,
                            'precipitation' => $item['current']['precipitation'] ?? null,
                            'wind_speed' => $item['current']['wind_speed_10m'] ?? null,
                            'humidity' => $item['current']['relative_humidity_2m'] ?? null,
                            'fetched_at' => $fetchedAt,
                            'created_at' => $fetchedAt,
                            'updated_at' => $fetchedAt,
                        ];
                    }

                    $success = true;
                } catch (\Exception $e) {
                    $this->warn('Batch ' . ($batchIndex + 1) . ' error: ' . $e->getMessage());
                    break;
                }
            }

            if (!$success) {
                $this->warn('Batch ' . ($batchIndex + 1) . ' failed after retries');
            }

            if ($batchIndex + 1 < count($batches)) sleep(self::RATE_LIMIT_DELAY);
        }

        if (empty($allRows)) {
            $this->warn('No weather data fetched.');
            return 1;
        }

        $this->info('Inserting ' . count($allRows) . ' weather records...');

        WeatherGrid::query()->truncate();

        DB::transaction(function () use ($allRows) {
            foreach (array_chunk($allRows, 500) as $chunk) {
                WeatherGrid::insert($chunk);
            }
        });

        $this->info('Done. Fetched weather for ' . count($allRows) . ' grid points at ' . $fetchedAt);
        return 0;
    }

    private function generateGridPoints(): array
    {
        $points = [];

        for ($lat = self::MIN_LAT; $lat <= self::MAX_LAT; $lat += self::GRID_STEP) {
            for ($lng = self::MIN_LNG; $lng <= self::MAX_LNG; $lng += self::GRID_STEP) {
                $lat = round($lat, 4);
                $lng = round($lng, 4);

                if ($this->isInsideNepal($lat, $lng)) {
                    $points[] = [$lat, $lng];
                }
            }
        }

        return $points;
    }

    private function isInsideNepal(float $lat, float $lng): bool
    {
        $polygon = self::NEPAL_BOUNDARY;
        $n = count($polygon);
        $inside = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = $polygon[$i][0];
            $yj = $polygon[$j][0];
            $xi = $polygon[$i][1];
            $xj = $polygon[$j][1];

            if ((($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
