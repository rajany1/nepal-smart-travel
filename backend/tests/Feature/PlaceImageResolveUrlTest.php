<?php

use App\Models\PlaceImage;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| PlaceImage URL resolution
|--------------------------------------------------------------------------
|
| Read paths must handle uploaded files, legacy external URLs, and files that
| disappeared from disk without throwing or producing a broken URL.
|
*/

beforeEach(function () {
    Storage::fake('public');
});

it('passes external image URLs through unchanged', function () {
    expect(PlaceImage::resolveUrl('https://upload.wikimedia.org/example.jpg'))
        ->toBe('https://upload.wikimedia.org/example.jpg');
});

it('resolves an existing public-disk file', function () {
    Storage::disk('public')->put('places/10/photo.jpg', 'image-bytes');

    expect(PlaceImage::resolveUrl('places/10/photo.jpg'))
        ->toBe(asset('storage/places/10/photo.jpg'));
});

it('handles a leading storage prefix without doubling it', function () {
    Storage::disk('public')->put('places/10/photo.jpg', 'image-bytes');

    expect(PlaceImage::resolveUrl('storage/places/10/photo.jpg'))
        ->toBe(asset('storage/places/10/photo.jpg'));
});

it('returns null for a missing local file instead of a broken URL', function () {
    expect(PlaceImage::resolveUrl('places/10/missing.jpg'))->toBeNull();
});

it('returns null for empty input', function () {
    expect(PlaceImage::resolveUrl(null))->toBeNull()
        ->and(PlaceImage::resolveUrl(''))->toBeNull();
});
