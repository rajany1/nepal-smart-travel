<?php

/**
 * Leaderboard invariants:
 *
 *   A. Only regular users compete - admin, super_admin, moderator, business
 *      and is_system accounts are never listed (nor ranked).
 *   B. The board lists the top N rows (mobile asks for 10); everyone else
 *      only gets their position through meta.user_rank.
 *   C. Categories: xp, reports, alerts, earnings (lifetime coins earned) and
 *      contributions (approved user-submitted places + applied corrections +
 *      visible reviews).
 *   D. Invisible content (pending places, rejected corrections/reviews) must
 *      not inflate the contribution score.
 *
 * Runs against the local `backend` MySQL DB with explicit row cleanup in
 * afterEach - same convention as BipadHazardFilterTest (no RefreshDatabase).
 */

use App\Models\PlaceCorrection;
use App\Models\PlaceReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$GLOBALS['lb_cleanup'] = [
    'user_ids' => [],
    'place_ids' => [],
    'alert_ids' => [],
    'place_category_id' => null,
    'report_category_id' => null,
];

function lb_user(string $label, int $xp = 0, string $role = 'user', bool $system = false): User
{
    $user = User::create([
        'name' => $label . ' ' . Str::lower(Str::random(4)),
        'email' => 'lb_' . Str::lower(Str::random(12)) . '@localhost.test',
        'phone' => '98' . mt_rand(10000000, 99999999),
        'password' => Str::random(20),
    ]);

    $user->forceFill([
        'role_id' => DB::table('roles')->where('name', $role)->value('id'),
        'total_xp' => $xp,
        'is_system' => $system,
    ])->save();

    $GLOBALS['lb_cleanup']['user_ids'][] = $user->id;

    return $user->fresh();
}

function lb_wallet(User $user, float $earned): void
{
    DB::table('oripori_coin_wallets')->insert([
        'user_id' => $user->id,
        'balance' => $earned,
        'total_earned' => $earned,
        'total_withdrawn' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function lb_place_category_id(): int
{
    if (!$GLOBALS['lb_cleanup']['place_category_id']) {
        $GLOBALS['lb_cleanup']['place_category_id'] = DB::table('place_categories')->insertGetId([
            'name' => 'LB Test Category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $GLOBALS['lb_cleanup']['place_category_id'];
}

function lb_report_category_id(): int
{
    if (!$GLOBALS['lb_cleanup']['report_category_id']) {
        $GLOBALS['lb_cleanup']['report_category_id'] = DB::table('report_categories')->insertGetId([
            'name' => 'LB Test Reports',
            'slug' => 'lb-test-' . Str::lower(Str::random(6)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $GLOBALS['lb_cleanup']['report_category_id'];
}

function lb_place(?User $user, bool $verified = true, string $source = 'user_submitted'): int
{
    $id = DB::table('places')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'category_id' => lb_place_category_id(),
        'created_by' => $user?->id,
        'name' => 'LB Place ' . Str::lower(Str::random(6)),
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'source' => $source,
        'is_verified' => $verified ? 1 : 0,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $GLOBALS['lb_cleanup']['place_ids'][] = $id;

    return $id;
}

function lb_correction(User $user, string $status = 'applied'): void
{
    PlaceCorrection::create([
        'user_id' => $user->id,
        'correction_type' => 'wrong_location',
        'description' => 'LB correction',
        'status' => $status,
    ]);
}

function lb_review(User $user, string $moderationStatus = 'approved'): void
{
    PlaceReview::create([
        'place_id' => lb_place(null, false, 'admin'),
        'user_id' => $user->id,
        'title' => 'LB review',
        'description' => 'LB review body',
        'rating' => 5,
        'moderation_status' => $moderationStatus,
        'moderated_at' => now(),
    ]);
}

function lb_report(User $user): void
{
    DB::table('reports')->insert([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'category_id' => lb_report_category_id(),
        'title' => 'LB report',
        'description' => 'LB report body',
        'priority' => 'medium',
        'status' => 'pending',
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function lb_alert(User $user): void
{
    $id = DB::table('alerts')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'title' => 'LB alert',
        'description' => 'LB alert body',
        'alert_type' => 'weather',
        'severity' => 'info',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $GLOBALS['lb_cleanup']['alert_ids'][] = $id;
}

afterEach(function () {
    // Alerts/places reference users with nullOnDelete, so they survive the
    // user delete - remove them explicitly first.
    if ($GLOBALS['lb_cleanup']['alert_ids']) {
        DB::table('alerts')->whereIn('id', $GLOBALS['lb_cleanup']['alert_ids'])->delete();
        $GLOBALS['lb_cleanup']['alert_ids'] = [];
    }

    if ($GLOBALS['lb_cleanup']['place_ids']) {
        DB::table('places')->whereIn('id', $GLOBALS['lb_cleanup']['place_ids'])->delete();
        $GLOBALS['lb_cleanup']['place_ids'] = [];
    }

    if ($GLOBALS['lb_cleanup']['user_ids']) {
        $ids = $GLOBALS['lb_cleanup']['user_ids'];
        // reports lost their FK (made nullable via raw ALTER), so nothing
        // cascades - delete the child rows explicitly before the users.
        DB::table('reports')->whereIn('user_id', $ids)->delete();
        DB::table('place_reviews')->whereIn('user_id', $ids)->delete();
        DB::table('place_corrections')->whereIn('user_id', $ids)->delete();
        DB::table('oripori_coin_wallets')->whereIn('user_id', $ids)->delete();
        DB::table('user_subscriptions')->whereIn('user_id', $ids)->delete();
        DB::table('users')->whereIn('id', $ids)->delete();
        $GLOBALS['lb_cleanup']['user_ids'] = [];
    }

    if ($GLOBALS['lb_cleanup']['place_category_id']) {
        DB::table('place_categories')->where('id', $GLOBALS['lb_cleanup']['place_category_id'])->delete();
        $GLOBALS['lb_cleanup']['place_category_id'] = null;
    }

    if ($GLOBALS['lb_cleanup']['report_category_id']) {
        DB::table('report_categories')->where('id', $GLOBALS['lb_cleanup']['report_category_id'])->delete();
        $GLOBALS['lb_cleanup']['report_category_id'] = null;
    }
});

test('staff and system accounts never appear on the leaderboard', function () {
    $anchor = lb_user('LB Anchor', 500000);
    $admin = lb_user('LB Admin', 900000, 'admin');
    $super = lb_user('LB Super', 850000, 'super_admin');
    $mod = lb_user('LB Mod', 800000, 'moderator');
    $business = lb_user('LB Business', 750000, 'business');
    $system = lb_user('LB System', 950000, 'user', true);

    $response = $this->getJson('/api/v1/leaderboard?category=xp&limit=5');
    $response->assertOk();

    $data = collect($response->json('data'));
    $ids = $data->pluck('user_id');

    expect($data->first()['user_id'])->toBe((string) $anchor->id);
    foreach ([$admin, $super, $mod, $business, $system] as $excluded) {
        expect($ids)->not->toContain((string) $excluded->id);
    }

    // Podium endpoint must apply the same exclusion.
    $top = collect($this->getJson('/api/v1/leaderboard/top')->json('data'));
    expect($top->first()['user_id'])->toBe((string) $anchor->id);
    expect($top->pluck('user_id'))->not->toContain((string) $admin->id);
});

test('only the top 10 are listed while everyone else gets their position', function () {
    $users = [];
    foreach (range(1, 12) as $i) {
        $users[$i] = lb_user("LB Rank {$i}", 100 + $i);
    }

    $response = $this->getJson('/api/v1/leaderboard?category=xp&limit=10');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data)->toHaveCount(10);
    expect($data->first()['rank'])->toBe(1);
    expect($data->last()['rank'])->toBe(10);
    expect($data->first()['user_id'])->toBe((string) $users[12]->id);
    expect($data->pluck('user_id'))->not->toContain((string) $users[1]->id);
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(12);

    // The 12th user only sees their position via meta.user_rank.
    $rankResponse = $this->actingAs($users[1])
        ->getJson('/api/v1/leaderboard?category=xp&limit=10');
    expect($rankResponse->json('meta.user_rank'))->toBe(12);

    // Staff have no rank at all.
    $admin = lb_user('LB Rank Admin', 999999, 'admin');
    $adminRank = $this->actingAs($admin)
        ->getJson('/api/v1/leaderboard?category=xp&limit=10');
    expect($adminRank->json('meta.user_rank'))->toBeNull();
});

test('earnings category ranks by lifetime coins earned', function () {
    $low = lb_user('LB Earn Low', 10);
    $high = lb_user('LB Earn High', 10);

    lb_wallet($low, 100.50);
    lb_wallet($high, 250.75);

    $response = $this->getJson('/api/v1/leaderboard?category=earnings&limit=10');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data->first()['user_id'])->toBe((string) $high->id);
    expect($data->first()['total_earned'])->toBe(250.75);
    expect($data[1]['user_id'])->toBe((string) $low->id);
    expect($data[1]['total_earned'])->toBe(100.50);
});

test('map contribution combines places, corrections and reviews and ignores invisible content', function () {
    $onePlace = lb_user('LB Contrib One');
    $mixed = lb_user('LB Contrib Mixed');

    // $onePlace: 2 approved user-submitted places. Pending place and
    // rejected review must not count.
    lb_place($onePlace);
    lb_place($onePlace);
    lb_place($onePlace, false);
    lb_review($onePlace, 'rejected');

    // $mixed: 1 applied correction + 2 visible reviews.
    lb_correction($mixed);
    lb_correction($mixed, 'pending');
    lb_review($mixed);
    lb_review($mixed);

    $response = $this->getJson('/api/v1/leaderboard?category=contributions&limit=10');
    $response->assertOk();

    $data = collect($response->json('data'))->keyBy('user_id');

    expect($data[(string) $mixed->id]['contribution_score'])->toBe(3);
    expect($data[(string) $mixed->id]['corrections_applied'])->toBe(1);
    expect($data[(string) $mixed->id]['places_contributed'])->toBe(0);
    expect($data[(string) $mixed->id]['total_reviews'])->toBe(2);

    expect($data[(string) $onePlace->id]['contribution_score'])->toBe(2);
    expect($data[(string) $onePlace->id]['places_contributed'])->toBe(2);
    expect($data[(string) $onePlace->id]['total_reviews'])->toBe(0);

    // Composite ordering: $mixed (3) above $onePlace (2).
    $order = collect($response->json('data'))->pluck('user_id');
    expect($order->search((string) $mixed->id))
        ->toBeLessThan($order->search((string) $onePlace->id));
});

test('reports and alerts categories rank map submissions', function () {
    $leader = lb_user('LB Reports Leader');
    $runnerUp = lb_user('LB Reports Runner');

    foreach (range(1, 50) as $_) {
        lb_report($leader);
    }
    foreach (range(1, 40) as $_) {
        lb_report($runnerUp);
    }

    $response = $this->getJson('/api/v1/leaderboard?category=reports&limit=10');
    $response->assertOk();

    $data = collect($response->json('data'));
    expect($data->first()['user_id'])->toBe((string) $leader->id);
    expect($data->first()['total_reports'])->toBe(50);
    expect($data[1]['user_id'])->toBe((string) $runnerUp->id);
    expect($data[1]['total_reports'])->toBe(40);

    // Alerts: same two users, reversed result.
    foreach (range(1, 2) as $_) {
        lb_alert($runnerUp);
    }
    foreach (range(1, 3) as $_) {
        lb_alert($leader);
    }

    $alerts = collect(
        $this->getJson('/api/v1/leaderboard?category=alerts&limit=10')->json('data')
    );
    expect($alerts->first()['user_id'])->toBe((string) $leader->id);
    expect($alerts->first()['total_alerts'])->toBe(3);
    expect($alerts[1]['user_id'])->toBe((string) $runnerUp->id);
    expect($alerts[1]['total_alerts'])->toBe(2);
});
