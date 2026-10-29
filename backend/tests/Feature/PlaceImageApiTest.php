<?php

use App\Jobs\DiscoverPlaceImagesJob;
use App\Models\Place;
use App\Models\PlaceImage;
use App\Services\OsmOverpassService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Place image API integration
|--------------------------------------------------------------------------
|
| Verifies the two hard boundaries of the hybrid design:
|  1. Place detail queues discovery instead of calling Wikimedia inline.
|  2. Nearby never calls Wikimedia (and therefore never gains API latency).
|
*/

$GLOBALS['imgapi_place_ids'] = [];
$GLOBALS['imgapi_category_ids'] = [];

beforeEach(function () {
    Cache::flush();
    Queue::fake();
    Http::fake();

    $GLOBALS['imgapi_place_ids'] = [];
    $GLOBALS['imgapi_category_ids'] = [];
});

afterEach(function () {
    $placeIds = $GLOBALS['imgapi_place_ids'] ?? [];
    $categoryIds = $GLOBALS['imgapi_category_ids'] ?? [];

    if ($placeIds !== []) {
        PlaceImage::whereIn('place_id', $placeIds)->delete();
        Place::whereIn('id', $placeIds)->delete();
    }

    foreach ($categoryIds as $categoryId) {
        DB::table('place_categories')->where('id', $categoryId)->delete();
    }

    $GLOBALS['imgapi_place_ids'] = [];
    $GLOBALS['imgapi_category_ids'] = [];
});

function imgapi_place(): Place
{
    $categoryId = DB::table('place_categories')->insertGetId([
        'name' => 'Image API Test '.Str::lower(Str::random(6)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $GLOBALS['imgapi_category_ids'][] = $categoryId;

    $place = Place::create([
        'uuid' => (string) Str::uuid(),
        'category_id' => $categoryId,
        'name' => 'Image API Place '.Str::lower(Str::random(6)),
        'district' => 'Kathmandu',
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'source' => 'osm',
        'is_active' => true,
    ]);
    $GLOBALS['imgapi_place_ids'][] = $place->id;

    return $place;
}

it('queues image discovery from place detail without any network call', function () {
    $place = imgapi_place();

    $response = $this->getJson('/api/v1/places/'.$place->id);

    $response->assertOk();
    Queue::assertPushed(DiscoverPlaceImagesJob::class, fn ($job) => $job->placeId === $place->id);
    Http::assertNothingSent();
});

it('never calls Wikimedia from the nearby-combined endpoint', function () {
    $place = imgapi_place();

    $bbox = OsmOverpassService::radiusToBbox((float) $place->latitude, (float) $place->longitude, 1.0);
    $bboxKey = OsmOverpassService::bboxCacheKey(
        $bbox['minLat'],
        $bbox['minLng'],
        $bbox['maxLat'],
        $bbox['maxLng'],
        1.0
    );
    Cache::put($bboxKey, json_encode(['elements' => []]), 120);

    $response = $this->getJson(
        '/api/v1/places/nearby-combined?lat='.$place->latitude.'&lng='.$place->longitude.'&radius_km=1'
    );

    $response->assertOk();

    Http::assertNotSent(function (Request $request) {
        $url = $request->url();

        return str_contains($url, 'wikimedia.org')
            || str_contains($url, 'wikidata.org')
            || str_contains($url, 'wikipedia.org');
    });

    Queue::assertNotPushed(DiscoverPlaceImagesJob::class);
});
