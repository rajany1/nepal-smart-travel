<?php

use App\Jobs\SendNearbyPushNotification;
use App\Jobs\TranslateContent;
use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\User;
use App\Models\UserFraudProfile;
use App\Services\Ai\AiFallbackRouter;
use App\Services\Ai\ReportAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Location integrity layer - end-to-end submission scenarios
|--------------------------------------------------------------------------
|
| Scenario matrix requested for the mock/fake-location work:
|
|  1. Normal GPS        → accepted normally, integrity 'genuine'
|  2. VPN (foreign IP)  → NOT flagged: IP never influences integrity
|  3. Android mock      → DIRECT REJECT, AI review skipped (token saving),
|                         audit + fraud score still recorded
|  4. Detector down     → 'cannot_determine' (never 'genuine'), still works
|  5. Photo/capture GPS conflict → risk signal up, no new auto-fraud here
|  6. Multiple signals  → high combined fraud risk → existing workflow decides
|  7. Non-mock signal   → AI still runs + moderation reviews (boundary: only
|                         the mock claim short-circuits the AI)
|
| All client location_integrity values are untrusted input — the tests
| assert what the SERVER derives, not what the client claims.
|
| Live-DB suite (RefreshDatabase disabled): every row/file created here is
| removed in afterEach. Reports attach to the EXISTING production category
| report_categories id=7 - never created, never deleted. Bus is faked for
| push/translation side effects only: AnalyzeReport runs for real (sync
| queue) exactly as in production. Function names are prefixed li… to avoid
| collisions with other suites.
|
*/

beforeEach(function () {
    $GLOBALS['li_report_ids'] = [];
    $GLOBALS['li_user_ids'] = [];
    $GLOBALS['li_files'] = [];

    Bus::fake([
        TranslateContent::class,
        SendNearbyPushNotification::class,
    ]);
});

afterEach(function () {
    $reportIds = $GLOBALS['li_report_ids'] ?? [];
    $userIds = $GLOBALS['li_user_ids'] ?? [];

    DB::table('report_media')->whereIn('report_id', $reportIds)->delete();
    DB::table('alerts')->where('source_type', 'report')->whereIn('source_id', $reportIds)->delete();
    DB::table('moderation_queues')->where('content_type', 'report')->whereIn('content_id', $reportIds)->delete();
    DB::table('report_security_logs')->whereIn('user_id', $userIds)->delete();
    DB::table('user_fraud_profiles')->whereIn('user_id', $userIds)->delete();
    DB::table('xp_transactions')
        ->where(function ($q) use ($userIds, $reportIds) {
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

    foreach ($GLOBALS['li_files'] ?? [] as $url) {
        Storage::disk('public')->delete($url);
    }

    expect(DB::table('reports')->whereIn('id', $reportIds)->count())->toBe(0)
        ->and(DB::table('report_security_logs')->whereIn('user_id', $userIds)->count())->toBe(0)
        ->and(DB::table('user_fraud_profiles')->whereIn('user_id', $userIds)->count())->toBe(0);

    $GLOBALS['li_report_ids'] = [];
    $GLOBALS['li_user_ids'] = [];
    $GLOBALS['li_files'] = [];
});

/** Same production category slot the moderation suite uses (lookup only). */
function liCategoryId(): int
{
    $category = ReportCategorie::query()->find(7);

    if ($category === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 is missing. '
            .'These tests must use that real record and must not create fixture categories.'
        );
    }

    return (int) $category->id;
}

function liUser(): User
{
    $user = User::create([
        'name' => 'LI User',
        'email' => 'li_'.Str::random(12).'@example.com',
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

    $GLOBALS['li_user_ids'][] = $user->id;

    return $user;
}

function liAcceptLegal(User $user): void
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

function liTiff(array $tags): string
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

function liJpeg(int $width, int $height, ?string $tiff = null): string
{
    $soi = "\xFF\xD8";
    $app0 = "\xFF\xE0".pack('n', 16)."JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

    $app1 = '';
    if ($tiff !== null) {
        $payload = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
    }

    $sof0 = "\xFF\xC0".pack('n', 11)."\x08".pack('nn', $height, $width)."\x01\x01\x11\x00";

    // Random tail keeps content hashes unique across submissions (the exact
    // duplicate check would otherwise block later tests) and satisfies the
    // "minimum 10240 bytes" upload rule.
    return $soi.$app0.$app1.$sof0."\xFF\xD9".random_bytes(11000);
}

function liCameraImage(): string
{
    return liJpeg(1000, 750, liTiff([
        0x010F => 'TestCam',
        0x0110 => 'PX9',
        0x0132 => '2026:01:02 03:04:05',
    ]));
}

function liUploadedImage(): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'li_up');
    file_put_contents($tmp, liCameraImage());

    return new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);
}

function liOutage(): AiFallbackRouter
{
    return new AiFallbackRouter([]);
}

function liText(array $result): AiFallbackRouter
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

function liVision(array $result): AiFallbackRouter
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

function liTextLegit(): array
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

function liVisionClean(): array
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

/** Bind the deterministic (stubbed) decision engine for submit tests. */
function liBindCleanAi(): void
{
    app()->instance(
        ReportAnalysisService::class,
        new ReportAnalysisService(liText(liTextLegit()), liVision(liVisionClean()))
    );
}

/**
 * POST /api/v1/reports with sensible defaults; overrides carry the
 * per-scenario location/integrity payload.
 */
function liSubmit(TestCase $test, User $user, array $overrides = [], array $server = []): TestResponse
{
    $data = array_merge([
        'title' => 'Landslide blocks Prithvi Highway near Muglin '.mt_rand(100000, 999999),
        'description' => 'A landslide has blocked the Prithvi Highway near Muglin; vehicles are stuck on both sides.',
        'category_id' => liCategoryId(),
        'priority' => 'medium',
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'is_live_capture' => true,
        'capture_latitude' => 27.7172,
        'capture_longitude' => 85.3240,
        'location_accuracy' => 8.5,
        'location_timestamp' => now()->toIso8601String(),
        'image' => liUploadedImage(),
    ], $overrides);

    return $test->actingAs($user)->post('/api/v1/reports', $data, array_merge([
        'Accept' => 'application/json',
    ], $server));
}

function liReportFor(User $user): Report
{
    $report = Report::where('user_id', $user->id)->latest('id')->firstOrFail();
    $GLOBALS['li_report_ids'][] = $report->id;

    return $report;
}

function liSecurityLog(User $user): ?object
{
    return DB::table('report_security_logs')
        ->where('user_id', $user->id)
        ->where('reason', 'location_integrity_suspicious')
        ->latest('id')
        ->first();
}

function liIntegrityClient(string $status, ?bool $mock = false): array
{
    return [
        'status' => $status,
        'mock_location_detected' => $mock,
        'detection_source' => 'android',
    ];
}

test('TEST 1 normal GPS: report accepted normally with genuine integrity', function () {
    $user = liUser();
    liAcceptLegal($user);
    liBindCleanAi();

    $response = liSubmit($this, $user, [
        'location_integrity' => liIntegrityClient('genuine'),
    ]);

    $response->assertStatus(201);

    $report = liReportFor($user);

    expect($report->location_integrity_status)->toBe('genuine')
        ->and((bool) $report->mock_location_detected)->toBeFalse()
        ->and($report->location_integrity_source)->toBe('android')
        ->and((float) $report->location_accuracy)->toBe(8.5)
        ->and($report->location_timestamp)->not->toBeNull()
        // Clean pipeline is unaffected: still auto-approvable.
        ->and($report->status)->toBe('approved')
        ->and($report->provenance)->toBe('unverified_client')
        ->and(liSecurityLog($user))->toBeNull()
        ->and(UserFraudProfile::where('user_id', $user->id)->exists())->toBeFalse();
});

test('TEST 2 VPN: foreign IP with genuine Nepali GPS is NOT classified fake', function () {
    $user = liUser();
    liAcceptLegal($user);
    liBindCleanAi();

    // Foreign IP (VPN / datacentre egress) in both REMOTE_ADDR and
    // X-Forwarded-For — GPS stays a genuine Nepal fix.
    $response = liSubmit(
        $this,
        $user,
        ['location_integrity' => liIntegrityClient('genuine')],
        ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.7']
    );

    $response->assertStatus(201);

    $report = liReportFor($user);

    // The IP must not have contaminated the integrity evaluation or the
    // fraud pipeline: integrity stays genuine, nothing gets flagged.
    expect($report->location_integrity_status)->toBe('genuine')
        ->and($report->provenance)->toBe('unverified_client')
        ->and($report->status)->toBe('approved')
        ->and(liSecurityLog($user))->toBeNull()
        ->and(UserFraudProfile::where('user_id', $user->id)->exists())->toBeFalse();
});

test('TEST 3 Android mock location: direct reject, AI review skipped to save tokens', function () {
    $user = liUser();
    liAcceptLegal($user);
    liBindCleanAi();

    $response = liSubmit($this, $user, [
        'location_integrity' => liIntegrityClient('mock_detected', true),
    ]);

    // Submission still reaches the backend — mock detection never blocks it.
    $response->assertStatus(201);

    $report = liReportFor($user);
    $log = liSecurityLog($user);
    $fraud = UserFraudProfile::where('user_id', $user->id)->first();
    $logSignals = $log ? (json_decode($log->metadata, true)['signals'] ?? []) : [];

    expect($report->location_integrity_status)->toBe('suspicious')
        ->and((bool) $report->mock_location_detected)->toBeTrue()
        ->and($report->provenance)->toBe('suspicious')
        // DIRECT REJECT: mock location detected → reject without any AI
        // review (text + vision routers never consulted → zero API tokens).
        ->and($report->status)->toBe('rejected')
        ->and($report->ai_analysis['action'] ?? null)->toBe('reject')
        ->and($report->ai_analysis['integrity_gate'] ?? null)->toBe('mock_location_detected')
        // Proof the vision AI never ran despite an attached photo:
        ->and($report->ai_analysis['image_check']['reviewed'] ?? null)->toBe(0)
        ->and($report->ai_analysis['image_check']['images'] ?? [])->toBe([])
        ->and($report->moderation_message)->toContain('mock location detected')
        ->and($report->moderation_message)->not->toContain('AI rejected')
        // Audit trail + user fraud score increased (non-blocking).
        ->and($log)->not->toBeNull()
        ->and($logSignals)->toContain('mock_location_reported')
        ->and($fraud)->not->toBeNull()
        ->and((int) $fraud->fraud_score)->toBeGreaterThanOrEqual(15)
        ->and((bool) $fraud->is_suspicious)->toBeFalse()
        // Queue reflects the direct rejection (still overridable by moderators).
        ->and(DB::table('moderation_queues')->where('content_type', 'report')
            ->where('content_id', $report->id)->value('status'))->toBe('rejected');
});

test('TEST 4 detection unavailable: cannot_determine reported, report still works', function () {
    $user = liUser();
    liAcceptLegal($user);
    liBindCleanAi();

    $response = liSubmit($this, $user, [
        'location_integrity' => [
            'status' => 'cannot_determine',
            'mock_location_detected' => false,
            'detection_source' => 'unavailable',
        ],
    ]);

    $response->assertStatus(201);

    $report = liReportFor($user);

    // No false "genuine" claim — and no punishment either: the detector
    // being unavailable must not route honest users into fraud scoring.
    expect($report->location_integrity_status)->toBe('cannot_determine')
        ->and($report->location_integrity_status)->not->toBe('genuine')
        ->and($report->status)->toBe('approved')
        ->and($report->provenance)->toBe('unverified_client')
        ->and(liSecurityLog($user))->toBeNull()
        ->and(UserFraudProfile::where('user_id', $user->id)->exists())->toBeFalse();
});

test('TEST 5 photo/capture GPS conflict: risk signal rises, submission still accepted', function () {
    $user = liUser();
    liAcceptLegal($user);
    liBindCleanAi();

    // Report GPS = Location A (Kathmandu), photo-capture GPS = Location B
    // (~55km away): the image has no EXIF GPS (image_picker strips it), so
    // the capture-coordinate fallback produces the conflict.
    $response = liSubmit($this, $user, [
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'capture_latitude' => 28.2172,
        'capture_longitude' => 85.3240,
        'location_integrity' => liIntegrityClient('genuine'),
    ]);

    $response->assertStatus(201);

    $report = liReportFor($user);
    $log = liSecurityLog($user);
    $fraud = UserFraudProfile::where('user_id', $user->id)->first();
    $logSignals = $log ? (json_decode($log->metadata, true)['signals'] ?? []) : [];

    // Risk signal increased…
    expect($report->gps_verification_status)->toBe('mismatched')
        ->and($report->location_integrity_status)->toBe('suspicious')
        ->and($log)->not->toBeNull()
        ->and($logSignals)->toContain('photo_location_conflict')
        ->and($fraud)->not->toBeNull()
        ->and((int) $fraud->fraud_score)->toBeGreaterThanOrEqual(10)
        ->and((bool) $fraud->is_suspicious)->toBeFalse()
        // …and the API accepted the report (no new auto-fraud from this
        // layer). The final status below is the PRE-EXISTING engine policy
        // for gps mismatch, unchanged by the location-integrity work.
        ->and($report->status)->toBe('rejected');
});

test('TEST 6 multiple signals: high combined fraud risk, existing workflow decides', function () {
    $user = liUser();
    liAcceptLegal($user);
    liBindCleanAi();

    // Previous report 20 minutes ago in Kathmandu; the new submission is in
    // western Nepal (~475km) → implied speed ~1400 km/h = impossible movement.
    $previousId = DB::table('reports')->insertGetId([
        'user_id' => $user->id,
        'uuid' => Str::uuid()->toString(),
        'title' => 'Previous incident report near Ratna Park '.mt_rand(100000, 999999),
        'description' => 'A previous incident report filed near Ratna Park in Kathmandu earlier today.',
        'category_id' => liCategoryId(),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'approved',
        'provenance' => 'unverified_client',
        'source' => 'user',
        'priority' => 'medium',
        'created_at' => now()->subMinutes(20),
        'updated_at' => now()->subMinutes(20),
    ]);
    $GLOBALS['li_report_ids'][] = $previousId;

    $response = liSubmit($this, $user, [
        'latitude' => 28.7,
        'longitude' => 80.6,
        'district' => 'Dhangadhi',
        // Photo-capture GPS still in Kathmandu → EXIF/capture conflict too.
        'capture_latitude' => 27.7172,
        'capture_longitude' => 85.3240,
        'location_integrity' => liIntegrityClient('mock_detected', true),
    ]);

    $response->assertStatus(201);

    $report = liReportFor($user);
    $log = liSecurityLog($user);
    $fraud = UserFraudProfile::where('user_id', $user->id)->first();
    $logSignals = $log ? (json_decode($log->metadata, true)['signals'] ?? []) : [];

    expect($report->location_integrity_status)->toBe('suspicious')
        ->and((bool) $report->mock_location_detected)->toBeTrue()
        ->and($report->gps_verification_status)->toBe('mismatched')
        ->and($report->provenance)->toBe('suspicious')
        ->and($logSignals)->toContain('mock_location_reported')
        ->and($logSignals)->toContain('photo_location_conflict')
        ->and($logSignals)->toContain('impossible_movement')
        // mock (15) + impossible movement (15) + residual integrity (10) = 40
        ->and((int) $fraud->fraud_score)->toBeGreaterThanOrEqual(40)
        ->and((int) $fraud->fraud_score)->toBeLessThan(80)
        ->and((bool) $fraud->is_suspicious)->toBeFalse()
        // The mock-location gate fires first (direct reject, no AI); the
        // EXISTING moderation workflow still owns the queue entry —
        // overridable by moderators.
        ->and($report->status)->toBe('rejected')
        ->and($report->ai_analysis['integrity_gate'] ?? null)->toBe('mock_location_detected')
        ->and(DB::table('moderation_queues')->where('content_type', 'report')
            ->where('content_id', $report->id)->value('status'))->toBe('rejected');
});

test('TEST 7 non-mock integrity signal: AI still runs, moderation still reviews', function () {
    $user = liUser();
    liAcceptLegal($user);
    liBindCleanAi();

    // Previous report 20 minutes ago in Kathmandu; new submission ~475km
    // west → impossible movement fires, but NO mock claim and NO GPS
    // mismatch (capture coordinates match the report location).
    $previousId = DB::table('reports')->insertGetId([
        'user_id' => $user->id,
        'uuid' => Str::uuid()->toString(),
        'title' => 'Earlier incident report near Ratna Park '.mt_rand(100000, 999999),
        'description' => 'An earlier incident report filed near Ratna Park in Kathmandu earlier today.',
        'category_id' => liCategoryId(),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'approved',
        'provenance' => 'unverified_client',
        'source' => 'user',
        'priority' => 'medium',
        'created_at' => now()->subMinutes(20),
        'updated_at' => now()->subMinutes(20),
    ]);
    $GLOBALS['li_report_ids'][] = $previousId;

    $response = liSubmit($this, $user, [
        'latitude' => 28.7,
        'longitude' => 80.6,
        'district' => 'Dhangadhi',
        'capture_latitude' => 28.7,
        'capture_longitude' => 80.6,
        'location_integrity' => liIntegrityClient('genuine'),
    ]);

    $response->assertStatus(201);

    $report = liReportFor($user);
    $log = liSecurityLog($user);
    $logSignals = $log ? (json_decode($log->metadata, true)['signals'] ?? []) : [];

    // Boundary guard: ONLY the mock claim short-circuits the AI. Any other
    // integrity signal keeps the normal pipeline — AI runs (here: the clean
    // stub approves text+image) and the integrity veto routes it to humans.
    expect($report->location_integrity_status)->toBe('suspicious')
        ->and((bool) $report->mock_location_detected)->toBeFalse()
        ->and($logSignals)->toContain('impossible_movement')
        ->and($logSignals)->not->toContain('mock_location_reported')
        // AI DID run: vision check reviewed the attached photo.
        ->and($report->ai_analysis['image_check']['reviewed'] ?? 0)->toBe(1)
        ->and($report->ai_analysis['integrity_gate'] ?? null)->toBeNull()
        // Integrity vetoes auto-approval → human moderation decides.
        ->and($report->ai_analysis['action'] ?? null)->toBe('pending-review')
        ->and($report->status)->toBe('pending')
        ->and($report->moderation_message)->toContain('location integrity flagged')
        ->and(DB::table('moderation_queues')->where('content_type', 'report')
            ->where('content_id', $report->id)->value('status'))->toBe('pending');
});
