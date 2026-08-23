<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlaceCategories;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * District-wise OSM import service.
 *
 * - Extracts tourism/amenity/leisure/shop/historic (plus key natural) POIs
 *   per district (admin_level=6 area) via Overpass, including ways/relations
 *   with `out center`.
 * - Maps OSM tags onto Nepal Smart Travels categories.
 * - Deduplicates on the canonical OSM identity `osm_id` (upsert).
 * - Merge strategy: OSM refresh updates only OSM-origin fields; manually
 *   managed business fields (is_active, is_verified, is_featured, ratings,
 *   images, created_by) are never overwritten.
 * - Tracks import metadata in a Redis hash: osm:import:{district}.
 */
class OsmImportService
{
    public const STATUS_KEY_PREFIX = 'osm:import:';
    public const IMPORTED_SET = 'osm:imported:districts';

    /** Districts with Overpass admin_level=6 name alternates where needed. */
    public const DISTRICTS = [
        'Bhojpur' => [], 'Dhankuta' => [], 'Ilam' => [], 'Jhapa' => [], 'Khotang' => [],
        'Morang' => [], 'Okhaldhunga' => [], 'Panchthar' => [], 'Sankhuwasabha' => [],
        'Solukhumbu' => [], 'Sunsari' => [], 'Taplejung' => [], 'Terhathum' => [], 'Udayapur' => [],
        'Dhanusa' => [], 'Mahottari' => [], 'Parsa' => [], 'Rautahat' => [], 'Saptari' => [],
        'Sarlahi' => [], 'Siraha' => [], 'Bara' => [],
        'Bhaktapur' => [], 'Chitwan' => [], 'Dhading' => [], 'Dolakha' => [], 'Kathmandu' => [],
        'Kavrepalanchok' => ['Kavrepalanchowk', 'Kavre'], 'Lalitpur' => [], 'Makwanpur' => [],
        'Nuwakot' => [], 'Ramechhap' => [], 'Rasuwa' => [], 'Sindhuli' => [], 'Sindhupalchok' => ['Sindhupalchowk'],
        'Baglung' => [], 'Gorkha' => [], 'Kaski' => [], 'Lamjung' => [], 'Manang' => [],
        'Mustang' => [], 'Myagdi' => [], 'Nawalpur' => ['Nawalparasi East', 'Nawalparasi'], 'Parbat' => [],
        'Syangja' => [], 'Tanahun' => [],
        'Arghakhanchi' => [], 'Banke' => [], 'Bardiya' => [], 'Dang' => [], 'Eastern Rukum' => ['Rukum East', 'Purbi Rukum'],
        'Gulmi' => [], 'Kapilvastu' => [], 'Palpa' => [], 'Parasi' => ['Nawalparasi West', 'Nawalparasi'], 'Pyuthan' => [],
        'Rolpa' => [], 'Rupandehi' => [],
        'Dailekh' => [], 'Dolpa' => [], 'Humla' => [], 'Jajarkot' => [], 'Jumla' => [],
        'Kalikot' => [], 'Mugu' => [], 'Salyan' => [], 'Surkhet' => [], 'Western Rukum' => ['Rukum West', 'Pashchim Rukum'],
        'Achham' => [], 'Baitadi' => [], 'Bajhang' => [], 'Bajura' => [], 'Dadeldhura' => [],
        'Darchula' => [], 'Doti' => [], 'Kailali' => [], 'Kanchanpur' => [],
    ];

    private const CATEGORY_PATTERNS = [
        'amenity' => 'restaurant|cafe|fast_food|pub|bar|hotel|motel|hostel|guest_house|hospital|clinic|pharmacy|doctors|bank|atm|fuel|taxi|police|fire_station|embassy|marketplace|theatre|cinema|community_centre|bus_station|ferry_terminal|parking|post_office|library|place_of_worship|school|university|college',
        'tourism' => 'attraction|hotel|motel|hostel|guest_house|information|museum|viewpoint|picnic_site|camp_site|caravan_site|wilderness_hut|alpine_hut|artwork|gallery|theme_park|zoo',
        'shop' => 'supermarket|convenience|mall|department_store|clothes|electronics|gift|souvenir',
        'leisure' => '',
        'historic' => '',
        'natural' => 'peak|volcano|bay|cape|beach',
    ];

    public function districts(): array
    {
        return array_keys(self::DISTRICTS);
    }

    /** All district names that have at least one successful import. */
    public function importedDistricts(): array
    {
        return Redis::sMembers(self::IMPORTED_SET) ?: [];
    }

    public function markImported(string $district): void
    {
        Redis::sAdd(self::IMPORTED_SET, $district);
    }

    public function statusKey(string $district): string
    {
        return self::STATUS_KEY_PREFIX . Str::slug($district);
    }

    public function setStatus(string $district, string $status, array $extra = []): void
    {
        $data = array_merge([
            'status' => $status,
            'started_at' => now()->toIso8601String(),
        ], $extra);
        Redis::hMSet($this->statusKey($district), $data);
        Redis::expire($this->statusKey($district), 60 * 60 * 24 * 14); // 14 days retention
    }

    public function getStatus(string $district): array
    {
        return Redis::hGetAll($this->statusKey($district)) ?: [];
    }

    /**
     * Import (or refresh) one district. Returns ['imported','updated','skipped'].
     */
    public function importDistrict(string $district, bool $refresh = true): array
    {
        $this->setStatus($district, 'running');

        $queries = $this->buildDistrictQueries($district);
        $cacheKey = 'osm:import:raw:' . Str::slug($district);
        $overpass = app(OsmOverpassService::class);

        $data = null;
        foreach ($queries as $i => $query) {
            // 24h raw cache for import responses: repeated refreshes inside a day
            // reuse the same Overpass payload.
            $data = $overpass->fetchRaw($query, $cacheKey . ':' . $i, 86400, 200);
            if ($data !== null && !empty($data['elements'])) {
                break;
            }
            Log::warning('OSM import: candidate query empty', ['district' => $district, 'candidate' => $i]);
        }

        if ($data === null) {
            $this->setStatus($district, 'failed', [
                'error' => 'Overpass request failed for district: ' . $district,
                'completed_at' => now()->toIso8601String(),
            ]);
            return ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $elements = $data['elements'] ?? [];
        $result = $this->upsertElements($district, $elements);

        $this->setStatus($district, 'completed', [
            'completed_at' => now()->toIso8601String(),
            'object_count' => $result['imported'] + $result['updated'] + $result['skipped'],
            'imported' => $result['imported'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'last_successful_import' => now()->toIso8601String(),
            'error' => '',
        ]);
        $this->markImported($district);

        // Refreshed map data: invalidate the Nepal-wide places cache.
        PlacesCache::bump();

        Log::info('OSM import completed', ['district' => $district, 'result' => $result]);

        return $result;
    }

    /**
     * Resolve every Nepal district relation (admin_level=6) to its derived
     * Overpass area id once, and cache the map in Redis for 7 days.
     */
    private function districtAreaMap(): array
    {
        $key = 'osm:import:area-map';
        $cached = Redis::get($key);
        if ($cached) {
            $map = json_decode($cached, true);
            if (is_array($map)) {
                return $map;
            }
        }

        $query = '[out:json][timeout:120];rel["boundary"="administrative"]["admin_level"="6"](26.0,79.5,31.0,89.0);out tags;';
        $data = app(OsmOverpassService::class)->fetchRaw($query, $key . ':raw', 86400, 90);
        $map = ['name:en' => [], 'name' => []];

        foreach (($data['elements'] ?? []) as $el) {
            $tags = $el['tags'] ?? [];
            if (empty($tags)) {
                continue;
            }
            $areaId = 3600000000 + (int) ($el['id'] ?? 0);
            if (isset($tags['name:en'])) {
                $map['name:en'][mb_strtolower(trim($tags['name:en']))] = $areaId;
            }
            if (isset($tags['name'])) {
                $map['name'][mb_strtolower(trim($tags['name']))] = $areaId;
            }
        }

        if (!empty($map['name:en']) || !empty($map['name'])) {
            Redis::setex($key, 60 * 60 * 24 * 7, json_encode($map));
        }

        return $map;
    }

    /**
     * Overpass queries for one district, most precise first.
     * Nepal districts are admin_level=6 relations; their derived areas are
     * addressed directly by area id (area index only covers `name`, so
     * `name:en` lookups cannot work).
     */
    public function buildDistrictQueries(string $district): array
    {
        $candidates = array_merge([$district], self::DISTRICTS[$district] ?? []);

        $map = $this->districtAreaMap();
        $areaId = null;
        foreach ($candidates as $candidate) {
            $lc = mb_strtolower(trim($candidate));
            $areaId = $map['name:en'][$lc] ?? $map['name'][$lc] ?? null;
            if ($areaId !== null) {
                break;
            }
        }

        $clauses = '';
        foreach (self::CATEGORY_PATTERNS as $key => $pattern) {
            $attr = $pattern === '' ? '["' . $key . '"]' : '["' . $key . '"~"' . $pattern . '"]';
            $clauses .= 'nwr' . $attr . '(area.a);';
        }

        if ($areaId !== null) {
            return [
                '[out:json][timeout:170][maxsize:400000000];area(' . $areaId . ')->.a;(' . $clauses . ');out center tags;',
            ];
        }

        // Fallback: match the area by its plain `name` (best effort).
        $escaped = array_map(
            fn ($n) => preg_quote(preg_replace('/[^\p{L}0-9\s\-_]/u', '', $n), '/'),
            $candidates
        );
        $or = implode('|', $escaped);
        $base = '[out:json][timeout:170][maxsize:400000000];(';
        $tail = ');out center tags;';
        return [
            $base . 'area["name"~"^(' . $or . ')$"]["admin_level"="6"]->.a;' . $clauses . $tail,
            $base . 'area["name"~"^(' . $or . ')$"]->.a;' . $clauses . $tail,
        ];
    }

    /**
     * Upsert all elements: update existing osm_id rows (OSM fields only),
     * attach legacy rows (same name within ~200m), insert new ones.
     */
    private function upsertElements(string $district, array $elements): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];
            if (empty($tags)) {
                $skipped++;
                continue;
            }

            [$lat, $lng] = $this->elementCentroid($element);
            if ($lat === null || $lng === null) {
                $skipped++;
                continue;
            }

            $name = $tags['name'] ?? $tags['name:en'] ?? null;
            if (!$name) {
                $skipped++;
                continue;
            }

            $osmType = $element['type'] ?? 'node';
            $osmId = $osmType . '/' . ($element['id'] ?? '');
            if ($osmId === 'node/') {
                $skipped++;
                continue;
            }

            $categoryName = $this->osmToCategory($tags);
            $categoryId = $this->resolveCategoryId($categoryName);

            $address = implode(', ', array_filter([
                $tags['addr:street'] ?? null,
                $tags['addr:city'] ?? null,
            ])) ?: null;

            $osmFields = [
                'name' => $name,
                'description' => $tags['description'] ?? $tags['note'] ?? null,
                'address' => $address,
                'district' => $tags['addr:city'] ?? $tags['addr:district'] ?? $district,
                'latitude' => $lat,
                'longitude' => $lng,
                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                'website' => $tags['website'] ?? $tags['contact:website'] ?? null,
                'category_id' => $categoryId,
                'osm_type' => $osmType,
                'imported_at' => now(),
            ];

            $existing = Place::where('osm_id', $osmId)->first();

            if ($existing) {
                // Update OSM-origin fields only — never touch manually managed
                // business fields (is_active, is_verified, is_featured,
                // ratings, images, created_by).
                $existing->update($osmFields);
                $updated++;
                continue;
            }

            // Legacy attach: admin-created row without osm_id at the same spot
            // and same name gets the canonical osm_id instead of a duplicate.
            $legacy = Place::whereNull('osm_id')
                ->where('is_active', true)
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
                ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= 0.2",
                    [$lat, $lng, $lat])
                ->first();
            if ($legacy) {
                $legacy->update([
                    'osm_id' => $osmId,
                    'osm_type' => $osmType,
                    'imported_at' => now(),
                    'source' => $legacy->source === 'admin' ? 'osm' : $legacy->source,
                ]);
                $updated++;
                continue;
            }

            try {
                Place::create(array_merge($osmFields, [
                    'uuid' => (string) Str::uuid(),
                    'is_active' => true,
                    'is_verified' => false,
                    'is_featured' => false,
                    'source' => 'osm',
                    'osm_id' => $osmId,
                ]));
                $imported++;
            } catch (\Exception $e) {
                Log::warning('OSM import: insert failed', ['name' => $name, 'error' => $e->getMessage()]);
                $skipped++;
            }
        }

        return compact('imported', 'updated', 'skipped');
    }

    /** Centroid for nodes (lat/lon), ways/relations (center or bounds avg). */
    private function elementCentroid(array $element): array
    {
        if (isset($element['lat']) && isset($element['lon'])) {
            return [(float) $element['lat'], (float) $element['lon']];
        }
        if (isset($element['center']['lat']) && isset($element['center']['lon'])) {
            return [(float) $element['center']['lat'], (float) $element['center']['lon']];
        }
        if (isset($element['bounds'])) {
            $b = $element['bounds'];
            return [((float) $b['minlat'] + (float) $b['maxlat']) / 2, ((float) $b['minlon'] + (float) $b['maxlon']) / 2];
        }
        return [null, null];
    }

    /** OSM tags -> Nepal Smart Travels category name (matches controller mapping). */
    public function osmToCategory(array $tags): string
    {
        $amenity = $tags['amenity'] ?? null;
        $tourism = $tags['tourism'] ?? null;
        $shop = $tags['shop'] ?? null;
        $leisure = $tags['leisure'] ?? null;
        $historic = $tags['historic'] ?? null;
        $natural = $tags['natural'] ?? null;

        if ($amenity) {
            $map = [
                'restaurant' => 'Restaurant', 'cafe' => 'Cafe', 'fast_food' => 'Food',
                'pub' => 'Pub', 'bar' => 'Bar',
                'hotel' => 'Hotel', 'motel' => 'Hotel', 'hostel' => 'Hotel', 'guest_house' => 'Hotel',
                'hospital' => 'Hospital', 'clinic' => 'Clinic', 'pharmacy' => 'Pharmacy', 'doctors' => 'Clinic',
                'bank' => 'Bank', 'atm' => 'ATM',
                'fuel' => 'Fuel Station', 'taxi' => 'Transport',
                'police' => 'Emergency', 'fire_station' => 'Emergency',
                'bus_station' => 'Transport', 'ferry_terminal' => 'Transport', 'parking' => 'Parking',
                'marketplace' => 'Market', 'theatre' => 'Entertainment', 'cinema' => 'Entertainment',
                'post_office' => 'Services', 'library' => 'Services',
                'school' => 'Education', 'university' => 'Education', 'college' => 'Education',
            ];
            return $map[$amenity] ?? ucfirst($amenity);
        }
        if ($tourism) {
            $map = [
                'attraction' => 'Attraction', 'hotel' => 'Hotel', 'motel' => 'Hotel',
                'hostel' => 'Hotel', 'guest_house' => 'Hotel',
                'museum' => 'Attraction', 'viewpoint' => 'Viewpoint',
                'camp_site' => 'Camping', 'picnic_site' => 'Picnic',
                'theme_park' => 'Entertainment', 'zoo' => 'Attraction',
                'gallery' => 'Attraction', 'artwork' => 'Attraction',
            ];
            return $map[$tourism] ?? ucfirst($tourism);
        }
        if ($shop) {
            return ucfirst($shop);
        }
        if ($leisure) {
            return 'Recreation';
        }
        if ($historic) {
            return 'Historic Site';
        }
        if ($natural) {
            return 'Nature';
        }
        return 'Place';
    }

    /**
     * OSM category -> existing place_categories.id, mirroring the admin
     * import mapping (PlaceController::osmCategoryToDbId) so the app's
     * filter chips/groupings stay unified. Falls back to find-or-create by
     * name only for categories unknown to the app.
     */
    public function resolveCategoryId(string $categoryName): int
    {
        $map = [
            'Restaurant' => 4, 'Cafe' => 4, 'Food' => 4, 'Pub' => 4, 'Bar' => 4,
            'Hotel' => 3,
            'Attraction' => 2, 'Viewpoint' => 2, 'Market' => 2, 'Shopping' => 2,
            'Historic Site' => 2, 'Nature' => 2,
            'Emergency' => 5, 'Hospital' => 5, 'Clinic' => 5, 'Pharmacy' => 5,
            'ATM' => 6, 'Bank' => 6,
            'Fuel Station' => 7,
            'Transport' => 8, 'Parking' => 8, 'Entertainment' => 8,
            'Education' => 8, 'Services' => 8, 'Recreation' => 8,
            'Camping' => 8, 'Picnic' => 8,
        ];

        if (isset($map[$categoryName]) && PlaceCategories::whereKey($map[$categoryName])->exists()) {
            return $map[$categoryName];
        }

        $category = PlaceCategories::where('name', $categoryName)->first();
        if (!$category) {
            $category = PlaceCategories::create([
                'name' => $categoryName,
                'icon' => strtolower(str_replace(' ', '_', $categoryName)),
            ]);
        }
        return $category->id;
    }
}