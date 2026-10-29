<?php

use App\Models\Place;
use App\Services\OsmImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| OSM import - Wikimedia matching metadata
|--------------------------------------------------------------------------
|
| The district importer is the upsert/refresh path used in production. These
| tests call its real upsert method (live local DB, explicit cleanup) and do
| not touch any external API.
|
*/

$GLOBALS['osmimg_osm_ids'] = [];

afterEach(function () {
    $ids = $GLOBALS['osmimg_osm_ids'] ?? [];
    if ($ids !== []) {
        Place::whereIn('osm_id', $ids)->delete();
    }
    $GLOBALS['osmimg_osm_ids'] = [];
});

function osmimg_element(int $id, array $tags): array
{
    return [
        'type' => 'node',
        'id' => $id,
        'lat' => 27.7172,
        'lon' => 85.3240,
        'tags' => $tags,
    ];
}

it('captures wikimedia tags on insert and preserves them on tagless refresh', function () {
    $id = random_int(900000000, 999999999);
    $osmId = 'node/'.$id;
    $name = 'OSM Image Metadata '.Str::lower(Str::random(6));
    $GLOBALS['osmimg_osm_ids'][] = $osmId;

    $service = app(OsmImportService::class);

    $service->upsertElements('Kathmandu', [
        osmimg_element($id, [
            'name' => $name,
            'tourism' => 'attraction',
            'wikidata' => 'q1234',
            'wikipedia' => 'en:Swayambhunath',
            'wikimedia_commons' => 'Category:Swayambhunath',
        ]),
    ]);

    $place = Place::where('osm_id', $osmId)->firstOrFail();
    expect($place->wikidata_id)->toBe('Q1234')
        ->and($place->wikipedia_title)->toBe('en:Swayambhunath')
        ->and($place->commons_category)->toBe('Category:Swayambhunath');

    $result = $service->upsertElements('Kathmandu', [
        osmimg_element($id, [
            'name' => $name,
            'tourism' => 'attraction',
            'description' => 'Refreshed description',
        ]),
    ]);

    $place->refresh();
    expect($result['updated'])->toBe(1)
        ->and($place->description)->toBe('Refreshed description')
        ->and($place->wikidata_id)->toBe('Q1234')
        ->and($place->wikipedia_title)->toBe('en:Swayambhunath')
        ->and($place->commons_category)->toBe('Category:Swayambhunath');

    $service->upsertElements('Kathmandu', [
        osmimg_element($id, [
            'name' => $name,
            'tourism' => 'attraction',
            'wikidata' => '',
            'wikipedia' => '',
            'wikimedia_commons' => '',
        ]),
    ]);

    $place->refresh();
    expect($place->wikidata_id)->toBe('Q1234')
        ->and($place->wikipedia_title)->toBe('en:Swayambhunath')
        ->and($place->commons_category)->toBe('Category:Swayambhunath')
        ->and(Place::where('osm_id', $osmId)->count())->toBe(1);

    expect(DB::table('places')->where('osm_id', $osmId)->exists())->toBeTrue();
});
