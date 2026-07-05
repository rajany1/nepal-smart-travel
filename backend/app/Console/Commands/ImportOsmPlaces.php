<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Place;
use App\Models\PlaceCategories;
use Illuminate\Support\Str;

class ImportOsmPlaces extends Command
{
    protected $signature = 'places:import-osm 
        {--radius=10 : Search radius in km around each city}
        {--city= : Single city to import (default: all major Nepali cities)}
        {--limit=200 : Max places per city}
        {--delay=3 : Seconds to wait between cities}
        {--retries=3 : Max retries per city on 429/504}';

    protected $description = 'Import nearby places from OpenStreetMap for Nepal and store in the database';

    private array $nepalCities = [
        ['name' => 'Kathmandu', 'lat' => 27.7172, 'lng' => 85.3240],
        ['name' => 'Pokhara', 'lat' => 28.2096, 'lng' => 83.9856],
        ['name' => 'Bharatpur', 'lat' => 27.6833, 'lng' => 84.4333],
        ['name' => 'Lalitpur', 'lat' => 27.6667, 'lng' => 85.3333],
        ['name' => 'Bhaktapur', 'lat' => 27.6722, 'lng' => 85.4278],
        ['name' => 'Birgunj', 'lat' => 27.0000, 'lng' => 84.8667],
        ['name' => 'Janakpur', 'lat' => 26.7286, 'lng' => 85.9248],
        ['name' => 'Butwal', 'lat' => 27.7000, 'lng' => 83.4667],
        ['name' => 'Biratnagar', 'lat' => 26.4833, 'lng' => 87.2833],
        ['name' => 'Dharan', 'lat' => 26.8167, 'lng' => 87.2833],
        ['name' => 'Nepalgunj', 'lat' => 28.0500, 'lng' => 81.6167],
        ['name' => 'Hetauda', 'lat' => 27.4167, 'lng' => 85.0333],
        ['name' => 'Chitwan', 'lat' => 27.5290, 'lng' => 84.3540],
        ['name' => 'Lumbini', 'lat' => 27.4840, 'lng' => 83.2740],
        ['name' => 'Dhangadhi', 'lat' => 28.6833, 'lng' => 80.6167],
        ['name' => 'Mahendranagar', 'lat' => 28.9667, 'lng' => 80.2333],
        ['name' => 'Bhadrapur', 'lat' => 26.5500, 'lng' => 88.0833],
        ['name' => 'Ghorahi', 'lat' => 28.0333, 'lng' => 82.4833],
        ['name' => 'Tansen', 'lat' => 27.8667, 'lng' => 83.5500],
        ['name' => 'Jomsom', 'lat' => 28.7833, 'lng' => 83.7333],
        ['name' => 'Namche Bazaar', 'lat' => 27.8050, 'lng' => 86.7167],
        ['name' => 'Sauraha', 'lat' => 27.5740, 'lng' => 84.5020],
    ];

    public function handle(): int
    {
        set_time_limit(0);

        $radius = (int) $this->option('radius');
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');
        $retries = (int) $this->option('retries');
        $specificCity = $this->option('city');

        // Ensure "All" category exists for OSM places
        $allCat = PlaceCategories::firstOrCreate(
            ['name' => 'All'],
            ['name' => 'All', 'icon' => 'explore']
        );

        // Ensure other necessary categories exist
        $categories = [
            'Attractions', 'Hotels', 'Restaurants', 'Cafe',
            'Emergency', 'ATMs', 'Fuel', 'Activities',
            'Transport', 'Shopping', 'Services', 'Education',
            'Entertainment', 'Hospital', 'Clinic', 'Pharmacy',
            'Bank', 'Parking', 'Recreation', 'Nature',
        ];
        foreach ($categories as $catName) {
            PlaceCategories::firstOrCreate(
                ['name' => $catName],
                ['name' => $catName, 'icon' => strtolower($catName)]
            );
        }

        $cities = $specificCity
            ? [collect($this->nepalCities)->firstWhere('name', $specificCity)]
            : $this->nepalCities;

        if ($specificCity && !$cities[0]) {
            $this->error("City '$specificCity' not found in predefined list.");
            return 1;
        }

        $totalImported = 0;
        $totalSkipped = 0;

        $bar = $this->output->createProgressBar(count($cities));
        $bar->start();

        foreach ($cities as $i => $city) {
            if ($i > 0 && $delay > 0) {
                $this->line("  Waiting {$delay}s to avoid rate limit...");
                sleep($delay);
            }
            $result = $this->importCityPlaces($city, $radius, $limit, $retries);
            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Import complete: {$totalImported} imported, {$totalSkipped} skipped (duplicates).");

        return 0;
    }

    private function importCityPlaces(array $city, int $radius, int $limit, int $maxRetries = 3): array
    {
        $radiusMeters = $radius * 1000;
        $lat = $city['lat'];
        $lng = $city['lng'];
        $cityName = $city['name'];

        $this->line("\nFetching OSM data for {$cityName}...");

        $overpassQuery = $this->buildOverpassQuery($lat, $lng, $radiusMeters, $limit);

        $responseBody = null;
        $httpCode = 0;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: NepalSmartTravel/1.0",
                        'content' => 'data=' . urlencode($overpassQuery),
                        'timeout' => 120,
                        'ignore_errors' => true,
                    ]
                ];
                $context = stream_context_create($opts);
                $responseBody = @file_get_contents('https://overpass-api.de/api/interpreter', false, $context);

                if ($responseBody === false) {
                    if ($attempt < $maxRetries) {
                        $wait = $attempt * 5;
                        $this->warn("  ⚠ Connection failed for {$cityName}, retrying in {$wait}s ({$attempt}/{$maxRetries})...");
                        sleep($wait);
                        continue;
                    }
                    $this->warn("  ⚠ Overpass API connection failed for {$cityName}");
                    return ['imported' => 0, 'skipped' => 0];
                }

                $httpCode = 200;
                if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
                    $httpCode = (int)$m[0];
                }

                if ($httpCode === 429 || $httpCode === 504) {
                    if ($attempt < $maxRetries) {
                        $wait = $attempt * 5;
                        $this->warn("  ⚠ Overpass API returned {$httpCode} for {$cityName}, retrying in {$wait}s ({$attempt}/{$maxRetries})...");
                        sleep($wait);
                        continue;
                    }
                    $this->warn("  ⚠ Overpass API returned status {$httpCode} for {$cityName} (gave up after {$maxRetries} retries)");
                    return ['imported' => 0, 'skipped' => 0];
                }

                if ($httpCode !== 200) {
                    $this->warn("  ⚠ Overpass API returned status {$httpCode} for {$cityName}");
                    return ['imported' => 0, 'skipped' => 0];
                }

                break;

            } catch (\Exception $e) {
                if ($attempt < $maxRetries) {
                    $wait = $attempt * 5;
                    $this->warn("  ⚠ Error for {$cityName}: {$e->getMessage()}, retrying in {$wait}s ({$attempt}/{$maxRetries})...");
                    sleep($wait);
                    continue;
                }
                $this->error("  ✗ Overpass API error for {$cityName}: {$e->getMessage()}");
                return ['imported' => 0, 'skipped' => 0];
            }
        }

        try {
            $data = json_decode($responseBody, true);
            $elements = $data['elements'] ?? [];

            if (empty($elements)) {
                $this->warn("  ⚠ No OSM data returned for {$cityName}");
                return ['imported' => 0, 'skipped' => 0];
            }

            $imported = 0;
            $skipped = 0;

            foreach ($elements as $element) {
                $tags = $element['tags'] ?? [];
                $elemLat = $element['lat'] ?? null;
                $elemLng = $element['lon'] ?? null;

                if (!$elemLat || !$elemLng) continue;

                $name = $tags['name'] ?? $tags['name:en'] ?? null;
                if (!$name) continue;

                $osmId = $element['type'] . '/' . $element['id'];

                // Skip if already imported
                if (Place::where('osm_id', $osmId)->exists()) {
                    $skipped++;
                    continue;
                }

                $category = $this->osmToCategory($tags);
                $categoryId = $this->getCategoryId($category);

                $address = implode(', ', array_filter([
                    $tags['addr:street'] ?? null,
                    $tags['addr:city'] ?? $tags['addr:district'] ?? $cityName,
                ]));

                $phone = $tags['phone'] ?? $tags['contact:phone'] ?? null;
                $website = $tags['website'] ?? $tags['contact:website'] ?? null;
                $description = $tags['description'] ?? $tags['note'] ?? null;
                $rating = isset($tags['rating']) ? (float)$tags['rating'] : null;

                try {
                    Place::create([
                        'uuid' => (string) Str::uuid(),
                        'name' => $name,
                        'description' => $description,
                        'address' => $address ?: null,
                        'district' => $tags['addr:city'] ?? $tags['addr:district'] ?? $cityName,
                        'latitude' => $elemLat,
                        'longitude' => $elemLng,
                        'category_id' => $categoryId,
                        'phone' => $phone,
                        'website' => $website,
                        'average_rating' => $rating ?? 0.0,
                        'is_verified' => false,
                        'is_featured' => false,
                        'is_active' => true,
                        'source' => 'osm',
                        'osm_id' => $osmId,
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Failed to import '{$name}': {$e->getMessage()}");
                }
            }

            $this->info("  ✓ {$cityName}: {$imported} imported, {$skipped} skipped");
            return compact('imported', 'skipped');

        } catch (\Exception $e) {
            $this->error("  ✗ Overpass API error for {$cityName}: {$e->getMessage()}");
            return ['imported' => 0, 'skipped' => 0];
        }
    }

    private function buildOverpassQuery(float $lat, float $lng, int $radiusMeters, int $limit): string
    {
        $amenityTypes = [
            'restaurant', 'cafe', 'fast_food', 'pub', 'bar',
            'hotel', 'motel', 'hostel', 'guest_house',
            'hospital', 'clinic', 'pharmacy', 'doctors',
            'bank', 'atm', 'fuel', 'taxi',
            'police', 'fire_station',
            'bus_station', 'ferry_terminal', 'parking',
            'post_office', 'library', 'place_of_worship',
            'school', 'university', 'college',
            'marketplace', 'theatre', 'cinema', 'community_centre',
        ];

        $tourismTypes = [
            'attraction', 'hotel', 'motel', 'hostel',
            'guest_house', 'information', 'museum',
            'viewpoint', 'picnic_site', 'camp_site',
            'caravan_site', 'wilderness_hut', 'alpine_hut',
            'artwork', 'gallery', 'theme_park', 'zoo',
        ];

        $shopTypes = [
            'supermarket', 'convenience', 'mall', 'department_store',
            'clothes', 'electronics', 'gift', 'souvenir',
        ];

        $queries = [];

        // Amenities query
        $queries[] = "node[\"amenity\"~\"" . implode('|', $amenityTypes) . "\"](around:{$radiusMeters},{$lat},{$lng});";

        // Tourism query
        $queries[] = "node[\"tourism\"~\"" . implode('|', $tourismTypes) . "\"](around:{$radiusMeters},{$lat},{$lng});";

        // Shops query
        $queries[] = "node[\"shop\"~\"" . implode('|', $shopTypes) . "\"](around:{$radiusMeters},{$lat},{$lng});";

        // Leisure
        $queries[] = "node[\"leisure\"](around:{$radiusMeters},{$lat},{$lng});";

        // Historic
        $queries[] = "node[\"historic\"](around:{$radiusMeters},{$lat},{$lng});";

        // Natural viewpoints
        $queries[] = "node[\"natural\"~\"peak|volcano|bay|cape|beach\"](around:{$radiusMeters},{$lat},{$lng});";

        return "[out:json];(" . implode('', $queries) . ");out body {$limit};";
    }

    private function osmToCategory(array $tags): string
    {
        $amenity = $tags['amenity'] ?? null;
        $tourism = $tags['tourism'] ?? null;
        $shop = $tags['shop'] ?? null;
        $leisure = $tags['leisure'] ?? null;
        $historic = $tags['historic'] ?? null;

        if ($amenity) {
            return match($amenity) {
                'restaurant', 'fast_food' => 'Restaurants',
                'cafe' => 'Cafe',
                'pub', 'bar' => 'Restaurants',
                'hotel', 'motel', 'hostel', 'guest_house' => 'Hotels',
                'hospital', 'clinic', 'doctors' => 'Hospital',
                'pharmacy' => 'Pharmacy',
                'bank' => 'Bank',
                'atm' => 'ATMs',
                'fuel' => 'Fuel',
                'taxi', 'bus_station', 'ferry_terminal' => 'Transport',
                'police', 'fire_station' => 'Emergency',
                'parking' => 'Parking',
                'marketplace' => 'Shopping',
                'theatre', 'cinema', 'community_centre' => 'Entertainment',
                'post_office', 'library' => 'Services',
                'school', 'university', 'college' => 'Education',
                'place_of_worship' => 'Attractions',
                default => 'Services',
            };
        }
        if ($tourism) {
            return match($tourism) {
                'hotel', 'motel', 'hostel', 'guest_house' => 'Hotels',
                'museum', 'attraction', 'artwork', 'gallery', 'theme_park', 'zoo' => 'Attractions',
                'viewpoint' => 'Nature',
                'camp_site', 'picnic_site' => 'Activities',
                'information' => 'Services',
                default => 'Attractions',
            };
        }
        if ($shop) return 'Shopping';
        if ($leisure) return 'Recreation';
        if ($historic) return 'Attractions';
        return 'Attractions';
    }

    private function getCategoryId(string $categoryName): ?int
    {
        $cat = PlaceCategories::where('name', $categoryName)->first();
        return $cat ? $cat->id : null;
    }
}