<?php

use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Admin Reports — Bulk Delete + Previous/Next Navigation tests
|--------------------------------------------------------------------------
|
| Feature 1: bulk deletion from the admin Reports list (authorization,
| validation, confirmation flow server-side guards, cleanup parity with the
| existing single-report delete, financial records untouched).
| Feature 2: Previous/Next navigation on report details that follows the
| exact filtered/sorted dataset of the Reports list (filters, status tabs,
| newest-first ordering, id DESC tiebreak, ID gaps, boundaries, deleted
| neighbours).
|
| All rows created here are cleaned up in afterEach (live-DB suite).
| Reports attach to the EXISTING production category report_categories id=7
| (see arbnCategoryId) — that record is only read, never created, never
| deleted. Navigation tests isolate their dataset with a unique search token
| so concurrent sessions' reports can never leak into the assertions.
|
*/

beforeEach(function () {
    $GLOBALS['arbn_report_ids'] = [];
    $GLOBALS['arbn_user_ids'] = [];
});

afterEach(function () {
    $reportIds = $GLOBALS['arbn_report_ids'] ?? [];
    $userIds = $GLOBALS['arbn_user_ids'] ?? [];

    DB::table('moderation_queues')
        ->where('content_type', 'report')
        ->whereIn('content_id', $reportIds)
        ->delete();
    DB::table('report_media')->whereIn('report_id', $reportIds)->delete();
    DB::table('report_comments')->whereIn('report_id', $reportIds)->delete();
    DB::table('report_reactions')->whereIn('report_id', $reportIds)->delete();
    DB::table('report_confirmations')->whereIn('report_id', $reportIds)->delete();
    DB::table('reports')->whereIn('id', $reportIds)->delete();
    DB::table('idempotency_keys')->whereIn('user_id', $userIds)->delete();
    DB::table('users')->whereIn('id', $userIds)->delete();

    $GLOBALS['arbn_report_ids'] = [];
    $GLOBALS['arbn_user_ids'] = [];
});

function arbnCategoryId(): int
{
    $category = ReportCategorie::query()->find(7);

    if ($category === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 '
            .'is missing. These tests must use that real category record and must not create fixture categories.'
        );
    }

    return (int) $category->id;
}

function arbnCreateUser(string $role = 'user'): User
{
    $user = User::create([
        'name' => 'ARBN '.ucfirst($role),
        'email' => 'arbn_'.Str::random(12).'@example.com',
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

    $GLOBALS['arbn_user_ids'][] = $user->id;

    return $user;
}

function arbnCreateReport(array $overrides = []): int
{
    $id = DB::table('reports')->insertGetId(array_merge([
        'user_id' => arbnCreateUser()->id,
        'uuid' => Str::uuid()->toString(),
        'title' => 'ARBN report '.Str::random(8),
        'description' => 'ARBN bulk delete and navigation test report description',
        'category_id' => arbnCategoryId(),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'pending',
        'priority' => 'medium',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    $GLOBALS['arbn_report_ids'][] = $id;

    return $id;
}

function arbnCreateMedia(int $reportId): int
{
    return DB::table('report_media')->insertGetId([
        'report_id' => $reportId,
        'type' => 'image',
        'media_url' => 'report-images/arbn_'.Str::random(12).'.jpg',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function arbnCreateComment(int $reportId, int $userId): int
{
    return DB::table('report_comments')->insertGetId([
        'report_id' => $reportId,
        'user_id' => $userId,
        'content' => 'ARBN test comment',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function arbnCreateReaction(int $reportId, int $userId): int
{
    return DB::table('report_reactions')->insertGetId([
        'report_id' => $reportId,
        'user_id' => $userId,
        'reaction_type' => 'helpful',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function arbnCreateModq(int $reportId, int $submittedBy): int
{
    return DB::table('moderation_queues')->insertGetId([
        'content_type' => 'report',
        'content_id' => $reportId,
        'submitted_by' => $submittedBy,
        'ai_spam_score' => '0.00',
        'status' => 'pending',
        'priority' => 'medium',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Report id linked from a report-nav-prev / report-nav-next anchor on the
 * details page, or null when the control is not rendered (boundary).
 */
function arbnNavTarget(TestResponse $response, string $which): ?int
{
    $pattern = '/href="([^"]*\/admin\/reports\/(\d+)(?:\?[^"]*)?)"\s+class="report-nav-'.$which.'[\s"]/';
    if (preg_match($pattern, $response->getContent(), $m)) {
        return (int) $m[2];
    }

    return null;
}

function arbnDetails(User $user, int $reportId, array $query = []): TestResponse
{
    $url = route('admin.reports.view', array_merge(['id' => $reportId], $query));

    return test()->actingAs($user)->get($url);
}

/*
|--------------------------------------------------------------------------
| FEATURE 1 — Bulk delete
|--------------------------------------------------------------------------
*/

it('Test 1: authorized admin can bulk delete selected reports only', function () {
    $admin = arbnCreateUser('admin');
    $a = arbnCreateReport();
    $b = arbnCreateReport();
    $keep = arbnCreateReport();

    // The list renders the bulk-selection controls.
    $this->actingAs($admin)->get(route('admin.reports'))
        ->assertOk()
        ->assertSee('id="selectAllReports"', false)
        ->assertSee('id="reportsBulkForm"', false)
        ->assertSee('report-checkbox', false)
        ->assertSee('Delete Selected', false);

    $response = $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => [$a, $b]]);

    $response->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success', '2 reports deleted');

    expect(DB::table('reports')->whereIn('id', [$a, $b])->count())->toBe(0)
        ->and(DB::table('reports')->where('id', $keep)->exists())->toBeTrue();
});

it('Test 2: empty selection never deletes anything', function () {
    $admin = arbnCreateUser('admin');
    $reportId = arbnCreateReport();

    // Unique _idempotency_key per request: without it the IdempotencyMiddleware
    // (web group) would replay the first cached 302 for the second request and
    // the second validation would never actually run.
    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => [], '_idempotency_key' => 'arbn-'.Str::random(16)])
        ->assertSessionHasErrors('ids');

    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['_idempotency_key' => 'arbn-'.Str::random(16)])
        ->assertSessionHasErrors('ids');

    expect(DB::table('reports')->where('id', $reportId)->exists())->toBeTrue();
});

it('Test 3: unauthorized users are rejected and the route stays in the web (CSRF) group', function () {
    $reportId = arbnCreateReport();

    // Guest → redirected to login.
    $this->post(route('admin.reports.bulk-delete'), ['ids' => [$reportId]])
        ->assertRedirect();

    // Authenticated regular user → 403 (role middleware, server-side).
    $user = arbnCreateUser('user');
    $this->actingAs($user)
        ->post(route('admin.reports.bulk-delete'), ['ids' => [$reportId]])
        ->assertForbidden();

    expect(DB::table('reports')->where('id', $reportId)->exists())->toBeTrue();

    // The endpoint lives in the web middleware group → CSRF protection active.
    $route = app('router')->getRoutes()->getByName('admin.reports.bulk-delete');
    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('web');
});

it('Test 4: malformed and nonexistent ids fail validation without deleting anything', function () {
    $admin = arbnCreateUser('admin');
    $valid = arbnCreateReport();

    // Nonexistent id mixed with a valid one → whole request rejected, nothing deleted.
    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => [$valid, 999999999], '_idempotency_key' => 'arbn-'.Str::random(16)])
        ->assertSessionHasErrors('ids.1');

    // Malformed id.
    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => ['abc'], '_idempotency_key' => 'arbn-'.Str::random(16)])
        ->assertSessionHasErrors('ids.0');

    // Non-array payload.
    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => 'not-an-array', '_idempotency_key' => 'arbn-'.Str::random(16)])
        ->assertSessionHasErrors('ids');

    expect(DB::table('reports')->where('id', $valid)->exists())->toBeTrue();
});

it('Test 5: bulk delete performs the same related cleanup as individual delete', function () {
    $admin = arbnCreateUser('admin');

    // Report deleted individually through the existing single-delete endpoint.
    $singleUser = arbnCreateUser();
    $single = arbnCreateReport(['user_id' => $singleUser->id]);
    arbnCreateMedia($single);
    arbnCreateComment($single, $singleUser->id);
    arbnCreateReaction($single, $singleUser->id);
    arbnCreateModq($single, $singleUser->id);

    // Report deleted through the new bulk endpoint.
    $bulkUser = arbnCreateUser();
    $bulk = arbnCreateReport(['user_id' => $bulkUser->id]);
    arbnCreateMedia($bulk);
    arbnCreateComment($bulk, $bulkUser->id);
    arbnCreateReaction($bulk, $bulkUser->id);
    arbnCreateModq($bulk, $bulkUser->id);

    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.delete', $single))
        ->assertRedirect(route('admin.reports'))
        ->assertSessionHas('success', 'Report deleted');

    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => [$bulk]]);

    foreach ([$single, $bulk] as $id) {
        expect(DB::table('reports')->where('id', $id)->exists())->toBeFalse()
            ->and(DB::table('report_media')->where('report_id', $id)->count())->toBe(0)
            ->and(DB::table('report_comments')->where('report_id', $id)->count())->toBe(0)
            ->and(DB::table('report_reactions')->where('report_id', $id)->count())->toBe(0)
            ->and(DB::table('moderation_queues')->where('content_type', 'report')->where('content_id', $id)->count())->toBe(0);
    }

    // Audit trail exists for both deletion paths.
    expect(DB::table('audit_logs')->where('action', 'report.deleted')->whereIn('resource_id', [$single, $bulk])->count())->toBe(2);
});

it('Test 6: bulk delete never touches unrelated financial records', function () {
    $admin = arbnCreateUser('admin');
    $owner = arbnCreateUser();
    $reportId = arbnCreateReport(['user_id' => $owner->id]);

    $coinId = DB::table('coin_transactions')->insertGetId([
        'user_id' => $owner->id,
        'type' => 'admin_adjustment',
        'amount' => 50.00,
        'report_id' => $reportId,
        'description' => 'ARBN financial isolation fixture',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $xpId = DB::table('xp_transactions')->insertGetId([
        'user_id' => $owner->id,
        'amount' => 10,
        'action_type' => 'report_approved',
        'reference_type' => Report::class,
        'reference_id' => $reportId,
        'description' => 'ARBN xp fixture',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => [$reportId]]);

    expect(DB::table('reports')->where('id', $reportId)->exists())->toBeFalse();

    // Coin transaction survives untouched: this MyISAM schema has no FK on
    // coin_transactions.report_id, so the reference is never nulled and the row
    // is never deleted or updated by report deletion.
    $coin = DB::table('coin_transactions')->find($coinId);
    expect($coin)->not->toBeNull()
        ->and((int) $coin->report_id)->toBe($reportId)
        ->and((float) $coin->amount)->toBe(50.00)
        ->and($coin->description)->toBe('ARBN financial isolation fixture');

    // XP ledger row survives untouched (no cascade, reference is historical).
    $xp = DB::table('xp_transactions')->find($xpId);
    expect($xp)->not->toBeNull()
        ->and((int) $xp->reference_id)->toBe($reportId)
        ->and($xp->action_type)->toBe('report_approved');

    // The owning user/account is untouched.
    expect(User::find($owner->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| FEATURE 2 — Previous/Next navigation
|--------------------------------------------------------------------------
*/

it('Test 7: prev/next follows list ordering and carries list context into links', function () {
    $admin = arbnCreateUser('admin');
    $tok = 'ARBN'.Str::random(8);

    // newest → oldest insertion order intentionally differs from nothing yet;
    // ids are consecutive here, ordering is asserted via created_at.
    $newest = arbnCreateReport(['title' => "{$tok} newest", 'created_at' => now()->subHours(1)]);
    $middle = arbnCreateReport(['title' => "{$tok} middle", 'created_at' => now()->subHours(2)]);
    $oldest = arbnCreateReport(['title' => "{$tok} oldest", 'created_at' => now()->subHours(3)]);

    // List page links into details preserving filter context.
    $this->actingAs($admin)->get(route('admin.reports', ['status' => 'pending', 'search' => $tok]))
        ->assertOk()
        ->assertSee('/admin/reports/'.$middle.'?status=pending', false);

    $ctx = ['status' => 'pending', 'search' => $tok];

    $middlePage = arbnDetails($admin, $middle, $ctx);
    $middlePage->assertOk();
    expect(arbnNavTarget($middlePage, 'prev'))->toBe($newest)
        ->and(arbnNavTarget($middlePage, 'next'))->toBe($oldest);

    $newestPage = arbnDetails($admin, $newest, $ctx);
    expect(arbnNavTarget($newestPage, 'prev'))->toBeNull()
        ->and(arbnNavTarget($newestPage, 'next'))->toBe($middle);

    $oldestPage = arbnDetails($admin, $oldest, $ctx);
    expect(arbnNavTarget($oldestPage, 'prev'))->toBe($middle)
        ->and(arbnNavTarget($oldestPage, 'next'))->toBeNull();

    // List context (including page) flows into nav and Back links; unknown
    // parameters are never forwarded.
    $withExtras = arbnDetails($admin, $middle, $ctx + ['page' => 9, 'evil' => '1']);
    $withExtras->assertOk()
        ->assertSee('page=9', false)
        ->assertDontSee('evil=1');
});

it('Test 8: status filter is respected by prev/next', function () {
    $admin = arbnCreateUser('admin');
    $tok = 'ARBN'.Str::random(8);

    $p1 = arbnCreateReport(['title' => "{$tok} pending one", 'created_at' => now()->subHours(3)]);
    $mid = arbnCreateReport(['title' => "{$tok} approved middle", 'created_at' => now()->subHours(2), 'status' => 'approved']);
    $p2 = arbnCreateReport(['title' => "{$tok} pending two", 'created_at' => now()->subHours(1)]);

    // p1 is the oldest, so its NEWER-side neighbour (prev) is what changes with
    // the filter; its next side is empty by construction.
    //
    // status=pending (the list default): the approved report is invisible.
    $pendingCtx = arbnDetails($admin, $p1, ['status' => 'pending', 'search' => $tok]);
    expect(arbnNavTarget($pendingCtx, 'prev'))->toBe($p2);

    // Default context (no status param) behaves exactly like the list default.
    $defaultCtx = arbnDetails($admin, $p1, ['search' => $tok]);
    expect(arbnNavTarget($defaultCtx, 'prev'))->toBe($p2);

    // status=all: the same dataset the "All" tab shows, approved included.
    $allCtx = arbnDetails($admin, $p1, ['status' => 'all', 'search' => $tok]);
    expect(arbnNavTarget($allCtx, 'prev'))->toBe($mid);

    // A report outside the active filter yields no incorrect neighbours.
    $approvedOnly = arbnDetails($admin, $mid, ['status' => 'pending', 'search' => $tok]);
    $approvedOnly->assertOk();
    expect(arbnNavTarget($approvedOnly, 'prev'))->toBeNull()
        ->and(arbnNavTarget($approvedOnly, 'next'))->toBeNull();
});

it('Test 9: navigation follows the list sort (newest first) and its id DESC tiebreak', function () {
    $admin = arbnCreateUser('admin');
    $tok = 'ARBN'.Str::random(8);
    $ctx = ['status' => 'pending', 'search' => $tok];

    // Insert older report FIRST (lower id), newer SECOND (higher id):
    // ordering must come from created_at (newest first), never from id order.
    $older = arbnCreateReport(['title' => "{$tok} older row", 'created_at' => now()->subHours(1)]);
    $newer = arbnCreateReport(['title' => "{$tok} newer row", 'created_at' => now()]);

    $newerPage = arbnDetails($admin, $newer, $ctx);
    // Under the old id-ascending logic this would be prev=older; the list
    // shows newest first, so the newer row is first → no Previous.
    expect(arbnNavTarget($newerPage, 'prev'))->toBeNull()
        ->and(arbnNavTarget($newerPage, 'next'))->toBe($older);

    // Exact created_at ties break deterministically by id DESC (list order).
    $tieTime = now()->subHours(2);
    $tieFirst = arbnCreateReport(['title' => "{$tok} tie lower id", 'created_at' => $tieTime]);
    $tieSecond = arbnCreateReport(['title' => "{$tok} tie higher id", 'created_at' => $tieTime]);

    $tieLowerPage = arbnDetails($admin, $tieFirst, $ctx);
    expect(arbnNavTarget($tieLowerPage, 'prev'))->toBe($tieSecond)
        ->and(arbnNavTarget($tieLowerPage, 'next'))->toBeNull();

    $tieHigherPage = arbnDetails($admin, $tieSecond, $ctx);
    expect(arbnNavTarget($tieHigherPage, 'next'))->toBe($tieFirst);
});

it('Test 10: first and last reports in the set have no Previous / no Next', function () {
    $admin = arbnCreateUser('admin');
    $tok = 'ARBN'.Str::random(8);
    $ctx = ['status' => 'pending', 'search' => $tok];

    $first = arbnCreateReport(['title' => "{$tok} boundary first", 'created_at' => now()]);
    arbnCreateReport(['title' => "{$tok} boundary middle", 'created_at' => now()->subHours(1)]);
    $last = arbnCreateReport(['title' => "{$tok} boundary last", 'created_at' => now()->subHours(2)]);

    $firstPage = arbnDetails($admin, $first, $ctx);
    $firstPage->assertOk()->assertDontSee('report-nav-prev');
    expect(arbnNavTarget($firstPage, 'prev'))->toBeNull();

    $lastPage = arbnDetails($admin, $last, $ctx);
    $lastPage->assertOk()->assertDontSee('report-nav-next');
    expect(arbnNavTarget($lastPage, 'next'))->toBeNull();
});

it('Test 11: navigation works across gaps in report ids', function () {
    $admin = arbnCreateUser('admin');
    $tok = 'ARBN'.Str::random(8);
    $ctx = ['status' => 'pending', 'search' => $tok];

    // Decoys (never matched by the search token) sit between the targets, so
    // the three target reports get non-consecutive ids — without ever
    // injecting explicit ids into the auto-increment column.
    $decoy1 = arbnCreateReport(['title' => 'ARBNdecoy '.Str::random(8)]);
    $g1 = arbnCreateReport(['title' => "{$tok} gap one", 'created_at' => now()->subHours(3)]);
    $decoy2 = arbnCreateReport(['title' => 'ARBNdecoy '.Str::random(8)]);
    $g2 = arbnCreateReport(['title' => "{$tok} gap two", 'created_at' => now()->subHours(2)]);
    $decoy3 = arbnCreateReport(['title' => 'ARBNdecoy '.Str::random(8)]);
    $g3 = arbnCreateReport(['title' => "{$tok} gap three", 'created_at' => now()->subHours(1)]);

    // Ids are genuinely non-consecutive (decoys occupy the in-between ids).
    expect($g2 - $g1)->toBeGreaterThan(1)
        ->and($g3 - $g2)->toBeGreaterThan(1);

    // Newest first: g3 → g2 → g1 regardless of the id gaps.
    $g3Page = arbnDetails($admin, $g3, $ctx);
    expect(arbnNavTarget($g3Page, 'prev'))->toBeNull()
        ->and(arbnNavTarget($g3Page, 'next'))->toBe($g2);

    $g2Page = arbnDetails($admin, $g2, $ctx);
    expect(arbnNavTarget($g2Page, 'prev'))->toBe($g3)
        ->and(arbnNavTarget($g2Page, 'next'))->toBe($g1);

    $g1Page = arbnDetails($admin, $g1, $ctx);
    expect(arbnNavTarget($g1Page, 'prev'))->toBe($g2)
        ->and(arbnNavTarget($g1Page, 'next'))->toBeNull();

    // Unmatched decoys never appear in the traversal.
    foreach ([$decoy1, $decoy2, $decoy3] as $decoy) {
        $g2Page->assertDontSee('/admin/reports/'.$decoy.'?', false);
    }
});

it('Test 12: deleted neighbours and deleted current reports never produce invalid links', function () {
    $admin = arbnCreateUser('admin');
    $tok = 'ARBN'.Str::random(8);
    $ctx = ['status' => 'pending', 'search' => $tok];

    // Newest first: c, a, b — so from a: Previous = c, Next = b.
    $a = arbnCreateReport(['title' => "{$tok} survivor", 'created_at' => now()->subHours(1)]);
    $b = arbnCreateReport(['title' => "{$tok} doomed", 'created_at' => now()->subHours(2)]);
    $c = arbnCreateReport(['title' => "{$tok} tail", 'created_at' => now()]);

    // Current state: from a, Next = b, Previous = c.
    $before = arbnDetails($admin, $a, $ctx);
    expect(arbnNavTarget($before, 'next'))->toBe($b)
        ->and(arbnNavTarget($before, 'prev'))->toBe($c);

    // Delete b while a stays open; the next request recomputes neighbours.
    $this->actingAs($admin)
        ->from(route('admin.reports'))
        ->post(route('admin.reports.bulk-delete'), ['ids' => [$b]]);
    expect(DB::table('reports')->where('id', $b)->exists())->toBeFalse();

    $after = arbnDetails($admin, $a, $ctx);
    $after->assertOk();
    expect(arbnNavTarget($after, 'next'))->toBeNull()   // b gone, nothing older remains
        ->and(arbnNavTarget($after, 'prev'))->toBe($c);

    // Opening a report that no longer exists is a clean 404, not a broken page.
    $this->actingAs($admin)
        ->get(route('admin.reports.view', ['id' => $b] + $ctx))
        ->assertNotFound();
});
