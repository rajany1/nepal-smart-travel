<?php

use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Report Details — Image Integrity endpoint tests
|--------------------------------------------------------------------------
|
| Verifies the admin-only exposure of stored screening indicators:
| - admins/moderators can view the section (read-only display)
| - guests and regular users cannot
| - the public report API never leaks internal screening metadata
| - opening the page triggers no AI job, no re-analysis, no state change
|
| All rows created here are cleaned up in afterEach (live-DB suite).
| Test reports use the EXISTING production category report_categories
| id=7 (see ariiCategoryId) — that record is only read, never created
| and never deleted.
|
*/

beforeEach(function () {
    $GLOBALS['arii_report_ids'] = [];
    $GLOBALS['arii_user_ids'] = [];
});

afterEach(function () {
    DB::table('reports')->whereIn('id', $GLOBALS['arii_report_ids'] ?? [])->delete();
    DB::table('users')->whereIn('id', $GLOBALS['arii_user_ids'] ?? [])->delete();
    $GLOBALS['arii_report_ids'] = [];
    $GLOBALS['arii_user_ids'] = [];
});

function ariiCreateUser(string $role = 'user'): User
{
    $user = User::create([
        'name' => 'ARII '.ucfirst($role),
        'email' => 'arii_'.Str::random(12).'@example.com',
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

    $GLOBALS['arii_user_ids'][] = $user->id;

    return $user;
}

/**
 * The one exact production category these tests attach reports to:
 * report_categories id=7 — the category of Report #190, the real
 * image-integrity scenario report (verified during investigation), which is
 * also the canonical DatabaseSeeder slot for 'Services & Utilities' and a
 * route-content category in RouteRelevanceService::ROUTE_CONTENT_CATEGORY_IDS.
 *
 * Lookup only: NEVER created, NEVER deleted here. Fails with an explicit
 * message when the record is missing — no fixture fallback. Keyed by
 * primary key because the name column is not unique in this table.
 */
function ariiCategoryId(): int
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

function ariiCreateReport(array $overrides = []): int
{
    $id = DB::table('reports')->insertGetId(array_merge([
        'user_id' => ariiCreateUser()->id,
        'uuid' => Str::uuid()->toString(),
        'title' => 'ARII image integrity report '.Str::random(6),
        'description' => 'Image integrity test report description for admin display checks',
        'category_id' => ariiCategoryId(),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    $GLOBALS['arii_report_ids'][] = $id;

    return $id;
}

/**
 * Stored analysis payload with a strong deterministic screening signal —
 * exactly the shape ReportAnalysisService writes to reports.ai_analysis.
 */
function ariiAnalysisPayload(): array
{
    return [
        'action' => 'pending-review',
        'summary' => 'Stored analysis summary for admin display testing.',
        'image_check' => [
            'reviewed' => 1,
            'verdict' => 'suspicious',
            'message' => 'suspicious',
            'images' => [[
                'media_id' => 123,
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
            ]],
        ],
    ];
}

it('shows the Image Integrity section with stored screening indicators to admins', function () {
    $admin = ariiCreateUser('admin');
    $reportId = ariiCreateReport(['ai_analysis' => json_encode(ariiAnalysisPayload())]);

    $response = $this->actingAs($admin)->get(route('admin.reports.view', $reportId));

    $response->assertOk()
        ->assertSee('Image Integrity', false)
        ->assertSee('Screenshot viewport dimensions', false)
        ->assertSee('Manual review recommended', false)
        ->assertSee('EXIF camera information unavailable', false)
        ->assertSee('GPS consistency', false)
        ->assertSee('Exact duplicate', false)
        ->assertSee('Moiré / display pattern', false)
        ->assertSee('Display structure', false)
        ->assertSee('Overall stored confidence', false)
        ->assertSee('Technical details', false)
        ->assertSee('Per-image results', false);

    // Display must not modify the stored payload (evidence survives untouched).
    $stored = Report::findOrFail($reportId)->ai_analysis;
    expect($stored['image_check']['images'][0]['screening']['strong'])->toBeTrue()
        ->and($stored['image_check']['images'][0]['screening']['signals'][0]['signal'])->toBe('known_screen_viewport_dimensions');
});

it('redirects guests away from report details', function () {
    $reportId = ariiCreateReport();

    $this->get(route('admin.reports.view', $reportId))->assertRedirect();
});

it('forbids regular users from report details', function () {
    $reportId = ariiCreateReport();

    $this->actingAs(ariiCreateUser())
        ->get(route('admin.reports.view', $reportId))
        ->assertForbidden();
});

it('loads a legacy report without stored analysis as Not evaluated', function () {
    $admin = ariiCreateUser('admin');
    $reportId = ariiCreateReport(['ai_analysis' => null]);

    $this->actingAs($admin)
        ->get(route('admin.reports.view', $reportId))
        ->assertOk()
        ->assertSee('Image Integrity', false)
        ->assertSee('Not evaluated', false);
});

it('loads a BIPAD-shaped report analysis without image data', function () {
    $admin = ariiCreateUser('admin');
    $reportId = ariiCreateReport(['ai_analysis' => json_encode([
        'bipad_hazard' => 'flood',
        'bipad_severity' => 'high',
        'bipad_verified' => true,
    ])]);

    $this->actingAs($admin)
        ->get(route('admin.reports.view', $reportId))
        ->assertOk()
        ->assertSee('Image Integrity', false)
        ->assertSee('Not evaluated', false);
});

it('triggers no AI job, no re-analysis and no state change when opening report details', function () {
    Queue::fake();

    $admin = ariiCreateUser('admin');
    $reportId = ariiCreateReport([
        'ai_analysis' => json_encode(ariiAnalysisPayload()),
        'moderation_message' => 'Stored moderation message must not change',
        'authenticity_score' => 0.42,
        'ai_analyzed_at' => '2026-09-01 12:00:00',
    ]);
    $before = DB::table('reports')->where('id', $reportId)->first([
        'status', 'moderation_message', 'authenticity_score', 'ai_analyzed_at', 'updated_at', 'verified_by', 'verified_at',
    ]);

    $this->actingAs($admin)->get(route('admin.reports.view', $reportId))
        ->assertOk()
        ->assertSee('Overall stored confidence: 42%', false)
        ->assertSee('not a Vision AI image score', false);

    Queue::assertNothingPushed();

    $after = DB::table('reports')->where('id', $reportId)->first([
        'status', 'moderation_message', 'authenticity_score', 'ai_analyzed_at', 'updated_at', 'verified_by', 'verified_at',
    ]);

    expect((array) $after)->toBe((array) $before);
});

it('labels a Report #190-class stored score honestly when the Vision AI image check was unavailable', function () {
    $admin = ariiCreateUser('admin');

    // Exact stored shape after the persistence fix: outage entry WITH
    // screening evidence and a camera EXIF trace recovered by the
    // pure-PHP fallback (this server has no ext-exif).
    $reportId = ariiCreateReport([
        'ai_analysis' => json_encode([
            'action' => 'pending-review',
            'summary' => 'Stored analysis summary.',
            'image_check' => [
                'reviewed' => 0,
                'verdict' => 'unverifiable',
                'message' => 'Images attached but none could be analyzed — needs moderator review',
                'images' => [[
                    'media_id' => 5,
                    'verdict' => 'unverifiable',
                    'reason' => 'AI providers all unavailable (quota/rate limit)',
                    'exif_trace' => [
                        'kind' => 'camera',
                        'make' => 'Xiaomi',
                        'model' => '21121119SG',
                        'has_lens_data' => true,
                        'reason' => 'Real camera EXIF trace present',
                    ],
                    'provider_used' => null,
                    'screening' => [
                        'evaluated' => true,
                        'strong' => false,
                        'signals' => [],
                        'facts' => ['mime' => 'image/jpeg', 'camera_trace' => 'present', 'aspect_ratio' => 1.328, 'viewport_match' => false],
                    ],
                ]],
            ],
        ]),
        'moderation_message' => 'Images attached but none could be analyzed — needs moderator review',
        'authenticity_score' => 0.90,
    ]);

    $this->actingAs($admin)->get(route('admin.reports.view', $reportId))
        ->assertOk()
        // Blade AI Review block: score renamed, never framed as AI image trust.
        ->assertSee('Overall stored confidence: 90%', false)
        ->assertSee('not a Vision AI image score', false)
        ->assertSee('Vision AI image check was unavailable', false)
        // Image Integrity section: camera trace recovered, screening persisted.
        ->assertSee('Camera metadata (make/model) is present', false)
        ->assertSee('Per-image screening stored', false)
        ->assertSee('1 of 1 image result(s)', false)
        ->assertSee('Moiré / display pattern', false)
        ->assertSee('Unavailable', false);
});

it('does not expose internal screening metadata on the public report API', function () {
    $reportId = ariiCreateReport([
        'status' => 'approved',
        'ai_analysis' => json_encode(ariiAnalysisPayload()),
        'moderation_message' => 'internal moderation message',
        'authenticity_score' => 0.42,
        'gps_verification_status' => 'mismatched',
        'gps_distance_km' => 12.5,
        'photo_gps_lat' => 27.71,
        'photo_gps_lng' => 85.32,
    ]);

    $response = $this->getJson('/api/v1/reports/'.$reportId);

    $response->assertOk();
    $data = $response->json('data');

    // Existing public contract fields remain present.
    expect($data)->toHaveKeys(['id', 'uuid', 'title', 'description', 'status', 'priority', 'image_urls', 'created_at']);

    // Internal screening / moderation / AI fields must not leak.
    expect($data)->not->toHaveKey('ai_analysis')
        ->and($data)->not->toHaveKey('moderation_message')
        ->and($data)->not->toHaveKey('authenticity_score')
        ->and($data)->not->toHaveKey('gps_verification_status')
        ->and($data)->not->toHaveKey('gps_distance_km')
        ->and($data)->not->toHaveKey('photo_gps_lat')
        ->and($data)->not->toHaveKey('verified_by')
        ->and($data)->not->toHaveKey('is_live_capture');

    $encoded = json_encode($data);
    expect($encoded)->not->toContain('screening')
        ->and($encoded)->not->toContain('known_screen_viewport_dimensions')
        ->and($encoded)->not->toContain('exif_trace')
        ->and($encoded)->not->toContain('ai_skipped');
});

it('keeps public report response fields unchanged for a plain approved report', function () {
    $reportId = ariiCreateReport(['status' => 'approved']);

    $data = $this->getJson('/api/v1/reports/'.$reportId)->assertOk()->json('data');

    expect($data)->toHaveKeys([
        'id', 'uuid', 'title', 'description', 'category_id', 'category_name',
        'priority', 'status', 'latitude', 'longitude', 'district',
        'helpful_count', 'unhelpful_count', 'comments_count',
        'reporter_name', 'reporter_id', 'image_urls', 'image_url',
        'created_at', 'updated_at', 'time_ago', 'user_reaction',
    ]);
});
