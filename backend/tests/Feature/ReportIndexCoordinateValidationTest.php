<?php

/**
 * GET /api/v1/reports — coordinate validation contract.
 *
 * Malformed or out-of-range coordinates must be rejected with 422 instead of
 * being silently cast ("abc" -> 0.0 would filter around Null Island, inventing
 * a location the client never reported). Absent coordinates keep the existing
 * no-location mode (latest nationwide), and valid coordinates keep working.
 *
 * Read-only endpoint — these tests create no rows and need no cleanup.
 */

describe('GET /api/v1/reports coordinate validation', function () {
    it('serves the feed without coordinates (no-location mode)', function () {
        $res = $this->getJson('/api/v1/reports?limit=5');

        $res->assertOk()->assertJsonPath('success', true);
    });

    it('serves the feed with valid in-range coordinates', function () {
        $res = $this->getJson('/api/v1/reports?limit=5&lat=27.7172&lng=85.3240&radius_km=20');

        $res->assertOk()->assertJsonPath('success', true);
    });

    it('rejects non-numeric coordinates with 422', function () {
        $res = $this->getJson('/api/v1/reports?lat=abc&lng=85.3240');

        $res->assertStatus(422);
        expect($res->json('success'))->toBeFalse();
        expect($res->json('message'))->toContain('Invalid latitude/longitude');
    });

    it('rejects an out-of-range latitude with 422', function () {
        $res = $this->getJson('/api/v1/reports?lat=999&lng=85.3240');

        $res->assertStatus(422);
        expect($res->json('success'))->toBeFalse();
    });

    it('rejects an out-of-range longitude with 422', function () {
        $res = $this->getJson('/api/v1/reports?lat=27.7172&lng=181');

        $res->assertStatus(422);
        expect($res->json('success'))->toBeFalse();
    });

    it('rejects array-valued coordinates with 422', function () {
        $res = $this->getJson('/api/v1/reports?lat[]=1&lng=85.3240');

        $res->assertStatus(422);
        expect($res->json('success'))->toBeFalse();
    });

    it('falls back to the default radius for a non-numeric radius', function () {
        $res = $this->getJson('/api/v1/reports?lat=27.7172&lng=85.3240&radius_km=notanumber');

        $res->assertOk()->assertJsonPath('success', true);
    });

    it('falls back to the default radius for a non-positive radius', function () {
        $res = $this->getJson('/api/v1/reports?lat=27.7172&lng=85.3240&radius_km=-3');

        $res->assertOk()->assertJsonPath('success', true);
    });
});
