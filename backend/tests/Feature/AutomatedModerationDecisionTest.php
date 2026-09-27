<?php

use App\Jobs\SendNearbyPushNotification;
use App\Jobs\TranslateContent;
use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\AiFallbackRouter;
use App\Services\Ai\ReportAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Automated moderation decision pipeline - end-to-end matrix
|--------------------------------------------------------------------------
|
| Verifies the REAL first-decision pipeline (not the moderator UI):
|
|   submit → image evidence → screening → AI/fallback → computeConfidence()
|          → decideAction() → persisted status (approved/pending/rejected)
|
| Root cause this file guards against: an AI-provider outage used to abort
| ReportAnalysisService::analyze() at the FIRST text-AI call, so the job
| released forever and reports stayed pending with no analysis (Report
| #296: "Analysis stored: no / 0 of 0"). The pipeline must instead fall
| back to deterministic signals and let the existing decision engine
| (>= 0.80 approve, 0.50-0.79 human review, < 0.50 reject) decide.
|
| AI outage is simulated with an empty AiFallbackRouter (run() throws
| AiRateLimitException). Successful AI is simulated with subclass stubs so
| no network call ever happens. The environment has no GD/Imagick/ext-exif,
| so images are byte-crafted.
|
| Live-DB suite: every row/file created here is removed in afterEach.
| Reports attach to the EXISTING production category
| report_categories id=7 (amdCategoryId) - never created, never deleted.
| Bus is faked for push/translation side effects only: AnalyzeReport runs
| for real (sync queue), proving submit → decision → status end-to-end.
| Function names are prefixed amd… to avoid collisions.
|
*/

beforeEach(function () {
    $GLOBALS['amd_report_ids'] = [];
    $GLOBALS['amd_user_ids'] = [];
    $GLOBALS['amd_files'] = [];

    // Nearby FCM pushes and translation side effects are not part of the
    // moderation contract under test. AnalyzeReport is NOT faked - it must
    // execute inline (QUEUE_CONNECTION=sync) exactly as submitted.
    Bus::fake([
        TranslateContent::class,
        SendNearbyPushNotification::class,
    ]);
});

afterEach(function () {
    $reportIds = $GLOBALS['amd_report_ids'] ?? [];
    $userIds = $GLOBALS['amd_user_ids'] ?? [];

    DB::table('report_media')->whereIn('report_id', $reportIds)->delete();
    DB::table('alerts')->where('source_type', 'report')->whereIn('source_id', $reportIds)->delete();
    DB::table('moderation_queues')->where('content_type', 'report')->whereIn('content_id', $reportIds)->delete();
    DB::table('xp_transactions')
        ->where(function ($q) use ($reportIds, $userIds) {
            $q->whereIn('user_id', $userIds)
                ->orWhere(function ($q2) use ($reportIds) {
                    $q2->where('reference_type', Report::class)->whereIn('reference_id', $reportIds);
                });
        })
        ->delete();
    DB::table('model_translations')->where('translatable_type', 'report')->whereIn('translatable_id', $reportIds)->delete();
    DB::table('reports')->whereIn('id', $reportIds)->delete();
    DB::table('legal_document_acceptances')->whereIn('user_id', $userIds)->delete();
    DB::table('idempotency_keys')->whereIn('user_id', $userIds)->delete();
    DB::table('users')->whereIn('id', $userIds)->delete();

    foreach ($GLOBALS['amd_files'] ?? [] as $url) {
        Storage::disk('public')->delete($url);
    }

    // Prove zero leftovers from this file.
    expect(DB::table('reports')->whereIn('id', $reportIds)->count())->toBe(0)
        ->and(DB::table('report_media')->whereIn('report_id', $reportIds)->count())->toBe(0)
        ->and(DB::table('moderation_queues')->where('content_type', 'report')->whereIn('content_id', $reportIds)->count())->toBe(0);

    $GLOBALS['amd_report_ids'] = [];
    $GLOBALS['amd_user_ids'] = [];
    $GLOBALS['amd_files'] = [];
});

/**
 * The one real category every fixture uses: report_categories id=7 - the
 * category of Report #190/#296 (the real image-integrity scenarios) and the
 * canonical DatabaseSeeder slot 'Services & Utilities'. Lookup only; fails
 * clearly when missing instead of creating a placeholder.
 */
function amdCategoryId(): int
{
    $category = ReportCategorie::query()->find(7);

    if ($category === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 '
            .'(seed slot "Services & Utilities"; category of the real #190/#296 reports) '
            .'is missing. These tests must use that real record and must not create fixture categories.'
        );
    }

    return (int) $category->id;
}

function amdUser(string $role = 'user'): User
{
    $user = User::create([
        'name' => 'AMD '.ucfirst($role),
        'email' => 'amd_'.Str::random(12).'@example.com',
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

    if ($role !== 'user') {
        User::whereKey($user->id)->update(['role_id' => Role::where('name', $role)->value('id')]);
        $user = $user->fresh();
    }

    $GLOBALS['amd_user_ids'][] = $user->id;

    return $user;
}

function amdReport(array $overrides = []): int
{
    $id = DB::table('reports')->insertGetId(array_merge([
        'user_id' => amdUser()->id,
        'uuid' => Str::uuid()->toString(),
        'title' => 'Prithvi Highway pothole damages vehicles '.mt_rand(100000, 999999),
        'description' => 'Deep potholes on the Prithvi Highway stretch are damaging vehicles near Muglin.',
        'category_id' => amdCategoryId(),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'pending',
        'provenance' => 'unverified_client',
        'source' => 'user',
        'priority' => 'medium',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    $GLOBALS['amd_report_ids'][] = $id;

    // Mirror ReportController::store(): the moderation-queue entry exists
    // BEFORE analysis starts - process() updates it to the final action.
    DB::table('moderation_queues')->insert([
        'content_type' => 'report',
        'content_id' => $id,
        'submitted_by' => DB::table('reports')->where('id', $id)->value('user_id'),
        'ai_spam_score' => '0.00',
        'status' => 'pending',
        'priority' => 'medium',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

function amdTiff(array $tags): string
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

function amdJpeg(int $width, int $height, ?string $tiff = null): string
{
    $soi = "\xFF\xD8";
    $app0 = "\xFF\xE0".pack('n', 16)."JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    $app1 = '';
    if ($tiff !== null) {
        $payload = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    $sof0 = "\xFF\xC0".pack('n', 11)."\x08".pack('nn', $height, $width)."\x01\x01\x11\x00";

    // Random tail AFTER the EOI marker: keeps content hashes unique across
    // tests (exact-duplicate block at submission) and satisfies the
    // submission rule "minimum 10240 bytes" without disturbing parsers.
    return $soi.$app0.$app1.$sof0."\xFF\xD9".random_bytes(11000);
}

function amdPutImage(string $bytes): string
{
    $url = 'report-images/amd_'.Str::random(16).'.jpg';
    Storage::disk('public')->put($url, $bytes);
    $GLOBALS['amd_files'][] = $url;

    return $url;
}

function amdAddMedia(int $reportId, string $bytes): void
{
    $url = amdPutImage($bytes);
    DB::table('report_media')->insert([
        'report_id' => $reportId,
        'type' => 'image',
        'media_url' => $url,
        'media_hash' => hash('sha256', $bytes),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function amdOutage(): AiFallbackRouter
{
    return new AiFallbackRouter([]);
}

function amdText(array $result): AiFallbackRouter
{
    return new class($result) extends AiFallbackRouter
    {
        private array $payload;

        public function __construct(array $payload)
        {
            parent::__construct([]);
            $this->payload = $payload;
        }

        public function generateJson(string $prompt, array $options = []): array
        {
            return $this->payload + ['provider_used' => 'fake-text'];
        }
    };
}

function amdVision(array $result): AiFallbackRouter
{
    return new class($result) extends AiFallbackRouter
    {
        private array $payload;

        public function __construct(array $payload)
        {
            parent::__construct([]);
            $this->payload = $payload;
        }

        public function generateJsonWithImages(string $prompt, array $imagePaths, array $options = []): array
        {
            return $this->payload + ['provider_used' => 'fake-vision'];
        }
    };
}

function amdTextLegit(): array
{
    return [
        'suggested_priority' => 'medium',
        'is_legitimate' => true,
        'is_duplicate' => false,
        'summary' => 'Community road hazard report about damaged highway stretch.',
        'category_match' => true,
        'category_reason' => 'Title and description match the report category',
        'action' => 'approve',
    ];
}

function amdVisionClean(): array
{
    return [
        'is_ai_generated' => false,
        'is_screen_photo' => false,
        'screen_probability' => 0.02,
        'real_scene_probability' => 0.95,
        'is_map_screenshot' => false,
        'shows_what' => 'potholes on a paved road',
        'matches_title_description' => true,
        'report_match' => 0.92,
        'misleading' => false,
        'inappropriate_abusive' => false,
        'phishing' => false,
        'confidence' => 0.95,
        'summary' => 'Photo shows damaged road surface matching the report.',
    ];
}

function amdVisionScreen(): array
{
    return [
        'is_ai_generated' => false,
        'is_screen_photo' => true,
        'screen_probability' => 0.5,
        'real_scene_probability' => 0.6,
        'is_map_screenshot' => false,
        'shows_what' => 'possibly a photo of a display',
        'matches_title_description' => true,
        'report_match' => 0.5,
        'misleading' => false,
        'inappropriate_abusive' => false,
        'phishing' => false,
        'confidence' => 0.8,
        'summary' => 'Possible photo of a screen - uncertain.',
    ];
}

function amdProcess(int $reportId, ?AiFallbackRouter $text = null, ?AiFallbackRouter $vision = null): array
{
    $service = new ReportAnalysisService($text ?? amdOutage(), $vision ?? amdOutage());

    return $service->process(Report::find($reportId));
}

function amdCameraImage(): string
{
    return amdJpeg(1000, 750, amdTiff([
        0x010F => 'TestCam',
        0x0110 => 'PX9',
        0x0132 => '2026:01:02 03:04:05',
    ]));
}

function amdAcceptLegal(User $user): void
{
    foreach (['terms_conditions', 'privacy_policy'] as $type) {
        $doc = DB::table('legal_documents')
            ->where('type', $type)
            ->where('is_published', 1)
            ->orderByDesc('published_at')
            ->first(['id', 'version', 'content']);

        if ($doc === null) {
            continue;
        }

        DB::table('legal_document_acceptances')->insert([
            'user_id' => $user->id,
            'legal_document_id' => $doc->id,
            'document_type' => $type,
            'document_version' => $doc->version,
            'document_hash' => hash('sha256', (string) $doc->content),
            'accepted_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Symfony',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

test('AUTO APPROVE: clear valid report is decided and published without a moderator', function () {
    $reportId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($reportId, amdCameraImage());

    $result = amdProcess(
        $reportId,
        amdText(amdTextLegit()),
        amdVision(amdVisionClean()),
    );

    $report = Report::find($reportId);

    expect($result['action'])->toBe('approve')
        ->and($report->status)->toBe('approved')
        ->and($report->ai_analyzed_at)->not->toBeNull()
        ->and($report->ai_analysis['action'])->toBe('approve')
        ->and($report->moderation_message)->toStartWith('AI approved')
        ->and($report->authenticity_score)->not->toBeNull()
        ->and(DB::table('moderation_queues')->where('content_type', 'report')
            ->where('content_id', $reportId)->value('status'))->toBe('approved')
        // Publishing: approved reports feed public queries; a proximity alert exists.
        ->and(DB::table('alerts')->where('source_type', 'report')
            ->where('source_id', $reportId)->count())->toBe(1);
});

test('AUTO REJECT: invalid location is rejected without a moderator', function () {
    $reportId = amdReport([
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'gps_verification_status' => 'verified',
    ]);
    amdAddMedia($reportId, amdCameraImage());

    $result = amdProcess($reportId);

    $report = Report::find($reportId);

    expect($result['action'])->toBe('reject')
        ->and($report->status)->toBe('rejected')
        ->and($report->ai_analyzed_at)->not->toBeNull()
        ->and($report->ai_analysis['action'])->toBe('reject')
        ->and($report->moderation_message)->toStartWith('AI rejected')
        ->and(DB::table('moderation_queues')->where('content_type', 'report')
            ->where('content_id', $reportId)->value('status'))->toBe('rejected');
});

test('HUMAN REVIEW: conflicting image evidence routes to pending for a moderator', function () {
    $reportId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($reportId, amdCameraImage());

    $result = amdProcess(
        $reportId,
        amdText(amdTextLegit()),
        amdVision(amdVisionScreen()),
    );

    $report = Report::find($reportId);

    expect($result['action'])->toBe('pending-review')
        ->and($report->status)->toBe('pending')
        ->and($report->ai_analyzed_at)->not->toBeNull()
        ->and($report->ai_analysis['action'])->toBe('pending-review')
        ->and($report->moderation_message)->toContain('needs moderator review')
        ->and(DB::table('moderation_queues')->where('content_type', 'report')
            ->where('content_id', $reportId)->value('status'))->toBe('pending');
});

test('DECISION PERSISTENCE: every decision writes status, analysis, message and queue row', function () {
    $reportId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($reportId, amdCameraImage());

    amdProcess($reportId, amdText(amdTextLegit()), amdVision(amdVisionClean()));

    $report = Report::find($reportId);
    $analysis = $report->ai_analysis;

    expect($analysis)->toBeArray()
        ->and($analysis['action'] ?? null)->toBe('approve')
        ->and($analysis['image_check']['verdict'] ?? null)->toBe('clean')
        ->and($analysis['image_check']['images'] ?? [])->toHaveCount(1)
        ->and($analysis['image_check']['images'][0]['screening'] ?? null)->toBeArray()
        ->and($report->status)->toBe('approved')
        ->and($report->ai_analyzed_at)->not->toBeNull()
        ->and($report->moderation_message)->not->toBeNull()
        ->and($report->authenticity_score)->not->toBeNull();
});

test('AI UNAVAILABLE + sufficient deterministic evidence: automated decision, not human review', function () {
    $reportId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($reportId, amdCameraImage());

    // Both AI chains completely down - exactly the Report #296 conditions.
    $result = amdProcess($reportId);

    $report = Report::find($reportId);

    expect($result['action'])->toBe('approve')
        ->and($report->status)->toBe('approved')
        ->and($report->ai_analyzed_at)->not->toBeNull()
        ->and($report->ai_analysis['ai_status'] ?? null)->toBe('unavailable')
        ->and($report->ai_analysis['image_check']['images'] ?? [])->toHaveCount(1)
        ->and($report->ai_analysis['image_check']['images'][0]['screening'] ?? null)->toBeArray();
});

test('AI UNAVAILABLE + insufficient evidence: routes to HUMAN REVIEW with decided analysis', function () {
    // Same outage, but no GPS verification at all - engine lacks full evidence.
    $reportId = amdReport();
    amdAddMedia($reportId, amdCameraImage());

    $result = amdProcess($reportId);

    $report = Report::find($reportId);

    expect($result['action'])->toBe('pending-review')
        ->and($report->status)->toBe('pending')
        ->and($report->ai_analyzed_at)->not->toBeNull()
        ->and($report->ai_analysis['ai_status'] ?? null)->toBe('unavailable')
        ->and($report->moderation_message)->not->toBeNull();
});

test('DETERMINISTIC SCREEN SIGNAL: screenshot-like image goes to human review, never auto-reject', function () {
    // No EXIF + square web-size = classic re-upload/screenshot heuristic -
    // evidence only. Existing architecture: 'suspicious' => human review.
    $reportId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($reportId, amdJpeg(500, 500));

    $result = amdProcess($reportId);

    $report = Report::find($reportId);
    $entry = $report->ai_analysis['image_check']['images'][0] ?? [];

    expect($result['action'])->toBe('pending-review')
        ->and($report->status)->toBe('pending')
        ->and($report->ai_analysis['image_check']['verdict'] ?? null)->toBe('suspicious')
        ->and($entry['ai_skipped'] ?? null)->toBeTrue()
        ->and($entry['screening'] ?? null)->toBeArray();
});

test('ONE IMAGE: one stored image result with screening during full AI outage', function () {
    $reportId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($reportId, amdCameraImage());

    amdProcess($reportId);

    $report = Report::find($reportId);
    $images = $report->ai_analysis['image_check']['images'] ?? [];

    expect($images)->toHaveCount(1)
        ->and($images[0]['screening'] ?? null)->toBeArray()
        ->and($images[0]['media_id'] ?? null)->not->toBeNull();
});

test('MULTIPLE IMAGES: two uploaded images produce two stored results', function () {
    $reportId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($reportId, amdCameraImage());
    amdAddMedia($reportId, amdJpeg(800, 600, amdTiff([0x010F => 'TestCamB', 0x0110 => 'QX1'])));

    amdProcess($reportId);

    $report = Report::find($reportId);
    $images = $report->ai_analysis['image_check']['images'] ?? [];

    expect($images)->toHaveCount(2);
});

test('NO IMAGE: a genuinely image-less report produces zero image results', function () {
    $reportId = amdReport(['gps_verification_status' => 'verified']);

    amdProcess($reportId);

    $report = Report::find($reportId);

    expect($report->ai_analysis['image_check']['images'] ?? [])->toHaveCount(0)
        ->and($report->ai_analysis['image_check']['reviewed'] ?? null)->toBe(0)
        ->and($report->ai_analysis['image_check']['message'] ?? null)->toBe('No images attached')
        ->and($report->ai_analyzed_at)->not->toBeNull();
});

test('END-TO-END SUBMIT: stored image, screening, confidence, decision, approved status', function () {
    $user = amdUser();
    amdAcceptLegal($user);

    $this->app->instance(
        ReportAnalysisService::class,
        new ReportAnalysisService(amdText(amdTextLegit()), amdVision(amdVisionClean()))
    );

    $tmp = tempnam(sys_get_temp_dir(), 'amd_up');
    file_put_contents($tmp, amdCameraImage());
    $uploaded = new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);

    $response = $this->actingAs($user)->post('/api/v1/reports', [
        'title' => 'Landslide blocks Prithvi Highway near Muglin '.mt_rand(100000, 999999),
        'description' => 'A landslide has blocked the Prithvi Highway near Muglin; vehicles are stuck on both sides.',
        'category_id' => amdCategoryId(),
        'priority' => 'medium',
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'is_live_capture' => true,
        'capture_latitude' => 27.7172,
        'capture_longitude' => 85.3240,
        'image' => $uploaded,
    ], ['Accept' => 'application/json']);

    $response->assertStatus(201);

    $report = Report::where('user_id', $user->id)->latest('id')->first();
    $GLOBALS['amd_report_ids'][] = $report->id;

    // The whole contract: image stored → analysis ran inline (sync queue) →
    // screening persisted → confidence/decision persisted → status applied
    // → moderation-queue row moved to the decision. Under the old dispatch
    // order (before media/modq) this test fails on images=0 and queue=pending.
    expect(DB::table('report_media')->where('report_id', $report->id)->count())->toBe(1)
        ->and($report->status)->toBe('approved')
        ->and($report->ai_analyzed_at)->not->toBeNull()
        ->and($report->ai_analysis['action'] ?? null)->toBe('approve')
        ->and($report->ai_analysis['image_check']['images'] ?? [])->toHaveCount(1)
        ->and($report->ai_analysis['image_check']['images'][0]['screening'] ?? null)->toBeArray()
        ->and($report->moderation_message)->toStartWith('AI approved')
        ->and(DB::table('moderation_queues')->where('content_type', 'report')
            ->where('content_id', $report->id)->value('status'))->toBe('approved');
});

test('MODERATOR QUEUE: only HUMAN REVIEW reports appear in the default admin queue', function () {
    $approvedId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($approvedId, amdCameraImage());
    amdProcess($approvedId, amdText(amdTextLegit()), amdVision(amdVisionClean()));

    $pendingId = amdReport(['gps_verification_status' => 'verified']);
    amdAddMedia($pendingId, amdCameraImage());
    amdProcess($pendingId, amdText(amdTextLegit()), amdVision(amdVisionScreen()));

    $approvedTitle = Report::find($approvedId)->title;
    $pendingTitle = Report::find($pendingId)->title;

    expect(Report::find($approvedId)->status)->toBe('approved')
        ->and(Report::find($pendingId)->status)->toBe('pending');

    $admin = amdUser('admin');
    $response = $this->actingAs($admin)->get(route('admin.reports'));

    $response->assertOk()
        ->assertSee($pendingTitle)
        ->assertDontSee($approvedTitle);
});
