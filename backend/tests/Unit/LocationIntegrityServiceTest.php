<?php

use App\Services\LocationIntegrityService;

/*
|--------------------------------------------------------------------------
| LocationIntegrityService unit tests
|--------------------------------------------------------------------------
|
| The service turns UNTRUSTED client signals plus server-side context into
| genuine | suspicious | cannot_determine. Key invariants guarded here:
|
|   - a missing/unusable client verdict is NEVER reported as genuine
|   - a client mock=false claim alone is not proof of a genuine location
|   - IP/network data has no influence (VPNs must not flag reports)
|   - implausible accuracy/timestamps, photo conflicts and impossible
|     movement raise the risk signal
|
| evaluate() is pure (no DB, no facades) so it runs in the Unit suite.
|
*/

function liuService(): LocationIntegrityService
{
    return new LocationIntegrityService;
}

function liuContext(array $overrides = []): array
{
    return array_merge([
        'client' => [
            'status' => 'genuine',
            'mock_location_detected' => false,
            'detection_source' => 'android',
        ],
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'location_timestamp' => date('c'),
        'location_accuracy' => 8.5,
        'gps_verification_status' => 'verified',
        'previous' => null,
    ], $overrides);
}

test('clean genuine signals evaluate to genuine with no risk signals', function () {
    $result = liuService()->evaluate(liuContext());

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_GENUINE)
        ->and($result['signals'])->toBe([])
        ->and($result['mock_location_detected'])->toBeFalse()
        ->and($result['source'])->toBe('android');
});

test('missing client payload evaluates to cannot_determine, never genuine', function () {
    $result = liuService()->evaluate(liuContext(['client' => null]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_CANNOT_DETERMINE)
        ->and($result['status'])->not->toBe(LocationIntegrityService::STATUS_GENUINE)
        ->and($result['signals'])->toBe([])
        ->and($result['mock_location_detected'])->toBeNull();
});

test('client mock_location_detected=false alone is not proof of genuine', function () {
    // A modified APK can send just the boolean without a detector verdict.
    $result = liuService()->evaluate(liuContext([
        'client' => ['mock_location_detected' => false],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_CANNOT_DETERMINE)
        ->and($result['signals'])->toBe([]);
});

test('client reporting mock_location_detected=true is suspicious', function () {
    $result = liuService()->evaluate(liuContext([
        'client' => [
            'status' => 'genuine',
            'mock_location_detected' => true,
            'detection_source' => 'android',
        ],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('mock_location_reported')
        ->and(liuService()->fraudReasons($result['signals']))->toContain('mock_location_detected');
});

test('client status mock_detected is suspicious even if the boolean says false', function () {
    $result = liuService()->evaluate(liuContext([
        'client' => [
            'status' => 'mock_detected',
            'mock_location_detected' => false,
        ],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('mock_location_reported');
});

test('client cannot_determine and unknown map to cannot_determine', function (string $clientStatus) {
    $result = liuService()->evaluate(liuContext([
        'client' => ['status' => $clientStatus, 'mock_location_detected' => false],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_CANNOT_DETERMINE)
        ->and($result['signals'])->toBe([]);
})->with(['cannot_determine', 'unknown']);

test('a tampered client status string is suspicious', function () {
    $result = liuService()->evaluate(liuContext([
        'client' => ['status' => 'totally_real_not_mock'],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('invalid_client_status');
});

test('photo EXIF/capture conflict raises the risk signal', function () {
    $result = liuService()->evaluate(liuContext([
        'gps_verification_status' => 'mismatched',
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('photo_location_conflict')
        ->and(liuService()->fraudReasons($result['signals']))->toBe(['location_integrity_suspicious']);
});

test('missing photo EXIF data is NOT a risk signal', function () {
    $result = liuService()->evaluate(liuContext([
        'gps_verification_status' => 'no_gps_data',
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_GENUINE)
        ->and($result['signals'])->toBe([]);
});

test('a fix timestamp in the future is suspicious', function () {
    $result = liuService()->evaluate(liuContext([
        'location_timestamp' => date('c', time() + 3600),
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('location_timestamp_in_future');
});

test('a stale fix timestamp is suspicious', function () {
    $result = liuService()->evaluate(liuContext([
        'location_timestamp' => date('c', time() - 48 * 3600),
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('stale_location_timestamp');
});

test('implausible accuracy values are suspicious', function (float $accuracy) {
    $result = liuService()->evaluate(liuContext([
        'location_accuracy' => $accuracy,
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('implausible_location_accuracy');
})->with([0.0, -3.0, 25000.0]);

test('plausible accuracy keeps the report genuine', function () {
    $result = liuService()->evaluate(liuContext(['location_accuracy' => 8.5]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_GENUINE)
        ->and($result['signals'])->toBe([]);
});

test('IP/network metadata never influences the evaluation (VPN rule)', function () {
    // A foreign/VPN IP next to a genuine Nepali GPS fix must not flag the
    // report: the evaluator receives no IP concept at all — unknown keys
    // (simulating anything else the controller might pass) are ignored.
    $result = liuService()->evaluate(liuContext([
        'ip_address' => '203.0.113.7',
        'country' => 'US',
        'is_vpn' => true,
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_GENUINE)
        ->and($result['signals'])->toBe([]);
});

test('impossible movement between consecutive reports is suspicious', function () {
    // Kathmandu → western Nepal (~470km) in 20 minutes ≈ 1400 km/h.
    $result = liuService()->evaluate(liuContext([
        'latitude' => 28.7,
        'longitude' => 80.6,
        'previous' => [
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'created_at' => date('c', time() - 20 * 60),
        ],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('impossible_movement')
        ->and(liuService()->fraudReasons($result['signals']))->toContain('impossible_movement');
});

test('ordinary short-distance movement is not flagged', function () {
    $result = liuService()->evaluate(liuContext([
        'latitude' => 27.7272,
        'longitude' => 85.3340,
        'previous' => [
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'created_at' => date('c', time() - 30 * 60),
        ],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_GENUINE)
        ->and($result['signals'])->toBe([]);
});

test('movement outside the comparison window is not flagged', function () {
    $result = liuService()->evaluate(liuContext([
        'latitude' => 28.7,
        'longitude' => 80.6,
        'previous' => [
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'created_at' => date('c', time() - 7 * 24 * 3600),
        ],
    ]));

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_GENUINE)
        ->and($result['signals'])->toBe([]);
});

test('multiple signals combine and each maps to a fraud reason', function () {
    $result = liuService()->evaluate(liuContext([
        'client' => ['status' => 'mock_detected', 'mock_location_detected' => true],
        'gps_verification_status' => 'mismatched',
        'location_accuracy' => 0.0,
        'latitude' => 28.7,
        'longitude' => 80.6,
        'previous' => [
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'created_at' => date('c', time() - 20 * 60),
        ],
    ]));

    $reasons = liuService()->fraudReasons($result['signals']);

    expect($result['status'])->toBe(LocationIntegrityService::STATUS_SUSPICIOUS)
        ->and($result['signals'])->toContain('mock_location_reported')
        ->and($result['signals'])->toContain('photo_location_conflict')
        ->and($result['signals'])->toContain('implausible_location_accuracy')
        ->and($result['signals'])->toContain('impossible_movement')
        ->and($reasons)->toContain('mock_location_detected')
        ->and($reasons)->toContain('impossible_movement')
        ->and($reasons)->toContain('location_integrity_suspicious');
});

test('cannot_determine never produces fraud reasons', function () {
    $result = liuService()->evaluate(liuContext(['client' => null]));

    expect(liuService()->fraudReasons($result['signals']))->toBe([]);
});
