<?php

use App\Jobs\DiscoverPlaceImagesJob;
use App\Models\Place;
use App\Models\PlaceImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| places:images-backfill
|--------------------------------------------------------------------------
|
| Dry-run must not dispatch, a live run must queue exactly one unique job per
| place, and an immediate rerun must not flood the queue.
|
*/

$GLOBALS['imgbackfill_place_ids'] = [];
$GLOBALS['imgbackfill_category_ids'] = [];

beforeEach(function () {
    Queue::fake();

    $GLOBALS['imgbackfill_place_ids'] = [];
    $GLOBALS['imgbackfill_category_ids'] = [];
});

afterEach(function () {
    $placeIds = $GLOBALS['imgbackfill_place_ids'] ?? [];
    $categoryIds = $GLOBALS['imgbackfill_category_ids'] ?? [];

    if ($placeIds !== []) {
        PlaceImage::whereIn('place_id', $placeIds)->delete();
        Place::whereIn('id', $placeIds)->delete();
    }

    foreach ($categoryIds as $categoryId) {
        DB::table('place_categories')->where('id', $categoryId)->delete();
    }

    $GLOBALS['imgbackfill_place_ids'] = [];
    $GLOBALS['imgbackfill_category_ids'] = [];
});

function imgbackfill_place(): Place
{
    $categoryId = DB::table('place_categories')->insertGetId([
        'name' => 'Backfill Test '.Str::lower(Str::random(6)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $GLOBALS['imgbackfill_category_ids'][] = $categoryId;

    $place = Place::create([
        'uuid' => (string) Str::uuid(),
        'category_id' => $categoryId,
        'name' => 'Backfill Place '.Str::lower(Str::random(6)),
        'district' => 'Kathmandu',
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'source' => 'osm',
        'is_active' => true,
    ]);
    $GLOBALS['imgbackfill_place_ids'][] = $place->id;

    return $place;
}

it('supports dry-run, queues once, and respects the retry window', function () {
    $place = imgbackfill_place();

    $this->artisan('places:images-backfill', [
        '--place' => $place->id,
        '--dry-run' => true,
    ])->assertExitCode(0);

    Queue::assertNothingPushed();

    $this->artisan('places:images-backfill', [
        '--place' => $place->id,
    ])->assertExitCode(0);

    Queue::assertPushedTimes(DiscoverPlaceImagesJob::class, 1);
    Queue::assertPushed(
        DiscoverPlaceImagesJob::class,
        fn ($job) => $job->placeId === $place->id
    );

    expect($place->fresh()->image_next_retry_at)->not->toBeNull();

    $this->artisan('places:images-backfill', [
        '--place' => $place->id,
    ])->assertExitCode(0);

    Queue::assertPushedTimes(DiscoverPlaceImagesJob::class, 1);

    // QueueFake never runs the job, so its ShouldBeUnique lock is still held.
    Cache::lock('laravel_unique_job:'.DiscoverPlaceImagesJob::class.':place-images:'.$place->id)->forceRelease();

    $this->artisan('places:images-backfill', [
        '--place' => $place->id,
        '--force' => true,
    ])->assertExitCode(0);

    Queue::assertPushedTimes(DiscoverPlaceImagesJob::class, 2);
});
