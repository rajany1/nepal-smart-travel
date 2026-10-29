<?php

use App\Models\ReportCategorie;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Reports — moderator access tests
|--------------------------------------------------------------------------
|
| Confirms that a moderator (not only a full admin) can open the Reports
| command center and a report detail page, see the approve/reject controls,
| and actually approve or reject a pending report. The controller already
| checks the approve_reports permission; these tests pin that behaviour to
| the moderator role so a future refactor cannot silently admin-gate it.
|
| All rows created here are cleaned up in afterEach (live-DB suite).
|
*/

beforeEach(function () {
    $GLOBALS['arm_report_ids'] = [];
    $GLOBALS['arm_user_ids'] = [];
});

afterEach(function () {
    $reportIds = $GLOBALS['arm_report_ids'] ?? [];
    $userIds = $GLOBALS['arm_user_ids'] ?? [];

    DB::table('alerts')
        ->where('source_type', 'report')
        ->whereIn('source_id', $reportIds)
        ->delete();
    DB::table('moderation_queues')
        ->where('content_type', 'report')
        ->whereIn('content_id', $reportIds)
        ->delete();
    DB::table('report_media')->whereIn('report_id', $reportIds)->delete();
    DB::table('report_comments')->whereIn('report_id', $reportIds)->delete();
    DB::table('report_reactions')->whereIn('report_id', $reportIds)->delete();
    DB::table('report_confirmations')->whereIn('report_id', $reportIds)->delete();
    DB::table('xp_transactions')->whereIn('reference_id', $reportIds)
        ->where('reference_type', App\Models\Report::class)
        ->delete();
    DB::table('xp_transactions')->whereIn('user_id', $userIds)->delete();
    DB::table('user_achievements')->whereIn('user_id', $userIds)->delete();
    DB::table('reports')->whereIn('id', $reportIds)->delete();
    DB::table('idempotency_keys')->whereIn('user_id', $userIds)->delete();
    DB::table('users')->whereIn('id', $userIds)->delete();

    $GLOBALS['arm_report_ids'] = [];
    $GLOBALS['arm_user_ids'] = [];
});

function armCategoryId(): int
{
    $category = ReportCategorie::query()->find(7);

    if ($category === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 is missing.'
        );
    }

    return (int) $category->id;
}

function armUser(string $role = 'user'): User
{
    $user = User::create([
        'name' => 'ARM '.ucfirst($role),
        'email' => 'arm_'.Str::random(12).'@example.com',
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

    $GLOBALS['arm_user_ids'][] = $user->id;

    return $user;
}

function armReport(array $overrides = []): int
{
    $reporter = armUser();

    $id = DB::table('reports')->insertGetId(array_merge([
        'user_id' => $reporter->id,
        'uuid' => Str::uuid()->toString(),
        'title' => 'ARM report '.Str::random(8),
        'description' => 'ARM moderator authorisation test report',
        'category_id' => armCategoryId(),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'pending',
        'priority' => 'medium',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    $GLOBALS['arm_report_ids'][] = $id;

    return $id;
}

it('lets a moderator open the reports command center and a report detail page', function () {
    $moderator = armUser('moderator');
    $reportId = armReport();

    $this->actingAs($moderator)
        ->get(route('admin.reports', ['status' => 'pending']))
        ->assertOk()
        ->assertSee('Reports command center', false)
        ->assertSee('Approve report #'.$reportId, false)
        ->assertSee('Reject report #'.$reportId, false);

    $this->actingAs($moderator)
        ->get(route('admin.reports.view', ['id' => $reportId, 'status' => 'pending']))
        ->assertOk()
        ->assertSee('Report narrative', false)
        ->assertSee('Review decision', false)
        ->assertSee('Approve report', false)
        ->assertSee('Reject report', false)
        ->assertSee('Verification signals', false);
});

it('lets a moderator approve a pending report', function () {
    Queue::fake();

    $moderator = armUser('moderator');
    $reportId = armReport();

    DB::table('moderation_queues')->insert([
        'content_type' => 'report',
        'content_id' => $reportId,
        'submitted_by' => $moderator->id,
        'ai_spam_score' => '0.00',
        'status' => 'pending',
        'priority' => 'medium',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($moderator)
        ->post(route('admin.reports.approve', $reportId))
        ->assertRedirect();

    $this->assertDatabaseHas('reports', [
        'id' => $reportId,
        'status' => 'approved',
        'verified_by' => $moderator->id,
    ]);

    $this->assertDatabaseHas('moderation_queues', [
        'content_type' => 'report',
        'content_id' => $reportId,
        'status' => 'approved',
        'reviewed_by' => $moderator->id,
    ]);
});

it('lets a moderator reject a pending report', function () {
    Queue::fake();

    $moderator = armUser('moderator');
    $reportId = armReport();

    DB::table('moderation_queues')->insert([
        'content_type' => 'report',
        'content_id' => $reportId,
        'submitted_by' => $moderator->id,
        'ai_spam_score' => '0.00',
        'status' => 'pending',
        'priority' => 'medium',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($moderator)
        ->post(route('admin.reports.reject', $reportId))
        ->assertRedirect();

    $this->assertDatabaseHas('reports', [
        'id' => $reportId,
        'status' => 'rejected',
    ]);

    $this->assertDatabaseHas('moderation_queues', [
        'content_type' => 'report',
        'content_id' => $reportId,
        'status' => 'rejected',
        'reviewed_by' => $moderator->id,
    ]);
});
