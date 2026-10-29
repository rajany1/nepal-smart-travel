<?php

use App\Models\Place;
use App\Services\PlaceImageMatcher;

/*
|--------------------------------------------------------------------------
| PlaceImageMatcher text-search precision
|--------------------------------------------------------------------------
|
| Commons text search matches file descriptions and categories, not only the
| title. Without a title gate, a mushroom photographed in Chitwan is accepted
| as a photo of Chitwan. These tests pin the conservative behaviour.
|
*/

function placeimage_candidate(array $overrides = []): array
{
    return array_merge([
        'url' => 'https://upload.wikimedia.org/wikipedia/commons/example.jpg',
        'original_url' => 'https://upload.wikimedia.org/wikipedia/commons/example.jpg',
        'width' => 1200,
        'height' => 800,
        'mime' => 'image/jpeg',
        'sha1' => str_repeat('b', 40),
        'title' => 'Example.jpg',
        'description' => '',
        'categories' => [],
        'license' => 'CC BY-SA 4.0',
        'author' => 'Test Author',
        'attribution' => 'Test Author, CC BY-SA 4.0',
        'source_id' => 'commons:1',
        'source_page_url' => 'https://commons.wikimedia.org/?curid=1',
        'via' => 'text_search',
    ], $overrides);
}

function placeimage_place(string $name, string $district, float $lat = 27.529, float $lng = 84.354): Place
{
    return new Place([
        'name' => $name,
        'district' => $district,
        'latitude' => $lat,
        'longitude' => $lng,
    ]);
}

it('rejects an area search result whose title does not name the place', function () {
    $place = placeimage_place('Chitwan', 'Chitwan');
    $candidate = placeimage_candidate([
        'title' => 'A chlorophyllum molybdites.jpg',
        'description' => 'Mushroom photographed in Chitwan',
        'categories' => ['Category:Chitwan District'],
    ]);

    $result = app(PlaceImageMatcher::class)->evaluate($place, $candidate);

    expect($result['accepted'])->toBeFalse()
        ->and($result['reasons'][0])->toContain('name-mismatch-in-title');
});

it('rejects a geotagged photo of another subject even inside the place', function () {
    $place = placeimage_place('Chitwan', 'Chitwan');
    $candidate = placeimage_candidate([
        'via' => 'geosearch',
        'title' => 'Gymnopilus aeruginosus...?.jpg',
        'description' => 'Mushroom photographed in Chitwan',
        'categories' => ['Category:Chitwan District'],
        'photo_lat' => 27.5291,
        'photo_lng' => 84.3542,
    ]);

    $result = app(PlaceImageMatcher::class)->evaluate($place, $candidate);

    expect($result['accepted'])->toBeFalse()
        ->and($result['reasons'][0])->toContain('name-mismatch-in-title');
});

it('accepts a text-search result whose title names the place', function () {
    $place = placeimage_place('Chitwan', 'Chitwan');
    $candidate = placeimage_candidate([
        'title' => 'Chitwan 4.jpg',
        'description' => 'Landscape in Chitwan',
        'categories' => ['Category:Chitwan District'],
    ]);

    $result = app(PlaceImageMatcher::class)->evaluate($place, $candidate);

    expect($result['accepted'])->toBeTrue()
        ->and($result['score'])->toBeGreaterThanOrEqual(0.45);
});

it('tolerates a one-letter difference in the title spelling', function () {
    $place = placeimage_place('Ilam', 'Ilam', 26.9167, 87.9333);
    $candidate = placeimage_candidate([
        'title' => 'Tea garden of Illam - panoramio.jpg',
        'description' => 'Tea garden in Ilam',
        'categories' => ['Category:Ilam'],
    ]);

    $result = app(PlaceImageMatcher::class)->evaluate($place, $candidate);

    expect($result['accepted'])->toBeTrue()
        ->and($result['score'])->toBeGreaterThanOrEqual(0.45);
});
