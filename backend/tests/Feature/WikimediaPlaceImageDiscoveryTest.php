<?php

use App\Jobs\DiscoverPlaceImagesJob;
use App\Models\Place;
use App\Models\PlaceImage;
use App\Services\PlaceImageMatcher;
use App\Services\Wikimedia\PlaceImageDiscoveryService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Wikimedia place-image discovery
|--------------------------------------------------------------------------
|
| Live local DB with explicit cleanup (no RefreshDatabase). Every Wikimedia
| request is mocked; the test suite never depends on a live external API.
|
*/

$GLOBALS['wmimg_place_ids'] = [];
$GLOBALS['wmimg_category_ids'] = [];

beforeEach(function () {
    Cache::flush();
    Storage::fake('public');
    Queue::fake();

    config([
        'images.min_per_place' => 3,
        'images.max_per_place' => 3,
        'images.min_width' => 1,
        'images.min_height' => 1,
        'images.thumb_width' => 1200,
    ]);

    $GLOBALS['wmimg_place_ids'] = [];
    $GLOBALS['wmimg_category_ids'] = [];
});

afterEach(function () {
    $placeIds = $GLOBALS['wmimg_place_ids'] ?? [];
    $categoryIds = $GLOBALS['wmimg_category_ids'] ?? [];

    if ($placeIds !== []) {
        PlaceImage::whereIn('place_id', $placeIds)->delete();
        Place::whereIn('id', $placeIds)->delete();
    }

    foreach ($categoryIds as $categoryId) {
        DB::table('place_categories')->where('id', $categoryId)->delete();
    }

    $GLOBALS['wmimg_place_ids'] = [];
    $GLOBALS['wmimg_category_ids'] = [];
});

function wmimg_category(): int
{
    $id = DB::table('place_categories')->insertGetId([
        'name' => 'WM Test '.Str::lower(Str::random(6)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $GLOBALS['wmimg_category_ids'][] = $id;

    return $id;
}

function wmimg_place(string $name, string $commonsCategory = 'Category:Swayambhunath'): Place
{
    $place = Place::create([
        'uuid' => (string) Str::uuid(),
        'category_id' => wmimg_category(),
        'name' => $name,
        'district' => 'Kathmandu',
        'latitude' => 27.7149,
        'longitude' => 85.2900,
        'source' => 'osm',
        'is_active' => true,
        'commons_category' => $commonsCategory,
    ]);

    $GLOBALS['wmimg_place_ids'][] = $place->id;

    return $place;
}

function wmimg_png(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
    );
}

function wmimg_fake_http(string $license = 'CC BY-SA 4.0'): void
{
    Http::preventStrayRequests();

    Http::fake(function (Request $request) use ($license) {
        $url = $request->url();
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (str_contains($url, 'commons.wikimedia.org')) {
            if (($query['list'] ?? '') === 'categorymembers') {
                return Http::response([
                    'query' => [
                        'categorymembers' => [
                            ['pageid' => 123, 'ns' => 6, 'title' => 'File:Swayambhunath Test.jpg'],
                        ],
                    ],
                ], 200);
            }

            if (($query['prop'] ?? '') === 'imageinfo|categories') {
                return Http::response([
                    'query' => [
                        'pages' => [[
                            'pageid' => 123,
                            'ns' => 6,
                            'title' => 'File:Swayambhunath Test.jpg',
                            'imageinfo' => [[
                                'url' => 'https://upload.wikimedia.org/wikipedia/commons/original.jpg',
                                'thumburl' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/1200px-Swayambhunath_Test.png',
                                'width' => 1200,
                                'height' => 800,
                                'mime' => 'image/png',
                                'sha1' => str_repeat('a', 40),
                                'extmetadata' => [
                                    'LicenseShortName' => ['value' => $license],
                                    'LicenseUrl' => ['value' => 'https://creativecommons.org/licenses/by-sa/4.0/'],
                                    'Artist' => ['value' => '<a href="#">Test Author</a>'],
                                    'Attribution' => ['value' => 'Test Author, '.$license],
                                    'ImageDescription' => ['value' => 'Swayambhunath Test temple in Kathmandu'],
                                ],
                            ]],
                            'categories' => [['title' => 'Category:Swayambhunath']],
                        ]],
                    ],
                ], 200);
            }
        }

        if (str_starts_with($url, 'https://upload.wikimedia.org/')
            || str_starts_with($url, 'https://thumb.wikimedia.org/')) {
            return Http::response(wmimg_png(), 200, ['Content-Type' => 'image/png']);
        }

        return Http::response(['error' => 'unexpected request'], 500);
    });
}

function wmimg_upload_requests(): int
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => str_starts_with($pair[0]->url(), 'https://upload.wikimedia.org/')
            || str_starts_with($pair[0]->url(), 'https://thumb.wikimedia.org/'))
        ->count();
}

it('downloads an accepted Commons image, stores provenance, and deduplicates reruns', function () {
    wmimg_fake_http();

    $place = wmimg_place('Swayambhunath Test '.Str::lower(Str::random(4)));
    $service = app(PlaceImageDiscoveryService::class);

    $first = $service->discover($place);
    expect($first['stored'])->toBe(1)
        ->and($first['download_failures'])->toBe(0);

    $image = PlaceImage::where('place_id', $place->id)->firstOrFail();
    expect($image->source)->toBe(PlaceImage::SOURCE_WIKIMEDIA)
        ->and($image->license)->toBe('CC BY-SA 4.0')
        ->and($image->author)->toBe('Test Author')
        ->and((float) $image->match_score)->toBeGreaterThan(0)
        ->and($image->source_page_url)->toContain('commons.wikimedia.org')
        ->and($image->original_url)->toStartWith('https://upload.wikimedia.org/')
        ->and($image->image_url)->toStartWith('places/'.$place->id.'/wm-')
        ->and(Storage::disk('public')->exists($image->image_url))->toBeTrue()
        ->and($image->displayUrl())->toContain('/storage/'.$image->image_url);

    $second = $service->discover($place->fresh());
    expect($second['stored'])->toBe(0)
        ->and($second['duplicates'])->toBe(1)
        ->and(PlaceImage::where('place_id', $place->id)->count())->toBe(1)
        ->and(wmimg_upload_requests())->toBe(1);
});

it('rejects images with a non-free licence without downloading them', function () {
    wmimg_fake_http('All rights reserved');

    $place = wmimg_place('Swayambhunath Blocked '.Str::lower(Str::random(4)));
    $service = app(PlaceImageDiscoveryService::class);

    $result = $service->discover($place);

    expect($result['stored'])->toBe(0)
        ->and(PlaceImage::where('place_id', $place->id)->count())->toBe(0)
        ->and(wmimg_upload_requests())->toBe(0);

    expect($place->fresh()->image_discovery_status)->toBe(Place::IMAGE_STATUS_NONE);
});

it('applies the licence allow-list before any download', function () {
    $matcher = app(PlaceImageMatcher::class);

    expect($matcher->licenseAllowed('CC BY-SA 4.0'))->toBeTrue()
        ->and($matcher->licenseAllowed('CC0'))->toBeTrue()
        ->and($matcher->licenseAllowed('Public domain'))->toBeTrue()
        ->and($matcher->licenseAllowed('CC BY-NC 4.0'))->toBeFalse()
        ->and($matcher->licenseAllowed('All rights reserved'))->toBeFalse()
        ->and($matcher->licenseAllowed(null))->toBeFalse();
});

it('serves resolved gallery URLs and an attribution payload on place detail without network calls', function () {
    wmimg_fake_http();

    $place = wmimg_place('Swayambhunath Detail '.Str::lower(Str::random(4)));
    $service = app(PlaceImageDiscoveryService::class);
    $service->discover($place);

    $image = PlaceImage::where('place_id', $place->id)->firstOrFail();

    // Simulate a later retry window so the detail endpoint queues a job.
    $place->forceFill([
        'image_discovery_status' => Place::IMAGE_STATUS_PENDING,
        'image_next_retry_at' => null,
    ])->save();

    $response = $this->getJson('/api/v1/places/'.$place->id);

    $response->assertOk()
        ->assertJsonPath('data.images.0', $image->displayUrl())
        ->assertJsonPath('data.image_gallery.0.license', 'CC BY-SA 4.0');

    Queue::assertPushed(DiscoverPlaceImagesJob::class, 1);
});
