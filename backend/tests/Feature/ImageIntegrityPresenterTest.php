<?php

use App\Models\Report;
use App\Models\ReportMedia;
use App\Support\ImageIntegrityPresenter;

/*
|--------------------------------------------------------------------------
| Image Integrity presenter tests (pure display transform, no DB writes)
|--------------------------------------------------------------------------
|
| These verify that stored screening / analysis / GPS data is surfaced as
| neutral EVIDENCE rows: unknown is never a negative verdict, and generated
| labels/results never claim "fake", "fraud" or any definitive conclusion.
|
*/

function iipReport(array $attributes = []): Report
{
    $report = new Report;
    $report->forceFill(array_merge([
        'title' => 'Image integrity fixture report',
        'ai_analysis' => null,
        'gps_verification_status' => 'none',
        'gps_distance_km' => null,
        'photo_gps_lat' => null,
        'photo_gps_lng' => null,
        'is_live_capture' => false,
        'authenticity_score' => null,
        'ai_analyzed_at' => null,
    ], $attributes));

    return $report;
}

function iipPresenter(): ImageIntegrityPresenter
{
    return new ImageIntegrityPresenter;
}

function iipRow(array $payload, string $label): array
{
    foreach ($payload['indicators'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }

    throw new RuntimeException("Indicator row not found: {$label}");
}

function iipImageRow(array $payload, int $index, string $label): array
{
    foreach ($payload['images'][$index]['rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }

    throw new RuntimeException("Per-image row not found: {$label}");
}

function iipStrongScreeningEntry(array $overrides = []): array
{
    return array_merge([
        'media_id' => 42,
        'verdict' => 'suspicious',
        'reason' => 'Deterministic image pre-filter — Vision AI skipped: 1080x1920 matches a documented screen viewport resolution and the file has no camera EXIF trace',
        'exif_trace' => ['kind' => 'no_metadata', 'reason' => 'No EXIF metadata at all', 'has_lens_data' => false],
        'ai_skipped' => true,
        'provider_used' => null,
        'screening' => [
            'strong' => true,
            'signals' => [[
                'signal' => 'known_screen_viewport_dimensions',
                'detail' => '1080x1920 matches a documented screen viewport resolution and the file has no camera EXIF trace',
            ]],
            'facts' => ['mime' => 'image/jpeg', 'camera_trace' => 'absent', 'aspect_ratio' => 1.778, 'viewport_match' => true],
        ],
    ], $overrides);
}

function iipVisionEntry(array $overrides = []): array
{
    return array_merge([
        'media_id' => 9,
        'verdict' => 'clean',
        'verdict_reason' => '',
        'is_ai_generated' => false,
        'is_screen_photo' => false,
        'is_map_screenshot' => false,
        'screen_probability' => 0.02,
        'real_scene_probability' => 0.93,
        'report_match' => 0.9,
        'shows_what' => 'A flooded road after heavy rain',
        'matches_title_description' => true,
        'misleading' => false,
        'inappropriate_abusive' => false,
        'phishing' => false,
        'confidence' => 0.85,
        'reason' => 'A flooded road after heavy rain',
        'provider_used' => 'gemini',
        'exif_trace' => ['kind' => 'camera', 'make' => 'Apple', 'model' => 'iPhone 14', 'has_lens_data' => true, 'reason' => 'Real camera EXIF trace present'],
        'screening' => [
            'strong' => false,
            'signals' => [],
            'facts' => ['mime' => 'image/jpeg', 'camera_trace' => 'present', 'aspect_ratio' => 1.333, 'viewport_match' => false],
        ],
    ], $overrides);
}

it('renders a legacy report with no stored analysis as Not evaluated', function () {
    $payload = iipPresenter()->build(iipReport());

    expect($payload['has_analysis'])->toBeFalse()
        ->and($payload['status']['tone'])->toBe('unknown')
        ->and($payload['status']['text'])->toContain('Not evaluated')
        ->and($payload['images'])->toBe([])
        ->and(iipRow($payload, 'Camera EXIF trace')['result'])->toBe('Not evaluated')
        ->and(iipRow($payload, 'GPS consistency')['result'])->toBe('Not evaluated')
        ->and(iipRow($payload, 'Exact duplicate')['result'])->toBe('Not evaluated')
        ->and(iipRow($payload, 'Vision AI analysis')['result'])->toBe('Not evaluated')
        ->and(iipRow($payload, 'Screenshot viewport dimensions')['result'])->toBe('Not evaluated')
        ->and(iipRow($payload, 'Overall stored confidence')['result'])->toBe('Not evaluated');
});

it('renders a BIPAD-shaped ai_analysis without image_check safely', function () {
    $payload = iipPresenter()->build(iipReport([
        'ai_analysis' => ['bipad_hazard' => 'flood', 'bipad_severity' => 'high'],
    ]));

    expect($payload['has_analysis'])->toBeTrue()
        ->and($payload['status']['tone'])->toBe('unknown')
        ->and($payload['images'])->toBe([])
        ->and(iipRow($payload, 'Vision AI analysis')['result'])->toBe('Not evaluated');
});

it('exposes a strong viewport screening signal as a neutral detected indicator', function () {
    $payload = iipPresenter()->build(iipReport([
        'ai_analysis' => [
            'image_check' => [
                'reviewed' => 1,
                'verdict' => 'suspicious',
                'message' => 'suspicious',
                'images' => [iipStrongScreeningEntry()],
            ],
        ],
        'authenticity_score' => 0.42,
    ]));

    expect($payload['status']['tone'])->toBe('suspicious')
        ->and($payload['status']['text'])->toContain('Manual review recommended');

    $viewport = iipRow($payload, 'Screenshot viewport dimensions');
    expect($viewport['result'])->toBe('Detected')
        ->and($viewport['tone'])->toBe('suspicious')
        ->and($viewport['meaning'])->toContain('1080x1920 matches a documented screen viewport')
        ->and($viewport['meaning'])->toContain('does not prove that the image is a screenshot');

    expect(iipRow($payload, 'Extreme aspect ratio')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'Screenshot software stamp')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'Exact duplicate')['result'])->toBe('Not found')
        ->and(iipRow($payload, 'Similar image')['result'])->toBe('Not found')
        ->and(iipRow($payload, 'Vision AI analysis')['result'])->toBe('Skipped')
        ->and(iipRow($payload, 'Overall stored confidence')['result'])->toBe('42%');

    $exif = iipRow($payload, 'Camera EXIF trace');
    expect($exif['result'])->toBe('EXIF camera information unavailable')
        ->and($exif['tone'])->toBe('unknown')
        ->and($exif['meaning'])->toContain('does not prove external sourcing');

    expect(iipRow($payload, 'GPS metadata')['result'])->toBe('Unavailable')
        ->and(iipRow($payload, 'GPS metadata')['meaning'])->toContain('not the same as a mismatch');

    // Per-image breakdown mirrors the same neutral evidence.
    expect(iipImageRow($payload, 0, 'Stored verdict')['result'])->toBe('Suspicious signal detected')
        ->and(iipImageRow($payload, 0, 'Screenshot viewport dimensions')['result'])->toBe('Detected')
        ->and(iipImageRow($payload, 0, 'Vision AI')['result'])->toBe('Skipped');
});

it('keeps GPS mismatch and GPS unavailable as distinct conditions', function () {
    $mismatch = iipPresenter()->build(iipReport([
        'photo_gps_lat' => 27.71,
        'photo_gps_lng' => 85.32,
        'gps_verification_status' => 'mismatched',
        'gps_distance_km' => 5.2,
    ]));

    $consistency = iipRow($mismatch, 'GPS consistency');
    expect($consistency['result'])->toBe('Mismatch detected')
        ->and($consistency['tone'])->toBe('suspicious')
        ->and($consistency['meaning'])->toContain('beyond the configured tolerance')
        ->and($consistency['meaning'])->toContain('5.2')
        ->and(iipRow($mismatch, 'GPS metadata')['result'])->toBe('Present')
        ->and($mismatch['status']['tone'])->toBe('suspicious');

    $noGps = iipPresenter()->build(iipReport([
        'gps_verification_status' => 'no_gps_data',
    ]));

    $consistency = iipRow($noGps, 'GPS consistency');
    expect($consistency['result'])->toBe('Unavailable')
        ->and($consistency['tone'])->toBe('unknown')
        ->and($consistency['meaning'])->toContain('not the same as a mismatch')
        ->and($consistency['meaning'])->not->toContain('beyond the configured tolerance')
        ->and($noGps['status']['tone'])->toBe('unknown');

    $verified = iipPresenter()->build(iipReport([
        'photo_gps_lat' => 27.71,
        'photo_gps_lng' => 85.32,
        'gps_verification_status' => 'verified',
        'gps_distance_km' => 0.2,
        'ai_analysis' => [
            'image_check' => ['reviewed' => 1, 'verdict' => 'clean', 'message' => 'clean', 'images' => [iipVisionEntry()]],
        ],
    ]));

    expect(iipRow($verified, 'GPS consistency')['result'])->toBe('Matches')
        ->and($verified['status']['tone'])->toBe('ok');
});

it('surfaces stored exact and fingerprint duplicate results verbatim', function () {
    $payload = iipPresenter()->build(iipReport([
        'ai_analysis' => [
            'image_check' => [
                'reviewed' => 2,
                'verdict' => 'duplicate',
                'message' => 'duplicate',
                'images' => [
                    ['media_id' => 1, 'verdict' => 'duplicate', 'reason' => 'Identical image already used in report #77 — likely re-uploaded'],
                    ['media_id' => 2, 'verdict' => 'duplicate', 'reason' => 'Fingerprint match with report #78 — likely same photo, different encoding'],
                ],
            ],
        ],
    ]));

    $exact = iipRow($payload, 'Exact duplicate');
    expect($exact['result'])->toBe('Suspicious signal detected')
        ->and($exact['meaning'])->toContain('Identical image already used in report #77');

    $similar = iipRow($payload, 'Similar image');
    expect($similar['result'])->toBe('Suspicious signal detected')
        ->and($similar['meaning'])->toContain('Fingerprint match with report #78');

    expect($payload['status']['tone'])->toBe('suspicious')
        ->and(iipImageRow($payload, 0, 'Stored verdict')['result'])->toBe('Duplicate signal detected')
        ->and(iipImageRow($payload, 1, 'Vision AI')['result'])->toBe('Skipped');
});

it('renders a completed clean vision result with stored numbers and camera EXIF detected', function () {
    $report = iipReport([
        'ai_analysis' => [
            'action' => 'approve',
            'image_check' => ['reviewed' => 1, 'verdict' => 'clean', 'message' => 'clean', 'images' => [iipVisionEntry()]],
        ],
        'authenticity_score' => 0.91,
        'photo_gps_lat' => 27.71,
        'photo_gps_lng' => 85.32,
        'gps_verification_status' => 'verified',
        'gps_distance_km' => 0.1,
        'is_live_capture' => true,
        'ai_analyzed_at' => '2026-09-25 10:00:00',
    ]);
    $report->setRelation('media', collect([
        (new ReportMedia)->forceFill(['id' => 9, 'media_hash' => str_repeat('a', 64), 'fingerprint_hash' => str_repeat('b', 64)]),
    ]));

    $payload = iipPresenter()->build($report);

    expect($payload['status']['tone'])->toBe('ok')
        ->and($payload['status']['text'])->toContain('No suspicious indicators detected')
        ->and($payload['analyzed_at'])->toBe('2026-09-25 10:00:00');

    expect(iipRow($payload, 'Camera EXIF trace')['result'])->toBe('Detected')
        ->and(iipRow($payload, 'Screenshot viewport dimensions')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'Extreme aspect ratio')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'Screenshot software stamp')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'AI-generated indicator')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'Screen-photo indicator')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'Misleading image indicator')['result'])->toBe('Not detected')
        ->and(iipRow($payload, 'Vision AI analysis')['result'])->toBe('Completed')
        ->and(iipRow($payload, 'Vision AI analysis')['meaning'])->toContain('gemini')
        ->and(iipRow($payload, 'Overall stored confidence')['result'])->toBe('91%')
        ->and(iipRow($payload, 'In-app capture flag')['result'])->toBe('Present');

    expect(iipImageRow($payload, 0, 'Vision AI')['meaning'])->toContain('screen-photo 2%')
        ->and(iipImageRow($payload, 0, 'Title/description match')['result'])->toBe('Yes')
        ->and(iipImageRow($payload, 0, 'AI confidence')['result'])->toBe('85%')
        ->and(iipImageRow($payload, 0, 'AI-generated indicator')['result'])->toBe('Not detected');

    $technical = collect($payload['images'][0]['technical']);
    expect($technical->firstWhere('label', 'SHA-256 (file hash)')['value'])->toBe(str_repeat('a', 64))
        ->and($technical->firstWhere('label', 'Fingerprint hash')['value'])->toBe(str_repeat('b', 64))
        ->and($technical->firstWhere('label', 'AI skipped'))->toBeNull();

    $reportTechnical = collect($payload['technical']);
    expect($reportTechnical->firstWhere('label', 'Analysis stored')['value'])->toBe('yes')
        ->and($reportTechnical->firstWhere('label', 'AI suggested action (raw)')['value'])->toBe('approve');
});

it('renders AI provider outage and missing EXIF extension as Unknown, not as a negative verdict', function () {
    $payload = iipPresenter()->build(iipReport([
        'ai_analysis' => [
            'image_check' => [
                'reviewed' => 1,
                'verdict' => 'unverifiable',
                'message' => 'unverifiable',
                'images' => [[
                    'media_id' => 3,
                    'verdict' => 'unverifiable',
                    'reason' => 'AI providers all unavailable (quota/rate limit)',
                    'exif_trace' => ['kind' => 'unknown', 'reason' => 'EXIF extension missing on server'],
                    'provider_used' => null,
                ]],
            ],
        ],
    ]));

    expect(iipRow($payload, 'Vision AI analysis')['result'])->toBe('Unavailable')
        ->and(iipRow($payload, 'Vision AI analysis')['tone'])->toBe('unknown')
        ->and(iipRow($payload, 'Camera EXIF trace')['result'])->toBe('Unknown')
        ->and(iipRow($payload, 'Camera EXIF trace')['meaning'])->toContain('EXIF extension missing on server')
        ->and(iipRow($payload, 'Camera EXIF trace')['meaning'])->toContain('Metadata absence alone does not prove external sourcing')
        ->and(iipImageRow($payload, 0, 'Vision AI')['result'])->toBe('Unavailable');

    // Unknown must never become a suspicious/negative status.
    expect($payload['status']['tone'])->not->toBe('suspicious');
});

it('never generates definitive claims in labels or results', function () {
    $payloads = [
        iipPresenter()->build(iipReport()),
        iipPresenter()->build(iipReport(['ai_analysis' => ['image_check' => ['reviewed' => 1, 'verdict' => 'suspicious', 'message' => 'suspicious', 'images' => [iipStrongScreeningEntry()]]]])),
        iipPresenter()->build(iipReport(['ai_analysis' => ['image_check' => ['reviewed' => 1, 'verdict' => 'clean', 'message' => 'clean', 'images' => [iipVisionEntry()]]]])),
        iipPresenter()->build(iipReport(['gps_verification_status' => 'mismatched', 'photo_gps_lat' => 1.0, 'photo_gps_lng' => 1.0])),
        iipPresenter()->build(iipReport(['ai_analysis' => ['bipad_hazard' => 'flood']])),
    ];

    $forbidden = '/\b(fake|fraud|fraudulent|definitely|proved|proven|not\s+camera\s+captured|downloaded\s+image)\b/i';

    foreach ($payloads as $payload) {
        foreach ($payload['indicators'] as $row) {
            expect($row['label'])->not->toMatch($forbidden)
                ->and($row['result'])->not->toMatch($forbidden);
        }
        foreach ($payload['images'] as $image) {
            foreach ($image['rows'] as $row) {
                expect($row['label'])->not->toMatch($forbidden)
                    ->and($row['result'])->not->toMatch($forbidden);
            }
        }
        expect($payload['status']['text'])->not->toMatch($forbidden);
    }
});

it('handles entries where a media row no longer exists', function () {
    $report = iipReport([
        'ai_analysis' => ['image_check' => ['reviewed' => 1, 'verdict' => 'clean', 'message' => 'clean', 'images' => [iipVisionEntry(['media_id' => 999])]]],
    ]);
    $report->setRelation('media', collect([
        (new ReportMedia)->forceFill(['id' => 42, 'media_hash' => str_repeat('c', 64)]),
    ]));

    $payload = iipPresenter()->build($report);

    expect($payload['images'])->toHaveCount(1)
        ->and(collect($payload['images'][0]['technical'])->firstWhere('label', 'SHA-256 (file hash)'))->toBeNull();
});

it('always reports pixel-level display-pattern techniques as Unknown with an honest environment note', function () {
    $payload = iipPresenter()->build(iipReport([
        'ai_analysis' => ['image_check' => ['reviewed' => 1, 'verdict' => 'suspicious', 'message' => 'suspicious', 'images' => [iipStrongScreeningEntry()]]],
    ]));

    $moire = iipRow($payload, 'Moiré / display pattern');
    expect($moire['result'])->toBe('Unknown')
        ->and($moire['tone'])->toBe('unknown')
        ->and($moire['meaning'])->toContain('not evaluated')
        ->and($moire['meaning'])->toContain('not implemented')
        ->and($moire['meaning'])->toContain('not a negative verdict');

    $display = iipRow($payload, 'Display structure');
    expect($display['result'])->toBe('Unknown')
        ->and($display['tone'])->toBe('unknown')
        ->and($display['meaning'])->toContain('no image-pixel decoding capability');

    // The Unknown rows must never make the whole section suspicious by themselves.
    expect($payload['status']['tone'])->toBe('suspicious') // from the strong viewport signal
        ->and($payload['status']['text'])->toContain('Manual review recommended');
});

it('explains Overall stored confidence when the image check was not evaluated', function () {
    $payload = iipPresenter()->build(iipReport([
        'ai_analysis' => [
            'image_check' => [
                'reviewed' => 0,
                'verdict' => 'unverifiable',
                'message' => 'Images attached but none could be analyzed — needs moderator review',
                'images' => [[
                    'media_id' => 3,
                    'verdict' => 'unverifiable',
                    'reason' => 'AI providers all unavailable (quota/rate limit)',
                    'provider_used' => null,
                    'screening' => [
                        'evaluated' => true,
                        'strong' => false,
                        'signals' => [],
                        'facts' => ['mime' => 'image/jpeg', 'camera_trace' => 'present', 'aspect_ratio' => 1.333, 'viewport_match' => false],
                    ],
                ]],
            ],
        ],
        'authenticity_score' => 0.90,
    ]));

    $trust = iipRow($payload, 'Overall stored confidence');
    expect($trust['result'])->toBe('90%')
        ->and($trust['meaning'])->toContain('text 35%, location 25%, image 40%')
        ->and($trust['meaning'])->toContain('NOT a Vision AI image-confidence value')
        ->and($trust['meaning'])->toContain('neutral placeholder')
        ->and($trust['meaning'])->toContain('never replaces human review');

    // The legacy "AI trust score" label must be gone everywhere.
    foreach ($payload['indicators'] as $row) {
        expect($row['label'])->not->toBe('AI trust score');
    }

    // Screening survived the outage and is counted as evaluated.
    expect(collect($payload['technical'])->firstWhere('label', 'Per-image screening stored')['value'])
        ->toBe('1 of 1 image result(s)')
        ->and(iipRow($payload, 'Vision AI analysis')['result'])->toBe('Unavailable')
        ->and(iipImageRow($payload, 0, 'Vision AI')['result'])->toBe('Unavailable');
});
