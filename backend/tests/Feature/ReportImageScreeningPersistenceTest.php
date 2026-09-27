<?php

use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\User;
use App\Services\Ai\AiFallbackRouter;
use App\Services\Ai\ReportAnalysisService;
use App\Support\ImageIntegrityPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Image screening persistence — Report #190-class regression tests
|--------------------------------------------------------------------------
|
| Root cause under test: ReportAnalysisService::analyzeImages() computed the
| deterministic ImageScreeningService result but did NOT attach it to the
| AI-outage catch entries (nor to the square-web-size heuristic entry), so a
| report analyzed while every AI provider was down stored a bare
| {verdict: unverifiable} image entry with no screening evidence.
|
| Environment reality (php -m): no 'exif' extension, no GD, no Imagick.
| These tests therefore also prove the new pure-PHP EXIF fallback keeps
| camera/software classification working without ext-exif. AI outage is
| simulated reliably via an empty AiFallbackRouter (run() throws
| AiRateLimitException 'No AI providers configured').
|
| Live-DB suite: every row/file created here is removed in afterEach.
| Test reports are attached to the EXISTING production category
| report_categories id=7 (see rspCategoryId) — that record is only read,
| never created and never deleted. Function names are prefixed rsp… to
| avoid collisions with other files.
|
*/

beforeEach(function () {
    $GLOBALS['rsp_report_ids'] = [];
    $GLOBALS['rsp_user_ids'] = [];
    $GLOBALS['rsp_files'] = [];
});

afterEach(function () {
    $reportIds = $GLOBALS['rsp_report_ids'] ?? [];

    DB::table('report_media')->whereIn('report_id', $reportIds)->delete();
    DB::table('reports')->whereIn('id', $reportIds)->delete();
    DB::table('users')->whereIn('id', $GLOBALS['rsp_user_ids'] ?? [])->delete();

    foreach ($GLOBALS['rsp_files'] ?? [] as $url) {
        Storage::disk('public')->delete($url);
    }

    // Prove zero leftovers from this file.
    expect(DB::table('report_media')->whereIn('report_id', $reportIds)->count())->toBe(0)
        ->and(DB::table('reports')->whereIn('id', $reportIds)->count())->toBe(0);

    $GLOBALS['rsp_report_ids'] = [];
    $GLOBALS['rsp_user_ids'] = [];
    $GLOBALS['rsp_files'] = [];
});

function rspUser(): int
{
    $user = User::create([
        'name' => 'RSP User',
        'email' => 'rsp_'.Str::random(12).'@example.com',
        'phone' => '98'.mt_rand(10000000, 99999999),
        'password' => bcrypt('TestPass123'),
        'uuid' => Str::uuid()->toString(),
        'status' => 'active',
        'is_active' => 1,
        'badges' => [],
        'expertise_regions' => [],
        'settings' => [],
        'total_xp' => 100,
        'current_level' => 3,
    ]);

    $GLOBALS['rsp_user_ids'][] = $user->id;

    return $user->id;
}

/**
 * The one exact production category these tests attach reports to:
 * report_categories id=7 — the category of Report #190, the real
 * image-integrity scenario report (verified during investigation), which is
 * also the canonical DatabaseSeeder slot for 'Services & Utilities' (seed
 * order: 1 General … 7 Services & Utilities) and a route-content category
 * in RouteRelevanceService::ROUTE_CONTENT_CATEGORY_IDS.
 *
 * Lookup only: this record is NEVER created and NEVER deleted here. If it
 * is missing the test fails with an explicit message instead of falling
 * back to a fixture category. Keyed by primary key because the name column
 * is not unique in this table.
 */
function rspCategoryId(): int
{
    $category = ReportCategorie::query()->find(7);

    if ($category === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 '
            .'(seed slot "Services & Utilities"; category of Report #190, the image-integrity scenario report) '
            .'is missing. These tests must use that real category record and must not create fixture categories.'
        );
    }

    return (int) $category->id;
}

function rspReportId(array $overrides = []): int
{
    $id = DB::table('reports')->insertGetId(array_merge([
        'user_id' => rspUser(),
        'uuid' => Str::uuid()->toString(),
        'title' => 'RSP screening persistence report '.Str::random(6),
        'description' => 'Regression test report for deterministic screening persistence',
        'category_id' => rspCategoryId(),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    $GLOBALS['rsp_report_ids'][] = $id;

    return $id;
}

function rspPutImage(string $bytes): string
{
    $url = 'report-images/rsp_'.Str::random(16).'.jpg';
    Storage::disk('public')->put($url, $bytes);
    $GLOBALS['rsp_files'][] = $url;

    return $url;
}

function rspAddMedia(int $reportId, string $url, ?string $hash = null): void
{
    DB::table('report_media')->insert([
        'report_id' => $reportId,
        'type' => 'image',
        'media_url' => $url,
        'media_hash' => $hash,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Invoke the protected analyzeImages() exactly as the pipeline does, with
 * the vision router replaced by an outage/error stand-in.
 */
function rspAnalyze(Report $report, ?AiFallbackRouter $vision = null): array
{
    $service = new ReportAnalysisService(
        new AiFallbackRouter([]),
        $vision ?? new AiFallbackRouter([]),
    );

    $method = new ReflectionMethod($service, 'analyzeImages');
    $method->setAccessible(true);

    return $method->invoke($service, $report);
}

function rspCraftJpeg(int $width, int $height, ?string $tiff = null): string
{
    $soi = "\xFF\xD8";
    $app0 = "\xFF\xE0".pack('n', 16)."JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    $app1 = '';
    if ($tiff !== null) {
        $payload = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    $sof0 = "\xFF\xC0".pack('n', 11)."\x08".pack('nn', $height, $width)."\x01\x01\x11\x00";

    return $soi.$app0.$app1.$sof0."\xFF\xD9";
}

/**
 * Byte-crafted TIFF with ASCII tags (little-endian) — see the Unit EXIF
 * test file for the full builder with big-endian/sub-IFD support.
 */
function rspCraftTiff(array $tags): string
{
    $entries = [];
    foreach ($tags as $tag => $value) {
        $entries[] = ['tag' => $tag, 'value' => $value."\0"];
    }

    $ifd0Size = 2 + (12 * count($entries)) + 4;
    $pos = 8 + $ifd0Size;

    foreach ($entries as &$entry) {
        if (strlen($entry['value']) > 4) {
            $entry['offset'] = $pos;
            $pos += strlen($entry['value']);
        }
    }
    unset($entry);

    $blob = pack('v', count($entries));
    $values = '';
    foreach ($entries as $entry) {
        $len = strlen($entry['value']);
        if ($len <= 4) {
            $blob .= pack('v', $entry['tag']).pack('v', 2).pack('V', $len)
                .$entry['value'].str_repeat("\0", 4 - $len);
        } else {
            $blob .= pack('v', $entry['tag']).pack('v', 2).pack('V', $len).pack('V', $entry['offset']);
            $values .= $entry['value'];
        }
    }
    $blob .= pack('V', 0);

    return 'II'.pack('v', 42).pack('V', 8).$blob.$values;
}

function rspIndicatorRows(array $payload): array
{
    $rows = [];
    foreach ($payload['indicators'] as $row) {
        $rows[$row['label']] = $row;
    }

    return $rows;
}

function rspTechValue(array $payload, string $label): ?string
{
    foreach ($payload['technical'] as $row) {
        if ($row['label'] === $label) {
            return $row['value'];
        }
    }

    return null;
}

test('AI-outage branch stores deterministic screening evidence (Report #190 regression)', function () {
    // Report #190 class: genuine camera-EXIF photo, every AI provider down.
    $reportId = rspReportId();
    $tiff = rspCraftTiff([
        0x010F => 'TestCam',
        0x0110 => 'PX9',
        0x0132 => '2026:01:02 03:04:05',
    ]);
    rspAddMedia($reportId, rspPutImage(rspCraftJpeg(1000, 750, $tiff)));

    $result = rspAnalyze(Report::find($reportId));

    expect($result['reviewed'])->toBe(0)
        ->and($result['verdict'])->toBe('unverifiable')
        ->and($result['images'])->toHaveCount(1);

    $entry = $result['images'][0];

    expect($entry['verdict'])->toBe('unverifiable')
        ->and($entry['reason'])->toContain('AI providers all unavailable')
        ->and($entry['provider_used'])->toBeNull()
        // THE regression: screening must ride along even when vision is down.
        ->and($entry['screening'])->toBeArray()
        ->and($entry['screening']['evaluated'])->toBeTrue()
        ->and($entry['screening']['strong'])->toBeFalse()
        ->and($entry['screening']['signals'])->toBe([])
        ->and($entry['screening']['facts']['camera_trace'])->toBe('present')
        ->and($entry['screening']['facts']['mime'])->toContain('jpeg')
        // Pure-PHP EXIF fallback classifies the camera trace (was 'unknown'
        // for EVERY image on this server because ext-exif is missing).
        ->and($entry['exif_trace']['kind'])->toBe('camera')
        ->and($entry['exif_trace']['make'])->toBe('TestCam')
        ->and($entry['exif_trace']['model'])->toBe('PX9');

    // Presenter surfaces the stored evidence honestly.
    $report = Report::find($reportId);
    $report->ai_analysis = ['image_check' => $result];
    $report->authenticity_score = 0.90;

    $payload = (new ImageIntegrityPresenter)->build($report);
    $rows = rspIndicatorRows($payload);

    expect($rows['Camera EXIF trace']['result'])->toBe('Detected')
        ->and($rows['Vision AI analysis']['result'])->toBe('Unavailable')
        ->and($rows['Screen-photo indicator']['result'])->toBe('Not evaluated')
        ->and($rows['Moiré / display pattern']['result'])->toBe('Unknown')
        ->and($rows['Moiré / display pattern']['tone'])->toBe('unknown')
        ->and($rows['Display structure']['result'])->toBe('Unknown')
        ->and($rows['Overall stored confidence']['result'])->toBe('90%')
        ->and($rows['Overall stored confidence']['meaning'])->toContain('neutral placeholder')
        ->and($rows['Overall stored confidence']['meaning'])->toContain('NOT a Vision AI image-confidence value')
        ->and(rspTechValue($payload, 'Per-image screening stored'))->toBe('1 of 1 image result(s)');
});

test('strong viewport signal skips vision AI and still stores screening with no-ext-exif trace', function () {
    $reportId = rspReportId();
    rspAddMedia($reportId, rspPutImage(rspCraftJpeg(1920, 1080)));

    $result = rspAnalyze(Report::find($reportId));

    expect($result['verdict'])->toBe('suspicious')
        ->and($result['reviewed'])->toBe(1);

    $entry = $result['images'][0];

    expect($entry['verdict'])->toBe('suspicious')
        ->and($entry['ai_skipped'])->toBeTrue()
        ->and($entry['reason'])->toContain('Deterministic image pre-filter')
        ->and($entry['screening']['evaluated'])->toBeTrue()
        ->and($entry['screening']['strong'])->toBeTrue()
        ->and(array_column($entry['screening']['signals'], 'signal'))
        ->toContain('known_screen_viewport_dimensions')
        // No APP1 at all -> fallback reports no_metadata (previously 'unknown').
        ->and($entry['exif_trace']['kind'])->toBe('no_metadata');
});

test('square web-size heuristic branch carries screening too', function () {
    $reportId = rspReportId();
    rspAddMedia($reportId, rspPutImage(rspCraftJpeg(600, 600)));

    $result = rspAnalyze(Report::find($reportId));

    $entry = $result['images'][0];

    expect($entry['verdict'])->toBe('suspicious')
        ->and($entry['reason'])->toContain('No camera metadata + square web-size dimensions')
        ->and($entry['ai_skipped'])->toBeTrue()
        ->and($entry['screening']['evaluated'])->toBeTrue()
        ->and($entry['screening']['facts']['camera_trace'])->toBe('absent')
        ->and($entry['exif_trace']['kind'])->toBe('no_metadata');
});

test('vision provider crash branch also stores screening', function () {
    $crashingVision = new class([]) extends AiFallbackRouter
    {
        public function generateJsonWithImages(string $prompt, array $imagePaths, array $options = []): array
        {
            throw new RuntimeException('simulated provider crash');
        }
    };

    $reportId = rspReportId();
    $tiff = rspCraftTiff([0x010F => 'CrashCam']);
    rspAddMedia($reportId, rspPutImage(rspCraftJpeg(800, 600, $tiff)));

    $result = rspAnalyze(Report::find($reportId), $crashingVision);

    $entry = $result['images'][0];

    expect($entry['verdict'])->toBe('unverifiable')
        ->and($entry['reason'])->toBe('Vision API error')
        ->and($entry['screening']['evaluated'])->toBeTrue()
        ->and($entry['exif_trace']['kind'])->toBe('camera')
        ->and($entry['exif_trace']['make'])->toBe('CrashCam');
});

test('EXIF software stamp is detected through the pure-PHP fallback and stored as a strong signal', function () {
    $reportId = rspReportId();
    $tiff = rspCraftTiff([0x0131 => 'Snipaste']); // software stamp, no camera Make/Model
    rspAddMedia($reportId, rspPutImage(rspCraftJpeg(1000, 750, $tiff)));

    $result = rspAnalyze(Report::find($reportId));

    $entry = $result['images'][0];

    expect($entry['verdict'])->toBe('suspicious')
        ->and($entry['ai_skipped'])->toBeTrue()
        ->and($entry['exif_trace']['kind'])->toBe('screenshotish')
        ->and($entry['exif_trace']['software'])->toBe('Snipaste')
        ->and($entry['screening']['evaluated'])->toBeTrue()
        ->and($entry['screening']['strong'])->toBeTrue()
        ->and(array_column($entry['screening']['signals'], 'signal'))
        ->toContain('screenshot_software_stamp');
});

test('duplicate entry keeps its evidence intact and intentionally has no screening', function () {
    $first = rspReportId();
    $second = rspReportId();
    $bytes = rspCraftJpeg(800, 600);
    $hash = hash('sha256', $bytes);
    $url = rspPutImage($bytes);
    rspAddMedia($first, $url, $hash);
    rspAddMedia($second, $url, $hash);

    $result = rspAnalyze(Report::find($second));

    $entry = $result['images'][0];

    expect($entry['verdict'])->toBe('duplicate')
        ->and($entry['reason'])->toContain("already used in report #{$first}")
        ->and($entry['reason'])->toContain('likely re-uploaded')
        ->and(array_key_exists('screening', $entry))->toBeFalse();

    // Presenter: duplicate evidence shown, screening rows honestly 'Not evaluated'.
    $report = Report::find($second);
    $report->ai_analysis = ['image_check' => $result];

    $payload = (new ImageIntegrityPresenter)->build($report);
    $rows = rspIndicatorRows($payload);

    expect($rows['Exact duplicate']['result'])->toBe('Suspicious signal detected')
        ->and($rows['Exact duplicate']['meaning'])->toContain("report #{$first}")
        ->and($rows['Screenshot viewport dimensions']['result'])->toBe('Not evaluated')
        ->and(rspTechValue($payload, 'Per-image screening stored'))->toBe('0 of 1 image result(s)');
});

test('screening results marked evaluated=false are counted as not evaluated by the presenter', function () {
    $reportId = rspReportId();
    $report = Report::find($reportId);
    $report->ai_analysis = ['image_check' => [
        'reviewed' => 1,
        'verdict' => 'suspicious',
        'message' => 'suspicious',
        'images' => [[
            'media_id' => 7,
            'verdict' => 'suspicious',
            'reason' => 'stored by an older pipeline',
            'exif_trace' => ['kind' => 'no_metadata', 'reason' => 'No EXIF metadata at all'],
            'ai_skipped' => true,
            'screening' => ['evaluated' => false, 'strong' => false, 'signals' => [], 'facts' => []],
        ]],
    ]];

    $payload = (new ImageIntegrityPresenter)->build($report);
    $rows = rspIndicatorRows($payload);

    expect($rows['Screenshot viewport dimensions']['result'])->toBe('Not evaluated')
        ->and($rows['Screenshot software stamp']['result'])->toBe('Not evaluated')
        ->and(rspTechValue($payload, 'Per-image screening stored'))->toBe('0 of 1 image result(s)');
});
