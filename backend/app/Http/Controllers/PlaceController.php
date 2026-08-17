<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Place;
use App\Models\PlaceReview;
use App\Models\PlaceCategories;
use App\Helpers\GeoHelper;
use App\Jobs\ModerateReview;
use App\Jobs\TranslateContent;
use App\Models\GameSetting;
use App\Services\AchievementService;
use App\Services\TranslationService;
use App\Models\PlaceImage;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlaceController extends Controller
{
    public function categories()
    {
        $categories = PlaceCategories::all();
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Bounding box query - fetch places within a lat/lng rectangle
     * Optimized for map viewport queries.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'phone' => 'nullable|string',
            'website' => 'nullable|url',
            'category' => 'required|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'osm_id' => 'nullable|string|max:50',
        ]);

        $category = PlaceCategories::where('name', $request->category)->first();
        if (!$category) {
            $category = PlaceCategories::create(['name' => $request->category]);
        }

        $osmId = $request->osm_id ? preg_replace('/^osm_/', '', $request->osm_id) : null;

        // Prevent duplicates: exact OSM id already stored (admin import or prior submission)
        if ($osmId) {
            $existing = Place::where('osm_id', $osmId)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => $existing->is_active
                        ? 'This place already exists in our database.'
                        : 'This place has already been submitted and is awaiting review.',
                    'data' => ['id' => (string)$existing->id],
                ], 409);
            }
        }

        // Prevent duplicates: same name near the same coordinates (within ~150m)
        $nearby = Place::whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($request->name))])
            ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= 0.15",
                [$request->latitude, $request->longitude, $request->latitude])
            ->first();
        if ($nearby) {
            return response()->json([
                'success' => false,
                'message' => 'A similar place ("' . $nearby->name . '") already exists nearby.',
            ], 409);
        }

        $place = Place::create([
            'uuid' => (string) Str::uuid(),
            'name' => $request->name,
            'description' => $request->description ?? '',
            'address' => $request->address ?? '',
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'phone' => $request->phone ?? '',
            'category_id' => $category->id,
            'source' => 'user_submitted',
            'osm_id' => $osmId,
            'created_by' => $request->user()?->id,
            'is_active' => false,
            'is_verified' => false,
            'is_featured' => false,
        ]);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('places/' . $place->id, 'public');
                PlaceImage::create([
                    'place_id' => $place->id,
                    'image_url' => $path,
                ]);
            }
        }

        if ($request->description) {
            dispatch(new TranslateContent('place', $place->id, 'description'));
        }

        // Review AI agent - real-time safety guard
        if ($request->user()) {
            $safety = app(\App\Services\ContentSafetyService::class);
            $guardName = $safety->guard($request->user(), (string) $request->input('name', ''), 'place', $place->id, 'name', 'realtime');
            $guardDesc = $safety->guard($request->user(), (string) ($request->description ?? ''), 'place', $place->id, 'description', 'realtime');
            if ($guardName['action'] === 'censored' || $guardDesc['action'] === 'censored') {
                $place->update(['name' => $guardName['censored'] ?? $guardName['text'], 'description' => $guardDesc['censored'] ?? $guardDesc['text']]);
            }
            $safetyPayload = $safety->payload([$guardName, $guardDesc]);
        } else {
            $safetyPayload = ['censored' => false, 'warning' => null, 'account' => 'active', 'until' => null];
        }

        // Log activity
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'place.submitted',
            'resource_type' => 'place',
            'resource_id' => $place->id,
            'description' => 'Submitted place: ' . $place->name,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Place submitted for review.',
            'data' => ['id' => (string)$place->id, 'uuid' => $place->uuid],
            'safety' => $safetyPayload,
        ], 201);
    }

    public function bboxQuery(Request $request)
    {
        $request->validate([
            'min_lat' => 'required|numeric|between:-90,90',
            'max_lat' => 'required|numeric|between:-90,90',
            'min_lng' => 'required|numeric|between:-180,180',
            'max_lng' => 'required|numeric|between:-180,180',
            'category' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $minLat = $request->min_lat;
        $maxLat = $request->max_lat;
        $minLng = $request->min_lng;
        $maxLng = $request->max_lng;
        $limit = $request->limit ?? 100;

        $query = Place::with(['category', 'images'])->active()
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng]);

        $this->applyShowOnMapFilter($query, $request->user()?->id);

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->category . '%');
            });
        }

        $places = $query->orderBy('is_featured', 'desc')
            ->orderBy('average_rating', 'desc')
            ->limit($limit)
            ->get();

        $data = $places->map(fn($place) => [
            'id' => $place->id,
            'uuid' => $place->uuid,
            'name' => $place->name,
            'description' => $place->description,
            'address' => $place->address,
            'district' => $place->district,
            'latitude' => (float)$place->latitude,
            'longitude' => (float)$place->longitude,
            'phone' => $place->phone,
            'average_rating' => $place->average_rating !== null ? (float)$place->average_rating : null,
            'total_reviews' => $place->total_reviews,
            'category' => $place->category ? $place->category->name : null,
            'is_verified' => $place->is_verified,
            'is_featured' => $place->is_featured,
            'source' => $place->source ?? 'admin',
            'images' => $this->placeImageUrls($place->images),
        ])->toArray();

        $data = TranslationService::attachToPlaces($data);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Legacy nearby - only admin places
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius_km' => 'nullable|numeric|min:0.1|max:100',
            'category_id' => 'nullable|integer|exists:place_categories,id',
            'limit' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius_km ?? 5.0;
        $limit = $request->limit ?? 50;

        $query = Place::query()->with(['category', 'images'])->active();

        $this->applyShowOnMapFilter($query, $request->user()?->id);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%");
            });
        }

        $places = $query->selectRaw(
            "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
            [$lat, $lng, $lat]
        )
        ->having('distance', '<=', $radius)
        ->orderBy('is_featured', 'desc')
        ->orderBy('distance')
        ->limit($limit)
        ->get();

        $data = $places->map(fn($place) => [
            'id' => $place->id,
            'uuid' => $place->uuid,
            'name' => $place->name,
            'description' => $place->description,
            'address' => $place->address,
            'district' => $place->district,
            'latitude' => $place->latitude !== null ? (float)$place->latitude : null,
            'longitude' => $place->longitude !== null ? (float)$place->longitude : null,
            'phone' => $place->phone,
            'average_rating' => $place->average_rating !== null ? (float)$place->average_rating : null,
            'total_reviews' => $place->total_reviews,
            'distance_km' => round($place->distance, 2),
            'category' => $place->category ? $place->category->name : null,
            'is_verified' => $place->is_verified,
            'is_featured' => $place->is_featured,
            'is_active' => $place->is_active,
            'source' => $place->source ?? 'admin',
            'images' => $this->placeImageUrls($place->images),
        ])->toArray();

        $data = TranslationService::attachToPlaces($data);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Respect the "Show on Map" privacy setting: places submitted by users who
     * opted out are hidden from public listings. OSM/admin places (no creator)
     * and the requesting user's own places are always shown.
     */
    private function applyShowOnMapFilter($query, $viewerId = null)
    {
        $hiddenAuthorIds = \App\Models\User::whereRaw("JSON_EXTRACT(settings, '$.show_on_map') = 'false'")
            ->pluck('id');

        $query->where(function ($q) use ($hiddenAuthorIds, $viewerId) {
            $q->whereNull('created_by')
              ->orWhere('created_by', $viewerId)
              ->orWhereNotIn('created_by', $hiddenAuthorIds);
        });

        return $query;
    }

    /**
     * COMBINED nearby: OpenStreetMap data + Admin places
     * This is the main endpoint used by the mobile app.
     */
    public function nearbyCombined(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
            'category_id' => 'nullable|integer|exists:place_categories,id',
            'search' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;
        $radius = $request->radius_km ?? 5.0;
        $categoryId = $request->category_id;
        $search = $request->search;
        $limit = $request->limit ?? 50;

        // 1. Fetch from OpenStreetMap Overpass API
        $osmPlaces = $this->fetchOsmNearby($lat, $lng, $radius);

        // Filter OSM results by search if provided
        if ($search) {
            $osmPlaces = array_filter($osmPlaces, fn($p) =>
                stripos($p['name'], $search) !== false ||
                stripos($p['description'] ?? '', $search) !== false ||
                stripos($p['category'], $search) !== false
            );
        }

        // Filter OSM results by category if provided
        if ($categoryId) {
            $categoryName = \App\Models\PlaceCategories::find($categoryId)?->name;
            if ($categoryName && $categoryName !== 'All') {
                $osmPlaces = array_filter($osmPlaces, fn($p) =>
                    strcasecmp($p['category'], $categoryName) === 0
                );
            }
        }

        $osmPlaces = array_values($osmPlaces);

        // 2. Fetch Admin places with Haversine distance
        $adminQuery = Place::with(['category', 'images'])->active()
            ->selectRaw(
                "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$lat, $lng, $lat]
            )
            ->having('distance', '<=', $radius);

        $this->applyShowOnMapFilter($adminQuery, $request->user()?->id);

        if ($categoryId) {
            $adminQuery->where('category_id', $categoryId);
        }
        if ($search) {
            $adminQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%");
            });
        }

        $adminPlaces = $adminQuery->orderBy('is_featured', 'desc')
            ->orderBy('distance')
            ->limit($limit)
            ->get()
            ->map(fn($place) => [
                'id' => 'admin_' . $place->id,
                'name' => $place->name,
                'description' => $place->description,
                'address' => $place->address,
                'district' => $place->district,
                'latitude' => $place->latitude !== null ? (float)$place->latitude : null,
                'longitude' => $place->longitude !== null ? (float)$place->longitude : null,
                'phone' => $place->phone,
                'average_rating' => $place->average_rating !== null ? (float)$place->average_rating : null,
                'total_reviews' => $place->total_reviews,
                'distance_km' => round($place->distance, 2),
                'category' => $place->category ? $place->category->name : 'Place',
                'is_verified' => $place->is_verified,
                'is_featured' => $place->is_featured,
                'source' => 'admin',
                'images' => $this->placeImageUrls($place->images),
            ])->toArray();

        // Attach Nepali translations (name_ne, description_ne, etc.)
        $adminPlaces = TranslationService::attachToPlaces($adminPlaces);

        // 2b. Cross-source dedup: drop OSM results already stored in our DB
        // (matched by osm_id, or by same name within ~100m for legacy admin rows).
        $dbPlaces = Place::select('id', 'osm_id', 'name', 'latitude', 'longitude')
            ->whereNotNull('osm_id')
            ->orWhereNotNull('latitude')
            ->get();
        $dbOsmIds = $dbPlaces->map(fn($p) => $p->osm_id)
            ->filter()
            ->map(fn($v) => str_replace('osm_', '', (string)$v))
            ->flip();
        $dbByName = [];
        foreach ($dbPlaces as $p) {
            if (!$p->name || $p->latitude === null || $p->longitude === null) continue;
            $dbByName[mb_strtolower(trim($p->name))][] = [(float)$p->latitude, (float)$p->longitude];
        }
        $osmPlaces = array_values(array_filter($osmPlaces, function ($p) use ($dbOsmIds, $dbByName) {
            $osmKey = str_replace('osm_', '', $p['id'] ?? '');
            if (isset($dbOsmIds[$osmKey])) return false;
            $key = mb_strtolower(trim($p['name'] ?? ''));
            foreach ($dbByName[$key] ?? [] as [$dLat, $dLng]) {
                if ($this->haversineDistance($dLat, $dLng, $p['latitude'], $p['longitude']) <= 0.1) {
                    return false;
                }
            }
            return true;
        }));

        // 3. Merge all sources and sort purely by distance from the user
        // (no OSM-on-top / DB-on-bottom bias — closest place wins).
        $combined = array_merge($adminPlaces, $osmPlaces);
        usort($combined, fn($a, $b) =>
            ($a['distance_km'] ?? PHP_FLOAT_MAX) <=> ($b['distance_km'] ?? PHP_FLOAT_MAX)
        );

        // Limit total results
        $combined = array_slice($combined, 0, $limit);

        return response()->json([
            'success' => true,
            'data' => $combined,
        ]);
    }

    /**
     * Fetch nearby places from OpenStreetMap Overpass API
     */
    private function fetchOsmNearby(float $lat, float $lng, float $radiusKm): array
    {
        // Cache key based on approximate location (rounded to 3 decimal places ~ 111m)
        $cacheKey = 'osm_nearby_' . round($lat, 3) . '_' . round($lng, 3) . '_' . $radiusKm;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $radiusMeters = round($radiusKm * 1000);

        // OSM category mapping for relevant place types
        $queries = [
            // Amenities
            "node[\"amenity\"~\"" . implode('|', [
                'restaurant', 'cafe', 'fast_food', 'pub', 'bar',
                'hotel', 'motel', 'hostel', 'guest_house',
                'hospital', 'clinic', 'pharmacy', 'doctors',
                'bank', 'atm', 'fuel', 'taxi',
                'police', 'fire_station', 'embassy',
                'marketplace', 'theatre', 'cinema', 'community_centre',
                'bus_station', 'ferry_terminal', 'parking',
                'post_office', 'library', 'place_of_worship',
                'school', 'university', 'college',
            ]) . "\"](around:{$radiusMeters},{$lat},{$lng});",

            // Tourism
            "node[\"tourism\"~\"" . implode('|', [
                'attraction', 'hotel', 'motel', 'hostel',
                'guest_house', 'information', 'museum',
                'viewpoint', 'picnic_site', 'camp_site',
                'caravan_site', 'wilderness_hut', 'alpine_hut',
                'artwork', 'gallery', 'theme_park', 'zoo',
            ]) . "\"](around:{$radiusMeters},{$lat},{$lng});",

            // Shops
            "node[\"shop\"~\"" . implode('|', [
                'supermarket', 'convenience', 'mall', 'department_store',
                'clothes', 'electronics', 'gift', 'souvenir',
            ]) . "\"](around:{$radiusMeters},{$lat},{$lng});",

            // Leisure
            "node[\"leisure\"](around:{$radiusMeters},{$lat},{$lng});",

            // Historic
            "node[\"historic\"](around:{$radiusMeters},{$lat},{$lng});",

            // Natural (viewpoints)
            "node[\"natural\"~\"" . implode('|', ['peak', 'volcano', 'bay', 'cape', 'beach']) . "\"](around:{$radiusMeters},{$lat},{$lng});",
        ];

        $overpassQuery = "[out:json][timeout:25];(" . implode('', $queries) . ");out body 100;";

        // Try multiple Overpass mirrors so rate limits (429) or outages (504) on
        // one server don't silently kill OSM results for the app.
        $endpoints = [
            'https://overpass-api.de/api/interpreter',
            'https://maps.mail.ru/osm/tools/overpass/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: NepalSmartTravel/1.0",
                        'content' => 'data=' . urlencode($overpassQuery),
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ]
                ];
                $context = stream_context_create($opts);
                $responseBody = @file_get_contents($endpoint, false, $context);

                if ($responseBody === false) {
                    \Log::warning('OSM Overpass API connection failed', ['endpoint' => $endpoint]);
                    continue;
                }

                $httpCode = 200;
                if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
                    $httpCode = (int)$m[0];
                }

                if ($httpCode === 429) {
                    \Log::warning('OSM Overpass API rate limited', ['endpoint' => $endpoint]);
                    usleep(700000); // brief backoff before trying the next mirror
                    continue;
                }

                if ($httpCode !== 200) {
                    \Log::warning('OSM Overpass API request failed', ['status' => $httpCode, 'endpoint' => $endpoint]);
                    continue;
                }

                $data = json_decode($responseBody, true);
                if (!is_array($data) || !isset($data['elements'])) {
                    \Log::warning('OSM Overpass API returned invalid payload', ['endpoint' => $endpoint]);
                    continue;
                }
                $elements = $data['elements'];

                $places = $this->parseOsmElements($lat, $lng, $elements);
                if (count($places) < count($elements)) {
                    \Log::warning('OSM Overpass API returned partial data', [
                        'endpoint' => $endpoint,
                        'elements' => count($elements),
                        'parsed' => count($places),
                    ]);
                }

                // Cache OSM results for 10 minutes (only non-empty results,
                // so a flaky/empty response never blocks fresh fetches)
                if (!empty($places)) {
                    Cache::put($cacheKey, $places, 600);
                }

                return $places;

            } catch (\Exception $e) {
                \Log::error('OSM Overpass API error: ' . $e->getMessage() . ' @ ' . $endpoint);
            }
        }

        \Log::warning('OSM Overpass API: all endpoints failed');
        return [];
    }

    /**
     * Parse Overpass elements into place arrays (shared by mirrors)
     */
    private function parseOsmElements(float $lat, float $lng, array $elements): array
    {
        $places = [];
        $seen = [];

        foreach ($elements as $element) {
            $tags = $element['tags'] ?? [];
            $elemLat = $element['lat'] ?? null;
            $elemLng = $element['lon'] ?? null;

            if (!$elemLat || !$elemLng) continue;

            // Determine name
            $name = $tags['name'] ?? $tags['name:en'] ?? null;
            if (!$name) continue; // Skip unnamed nodes

            // Deduplicate by OSM id
            $osmId = $element['type'] . '/' . $element['id'];
            if (isset($seen[$osmId])) continue;
            $seen[$osmId] = true;

            // Determine category
            $category = $this->osmToCategory($tags);

            // Calculate distance
            $distance = $this->haversineDistance($lat, $lng, $elemLat, $elemLng);

            // Build address from tags
            $address = implode(', ', array_filter([
                $tags['addr:street'] ?? null,
                $tags['addr:city'] ?? null,
            ]));

            // Rating - OSM doesn't have ratings, but some have `rating` tag
            $rating = null;
            if (isset($tags['rating'])) {
                $rating = (float)$tags['rating'];
            }

            $places[] = [
                'id' => 'osm_' . $osmId,
                'name' => $name,
                'description' => $tags['description'] ?? $tags['note'] ?? null,
                'address' => $address ?: null,
                'district' => $tags['addr:city'] ?? $tags['addr:district'] ?? null,
                'latitude' => $elemLat,
                'longitude' => $elemLng,
                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                'average_rating' => $rating,
                'total_reviews' => 0,
                'distance_km' => round($distance, 2),
                'category' => $category,
                'is_verified' => false,
                'is_featured' => false,
                'source' => 'osm',
                'images' => [],
            ];
        }

        // Sort by distance
        usort($places, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return $places;
    }

    /**
     * Map OSM tags to human-readable category
     */
    private function osmToCategory(array $tags): string
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
        if ($leisure) return 'Recreation';
        if ($historic) return 'Historic Site';
        if ($natural) return 'Nature';
        return 'Place';
    }

    /**
     * Map OSM category string to DB place_categories.id
     */
    private function osmCategoryToDbId(?string $category): int
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
        return $map[$category] ?? 2;
    }

    /**
     * Haversine distance between two coordinates
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return GeoHelper::haversineKm($lat1, $lng1, $lat2, $lng2);
    }

    public function featured(Request $request)
    {
        $request->validate([
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $limit = $request->limit ?? 10;
        $query = Place::with('category')->featured()->active();

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $query->selectRaw(
                "*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$lat, $lng, $lat]
            )->orderBy('distance');
        } else {
            $query->latest();
        }

        $places = $query->limit($limit)->get();

        $data = $places->map(fn($place) => [
            'id' => $place->id,
            'uuid' => $place->uuid,
            'name' => $place->name,
            'description' => $place->description,
            'address' => $place->address,
            'district' => $place->district,
            'latitude' => $place->latitude !== null ? (float)$place->latitude : null,
            'longitude' => $place->longitude !== null ? (float)$place->longitude : null,
            'average_rating' => $place->average_rating !== null ? (float)$place->average_rating : null,
            'total_reviews' => $place->total_reviews,
            'distance_km' => isset($place->distance) ? round($place->distance, 2) : null,
            'category' => $place->category ? $place->category->name : null,
            'is_verified' => $place->is_verified,
            'is_featured' => true,
            'source' => 'admin',
        ])->toArray();

        $data = TranslationService::attachToPlaces($data);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function addReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Auto-create Place from OSM if not in DB yet
        if (str_starts_with($id, 'osm_')) {
            $osmId = substr($id, 4);
            $place = Place::where('osm_id', $osmId)->first();

            if (!$place) {
                $request->validate([
                    'name' => 'required|string|max:255',
                    'latitude' => 'required|numeric|between:-90,90',
                    'longitude' => 'required|numeric|between:-180,180',
                ]);

                $categoryId = $this->osmCategoryToDbId($request->input('category'));
                $place = Place::create([
                    'uuid' => (string) Str::uuid(),
                    'category_id' => $categoryId,
                    'created_by' => $request->user()->id,
                    'name' => $request->input('name'),
                    'description' => $request->input('description', ''),
                    'address' => $request->input('address', ''),
                    'district' => $request->input('district', ''),
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'phone' => $request->input('phone', ''),
                    'source' => 'osm',
                    'osm_id' => $osmId,
                    // BE-18: OSM-auto places must pass moderation like user submissions
                    'is_active' => false,
                    'is_verified' => false,
                    'is_featured' => false,
                ]);
            }

            $id = (string) $place->id;
        }

        $place = Place::findOrFail($id);
        $user = $request->user();

        // Re-submitting an existing review resets moderation so it gets re-moderated
        $review = PlaceReview::updateOrCreate(
            ['place_id' => $place->id, 'user_id' => $user->id],
            [
                'title' => $request->title,
                'description' => $request->description,
                'rating' => $request->rating,
                'moderation_status' => null,
                'moderated_at' => null,
            ]
        );

        // Review AI agent - real-time safety guard (censors + records + escalates)
        $safety = app(\App\Services\ContentSafetyService::class);
        $guardTitle = $safety->guard($user, (string) $request->title, 'place_review', $review->id, 'title', 'realtime');
        $guardDesc = $safety->guard($user, (string) $request->description, 'place_review', $review->id, 'description', 'realtime');
        if ($guardTitle['action'] === 'censored' || $guardDesc['action'] === 'censored') {
            $review->update(['title' => $guardTitle['censored'] ?? $guardTitle['text'], 'description' => $guardDesc['censored'] ?? $guardDesc['text']]);
        }
        $safetyPayload = $safety->payload([$guardTitle, $guardDesc]);

        // Award review XP only for the first submission of this review
        if ($review->wasRecentlyCreated) {
            $reviewXp = GameSetting::getValue('review_xp', 3);
            app(AchievementService::class)->awardXp(
                $user, $reviewXp, 'review_created',
                "Reviewed: {$place->name}", $review
            );
        }

        dispatch(new ModerateReview($review->id));
        if ($request->description) {
            dispatch(new TranslateContent('place_review', $review->id, 'description'));
        }

        // Handle uploaded images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('review-images', 'public');
                // If you have a ReviewImage model, attach here
            }
        }

        // Recalculate average over approved reviews only
        $place->average_rating = $place->approvedReviews()->avg('rating');
        $place->total_reviews = $place->approvedReviews()->count();
        $place->save();

        return response()->json([
            'success' => true,
            'data' => [
                'review_id' => (string)$review->id,
                'place_id' => (string)$place->id,
                'user_id' => (string)$user->id,
                'user_name' => $user->name ?? $user->email,
                'user_avatar' => $user->avatar,
                'title' => $review->title,
                'description' => $review->description,
                'rating' => $review->rating,
                'images' => [],
                'created_at' => $review->created_at->toIso8601String(),
                'average_rating' => round((float)$place->average_rating, 1),
                'total_reviews' => $place->total_reviews,
            ],
            'safety' => $safetyPayload,
        ]);
    }

    public function reviews($id)
    {
        if (str_starts_with($id, 'osm_')) {
            $osmId = substr($id, 4);
            $place = Place::where('osm_id', $osmId)->first();
            if (!$place) {
                return response()->json(['success' => true, 'data' => []]);
            }
            $id = (string) $place->id;
        }

        $reviews = PlaceReview::with('user')
            ->where('place_id', $id)
            ->where(function ($q) {
                $q->whereNull('moderation_status')
                    ->orWhere('moderation_status', 'approved');
            })
            ->latest()
            ->get()
            ->map(fn($review) => [
                'id' => (string) $review->id,
                'title' => $review->title,
                'description' => $review->description,
                'rating' => (int) $review->rating,
                'user_name' => $review->user?->name ?? 'Anonymous',
                'user_avatar' => $review->user?->avatar,
                'created_at' => $review->created_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $reviews,
        ]);
    }

    /**
     * Return absolute storage URLs for place images (relative paths would 404 in the app).
     */
    private function placeImageUrls($images): array
    {
        return $images
            ->map(fn($img) => asset('storage/' . $img->image_url))
            ->toArray();
    }

    public function show($id)
    {
        if (str_starts_with($id, 'osm_')) {
            $osmId = substr($id, 4);
            $place = Place::with(['category', 'approvedReviews', 'images'])->where('osm_id', $osmId)->first();
            if (!$place) {
                // IN-09: unreviewed OSM POI — no 404; client renders its cached OSM data as "be the first to review"
                return response()->json(['success' => true, 'data' => null]);
            }
        } else {
            $place = Place::with(['category', 'approvedReviews', 'images'])->findOrFail($id);
        }
        $data = TranslationService::attachToModel($place, 'place');
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function translations($id)
    {
        $translations = \App\Models\ModelTranslation::where('translatable_type', 'place')
            ->where('translatable_id', $id)
            ->where('locale', 'ne')
            ->get(['field', 'value']);

        return response()->json([
            'success' => true,
            'data' => $translations,
        ]);
    }

    public function osmStatus(Request $request)
    {
        $request->validate([
            'osm_ids' => 'required|array',
            'osm_ids.*' => 'string|max:50',
        ]);

        $statuses = [];

        foreach ($request->osm_ids as $osmId) {
            // Normalize: app sends "osm_node/123", DB stores "node/123"
            $dbOsmId = preg_replace('/^osm_/', '', $osmId);

            $place = Place::where('osm_id', $dbOsmId)->first();

            if (!$place) {
                $statuses[$osmId] = 'none';
            } elseif ($place->is_active) {
                $statuses[$osmId] = 'approved';
            } else {
                $rejected = \App\Models\ModerationQueue::where('content_type', 'place')
                    ->where('content_id', $place->id)
                    ->where('status', 'rejected')
                    ->exists();

                $statuses[$osmId] = $rejected ? 'rejected' : 'pending';
            }
        }

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    public function storeCorrection(Request $request)
    {
        $request->validate([
            'place_id' => 'nullable|integer|exists:places,id',
            'osm_id' => 'nullable|string|max:50',
            'place_name' => 'required|string|max:255',
            'correction_type' => 'required|in:wrong_location,wrong_name,closed,duplicate,outdated_info,other',
            'description' => 'required|string|max:1000',
            'suggested_name' => 'nullable|string|max:255',
            'suggested_latitude' => 'nullable|numeric|between:-90,90',
            'suggested_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Please log in to report a problem.'], 401);
        }

        $placeId = $request->place_id;
        $osmId = $request->osm_id ? preg_replace('/^osm_/', '', $request->osm_id) : null;

        $correction = \App\Models\PlaceCorrection::create([
            'place_id' => $placeId,
            'osm_id' => $osmId,
            'place_name' => $request->place_name,
            'user_id' => $user->id,
            'correction_type' => $request->correction_type,
            'description' => $request->description,
            'suggested_name' => $request->suggested_name,
            'suggested_latitude' => $request->suggested_latitude,
            'suggested_longitude' => $request->suggested_longitude,
            'status' => 'pending',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'place.correction-submitted',
            'resource_type' => 'place_correction',
            'resource_id' => $correction->id,
            'description' => 'Correction requested for: ' . $request->place_name,
            'ip_address' => $request->ip(),
        ]);

        // Review AI agent - real-time safety guard
        $safety = app(\App\Services\ContentSafetyService::class);
        $guardName = $safety->guard($user, (string) $request->place_name, 'place_correction', $correction->id, 'place_name', 'realtime');
        $guardDesc = $safety->guard($user, (string) $request->description, 'place_correction', $correction->id, 'description', 'realtime');
        $guardSuggested = $safety->guard($user, (string) ($request->suggested_name ?? ''), 'place_correction', $correction->id, 'suggested_name', 'realtime');
        if ($guardName['action'] === 'censored' || $guardDesc['action'] === 'censored' || $guardSuggested['action'] === 'censored') {
            $correction->update([
                'place_name' => $guardName['censored'] ?? $guardName['text'],
                'description' => $guardDesc['censored'] ?? $guardDesc['text'],
                'suggested_name' => $guardSuggested['censored'] ?? $guardSuggested['text'],
            ]);
        }
        $safetyPayload = $safety->payload([$guardName, $guardDesc, $guardSuggested]);

        return response()->json([
            'success' => true,
            'message' => 'Correction request submitted. Our team will review it.',
            'data' => ['id' => $correction->id, 'status' => 'pending'],
            'safety' => $safetyPayload,
        ], 201);
    }

    public function myCorrections(Request $request)
    {
        $user = $request->user();
        $corrections = \App\Models\PlaceCorrection::where('user_id', $user?->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'place_id' => $c->place_id,
                'osm_id' => $c->osm_id,
                'place_name' => $c->place_name,
                'correction_type' => $c->correction_type,
                'description' => $c->description,
                'suggested_name' => $c->suggested_name,
                'status' => $c->status,
                'admin_note' => $c->admin_note,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $corrections,
        ]);
    }
}