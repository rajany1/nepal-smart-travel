<?php

use App\Services\Ai\ImageScreeningService;

/*
|--------------------------------------------------------------------------
| ImageScreeningService (deterministic, zero-AI pre-filter) unit tests
|--------------------------------------------------------------------------
|
| Fixtures are crafted byte-exact PNG/JPEG files (no GD/Imagick available),
| and EXIF camera traces are supplied the same way ReportAnalysisService
| computes them via checkCameraTrace().
|
*/

$GLOBALS['screening_temp_files'] = [];

function screeningTempFile(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'screening');
    file_put_contents($path, $bytes);
    $GLOBALS['screening_temp_files'][] = $path;

    return $path;
}

function screeningPngChunk(string $type, string $data): string
{
    return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
}

function screeningMakePng(int $width, int $height): string
{
    $ihdr = pack('NN', $width, $height)."\x08\x02\x00\x00\x00"; // 8-bit RGB
    $raw = str_repeat("\x00".str_repeat("\x00", $width * 3), $height);
    $idat = gzcompress($raw);

    return "\x89PNG\r\n\x1a\n"
        .screeningPngChunk('IHDR', $ihdr)
        .screeningPngChunk('IDAT', $idat)
        .screeningPngChunk('IEND', '');
}

function screeningMakeJpeg(int $width, int $height, bool $withExif = false): string
{
    $soi = "\xFF\xD8";
    $app0 = "\xFF\xE0".pack('n', 16)."JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    $app1 = '';
    if ($withExif) {
        // Minimal TIFF header + empty IFD0, enough to carry the Exif signature.
        $tiff = "II*\x00".pack('V', 8).pack('v', 0).pack('V', 0);
        $payload = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    $sof0 = "\xFF\xC0".pack('n', 11)."\x08".pack('nn', $height, $width)."\x01\x01\x11\x00";

    return $soi.$app0.$app1.$sof0."\xFF\xD9";
}

function screeningTraceExifMissing(): array
{
    return ['kind' => 'unknown', 'reason' => 'EXIF extension missing on server'];
}

function screeningTraceNotJpeg(string $mime): array
{
    return ['kind' => 'unknown', 'reason' => "Not a JPEG ({$mime})"];
}

function screeningTraceCamera(): array
{
    return [
        'kind' => 'camera',
        'make' => 'Google',
        'model' => 'Pixel 8',
        'has_lens_data' => true,
        'reason' => 'Real camera EXIF trace present',
    ];
}

function screeningSignalIds(array $result): array
{
    return array_column($result['signals'], 'signal');
}

afterEach(function () {
    foreach ($GLOBALS['screening_temp_files'] ?? [] as $path) {
        @unlink($path);
    }
    $GLOBALS['screening_temp_files'] = [];
});

test('genuine-looking camera-ratio image without EXIF is NOT flagged and continues to vision AI', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1200, 1600));

    $result = $service->screen($path, 1200, 1600, screeningTraceExifMissing());

    expect($result['strong'])->toBeFalse()
        ->and($result['signals'])->toBeEmpty()
        ->and($result['facts']['camera_trace'])->toBe('absent')
        ->and($result['facts']['mime'])->toContain('jpeg');
});

test('missing EXIF alone is never treated as a fake signal (PNG case)', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakePng(1200, 1600));

    $result = $service->screen($path, 1200, 1600, screeningTraceNotJpeg('image/png'));

    expect($result['strong'])->toBeFalse()
        ->and($result['signals'])->toBeEmpty();
});

test('1080x1920 PNG screenshot without EXIF triggers known_screen_viewport_dimensions', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakePng(1080, 1920));

    $result = $service->screen($path, 1080, 1920, screeningTraceNotJpeg('image/png'));

    expect($result['strong'])->toBeTrue()
        ->and(screeningSignalIds($result))->toContain('known_screen_viewport_dimensions')
        ->and($result['facts']['viewport_match'])->toBeTrue();
});

test('1080x1920 JPEG screenshot without EXIF triggers known_screen_viewport_dimensions', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1080, 1920));

    $result = $service->screen($path, 1080, 1920, screeningTraceExifMissing());

    expect($result['strong'])->toBeTrue()
        ->and(screeningSignalIds($result))->toContain('known_screen_viewport_dimensions');
});

test('reversed (landscape) viewport orientation also matches', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakePng(1920, 1080));

    $result = $service->screen($path, 1920, 1080, screeningTraceNotJpeg('image/png'));

    expect($result['strong'])->toBeTrue()
        ->and(screeningSignalIds($result))->toContain('known_screen_viewport_dimensions');
});

test('viewport dimensions WITH a camera EXIF trace are NOT flagged', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1080, 1920));

    $result = $service->screen($path, 1080, 1920, screeningTraceCamera());

    expect($result['strong'])->toBeFalse()
        ->and($result['facts']['camera_trace'])->toBe('present');
});

test('viewport dimensions WITH an EXIF block that cannot be parsed are NOT flagged (conservative)', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1080, 1920, withExif: true));

    $result = $service->screen($path, 1080, 1920, screeningTraceExifMissing());

    expect($result['strong'])->toBeFalse()
        ->and($result['facts']['camera_trace'])->toBe('uncertain');
});

test('viewport dimensions with a positively-read no-metadata trace ARE flagged (production EXIF path)', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1080, 1920));

    $result = $service->screen($path, 1080, 1920, [
        'kind' => 'no_metadata',
        'reason' => 'No EXIF metadata at all',
    ]);

    expect($result['strong'])->toBeTrue()
        ->and(screeningSignalIds($result))->toContain('known_screen_viewport_dimensions');
});

test('extreme aspect ratio without camera trace triggers extreme_aspect_ratio', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakePng(600, 3000));

    $result = $service->screen($path, 600, 3000, screeningTraceNotJpeg('image/png'));

    expect($result['strong'])->toBeTrue()
        ->and(screeningSignalIds($result))->toContain('extreme_aspect_ratio')
        ->and($result['facts']['aspect_ratio'])->toEqual(5.0);
});

test('extreme aspect ratio WITH camera trace is NOT flagged (legit panorama case)', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(600, 3000));

    $result = $service->screen($path, 600, 3000, screeningTraceCamera());

    expect($result['strong'])->toBeFalse();
});

test('EXIF Software tag naming a screen-capture tool triggers screenshot_software_stamp', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1600, 1200));

    $result = $service->screen($path, 1600, 1200, [
        'kind' => 'screenshotish',
        'software' => 'Snipping Tool',
        'has_lens_data' => false,
        'reason' => 'No camera trace; software stamp: Snipping Tool',
    ]);

    expect($result['strong'])->toBeTrue()
        ->and(screeningSignalIds($result))->toContain('screenshot_software_stamp');
});

test('generic editing Software stamp alone does NOT skip vision AI', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1600, 1200));

    $result = $service->screen($path, 1600, 1200, [
        'kind' => 'screenshotish',
        'software' => 'Adobe Photoshop 24.0',
        'has_lens_data' => false,
        'reason' => 'No camera trace; software stamp: Adobe Photoshop 24.0',
    ]);

    expect($result['strong'])->toBeFalse()
        ->and($result['signals'])->toBeEmpty();
});

test('corrupted / non-image bytes never throw and are never flagged strong', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile('this is definitely not an image file');

    $result = $service->screen($path, 0, 0, screeningTraceExifMissing());

    expect($result['strong'])->toBeFalse();
});

test('nonexistent file path never throws and is never flagged strong', function () {
    $service = new ImageScreeningService;

    $result = $service->screen(
        sys_get_temp_dir().DIRECTORY_SEPARATOR.'screening_missing_file.jpg',
        1080,
        1920,
        screeningTraceExifMissing()
    );

    expect($result['strong'])->toBeFalse();
});

test('every screen viewport entry has a long edge above the app capture cap (1600)', function () {
    $constant = (new ReflectionClass(ImageScreeningService::class))
        ->getReflectionConstant('SCREEN_VIEWPORT_DIMENSIONS');

    expect($constant)->not->toBeFalse();

    foreach ($constant->getValue() as [$w, $h]) {
        expect(max($w, $h))->toBeGreaterThan(1600);
    }
});

test('zero/invalid dimensions produce no dimension-based signals', function () {
    $service = new ImageScreeningService;
    $path = screeningTempFile(screeningMakeJpeg(1080, 1920));

    $result = $service->screen($path, 0, 0, screeningTraceExifMissing());

    expect($result['strong'])->toBeFalse()
        ->and($result['facts']['aspect_ratio'])->toBe(0.0);
});
