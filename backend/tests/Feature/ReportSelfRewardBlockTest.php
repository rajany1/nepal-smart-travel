<?php

use App\Models\AdCampaign;
use App\Models\AdRewardEvent;
use App\Models\AdRevenueLedger;
use App\Models\CoinTransaction;
use App\Models\OriporiCoinWallet;
use App\Models\Report;
use App\Models\User;
use App\Services\CoinService;
use App\Services\FinancialLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Regression tests: report owner must never earn coins from ads on own report
|--------------------------------------------------------------------------
|
| Security rule under test (server-side, authoritative in Laravel):
|
|   authenticated actor -> reward event -> associated report -> report owner
|   -> same user? -> YES -> reward BLOCKED (no coin txn, no wallet increment)
|
| The ad impression/click itself is still tracked and billed; only the coin
| reward is withheld. Legitimate cross-user rewards must keep working.
|
| Helper names are prefixed with `srr_` to avoid clashes with other suites.
*/

function srr_seed_settings(): void
{
    $settings = [
        'user_share_percent' => 47,
        'coin_to_npr_rate' => 1,
        'impression_cooldown_minutes' => 10,
        'impression_value' => 0.0235,
        'click_value' => 0.235,
        'daily_earning_cap' => 500,
        'daily_impression_cap' => 1000,
    ];
    foreach ($settings as $key => $value) {
        DB::table('coin_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
        );
    }
    DB::table('game_settings')->updateOrInsert(['key' => 'ad_cpm'], ['value' => 50, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('game_settings')->updateOrInsert(['key' => 'ad_cpc'], ['value' => 0.50, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('game_settings')->updateOrInsert(['key' => 'ad_freq_cap'], ['value' => 3, 'created_at' => now(), 'updated_at' => now()]);
}

function srr_make_user(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'SelfReward User',
        'email' => 'srr_' . Str::random(10) . '@example.com',
        'email_verified_at' => now(),
        'phone' => '98' . mt_rand(10000000, 99999999),
        'password' => bcrypt('password'),
        'uuid' => Str::uuid()->toString(),
        'role_id' => DB::table('roles')->where('name', 'user')->value('id') ?? 1,
        'status' => 'active',
        'is_active' => 1,
        'badges' => json_encode([]),
        'expertise_regions' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function srr_make_partner(int $userId): int
{
    return DB::table('travel_partners')->insertGetId([
        'name' => 'SelfReward Partner ' . Str::random(5),
        'type' => 'hotel',
        'user_id' => $userId,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function srr_make_campaign(int $partnerId): int
{
    return DB::table('ad_campaigns')->insertGetId([
        'name' => 'SelfReward Campaign ' . Str::random(5),
        'business_id' => $partnerId,
        'ad_type' => 'banner',
        'content' => 'Test ad content for self-reward regression',
        'budget' => 1000,
        'cost_per_view' => 50,
        'cost_per_click' => 0.50,
        'max_impressions' => 0,
        'status' => 'active',
        'payment_status' => 'paid',
        'current_impressions' => 0,
        'current_clicks' => 0,
        'spent_amount' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function srr_make_report(int $userId, string $status = 'approved'): int
{
    $catId = DB::table('report_categories')->where('id', 7)->value('id')
        ?? DB::table('report_categories')->value('id');

    return DB::table('reports')->insertGetId([
        'user_id' => $userId,
        'uuid' => Str::uuid()->toString(),
        'title' => 'SelfReward Report ' . Str::random(5),
        'description' => 'Regression fixture for the report self-reward ownership rule',
        'category_id' => $catId,
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function srr_setup(string $status = 'approved'): array
{
    srr_seed_settings();

    $ownerId = srr_make_user();
    $partnerUserId = srr_make_user();
    $partnerId = srr_make_partner($partnerUserId);
    $campaignId = srr_make_campaign($partnerId);
    $reportId = srr_make_report($ownerId, $status);

    return [
        'ownerId' => $ownerId,
        'partnerUserId' => $partnerUserId,
        'partnerId' => $partnerId,
        'campaignId' => $campaignId,
        'reportId' => $reportId,
        'userIds' => [$ownerId, $partnerUserId],
    ];
}

function srr_cleanup(array $f): void
{
    DB::table('ad_impressions')->where('ad_campaign_id', $f['campaignId'])->delete();
    DB::table('ad_clicks')->where('ad_campaign_id', $f['campaignId'])->delete();
    DB::table('ad_reward_events')->where('ad_campaign_id', $f['campaignId'])->delete();
    DB::table('ad_revenue_ledger')->where('ad_campaign_id', $f['campaignId'])->delete();
    DB::table('coin_transactions')->where(function ($q) use ($f) {
        $q->where('ad_campaign_id', $f['campaignId'])->orWhere('report_id', $f['reportId']);
    })->delete();
    DB::table('ad_campaigns')->where('id', $f['campaignId'])->delete();
    DB::table('reports')->where('id', $f['reportId'])->delete();
    DB::table('travel_partners')->where('id', $f['partnerId'])->delete();

    foreach ($f['userIds'] as $id) {
        DB::table('user_fraud_profiles')->where('user_id', $id)->delete();
        DB::table('oripori_coin_wallets')->where('user_id', $id)->delete();
        DB::table('users')->where('id', $id)->delete();
    }
}

function srr_assert_no_coins(array $f): void
{
    expect(CoinTransaction::where('report_id', $f['reportId'])->count())->toBe(0);
    expect(CoinTransaction::where('ad_campaign_id', $f['campaignId'])->count())->toBe(0);
    expect(
        CoinTransaction::where('user_id', $f['ownerId'])
            ->whereIn('type', ['impression_earning', 'click_earning'])
            ->count()
    )->toBe(0);

    $wallet = OriporiCoinWallet::where('user_id', $f['ownerId'])->first();
    expect($wallet === null || (float) $wallet->balance === 0.0)->toBeTrue();
}

/*
|--------------------------------------------------------------------------
| 1-3. Own report, every existing status
|--------------------------------------------------------------------------
*/

test('owner cannot earn coins from an ad impression on their own pending report', function () {
    $f = srr_setup('pending');
    $owner = User::find($f['ownerId']);

    $response = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => $f['reportId'],
        'context' => 'report',
    ]);

    // Clean, non-error eligibility result: the report screen must not crash
    $response->assertOk()->assertJson([
        'success' => true,
        'coins_earned' => 0,
        'eligibility' => 'not_eligible',
    ]);

    // No coins, no wallet transaction
    srr_assert_no_coins($f);

    // Ad display/tracking still works — impression != reward
    $campaign = AdCampaign::find($f['campaignId']);
    expect($campaign->current_impressions)->toBe(1);

    $reward = AdRewardEvent::where('ad_campaign_id', $f['campaignId'])->first();
    expect($reward)->not->toBeNull();
    expect($reward->event_status)->toBe('validated');
    expect((float) $reward->coins_credited)->toBe(0.0);

    $ledger = AdRevenueLedger::where('ad_campaign_id', $f['campaignId'])->first();
    expect($ledger)->not->toBeNull();
    expect((float) $ledger->user_share)->toBe(0.0);

    srr_cleanup($f);
});

test('owner cannot earn coins from an ad impression on their own approved report', function () {
    $f = srr_setup('approved');
    $owner = User::find($f['ownerId']);

    $response = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => $f['reportId'],
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'coins_earned' => 0,
        'eligibility' => 'not_eligible',
    ]);

    srr_assert_no_coins($f);
    expect((float) AdRewardEvent::where('ad_campaign_id', $f['campaignId'])->value('coins_credited'))->toBe(0.0);

    srr_cleanup($f);
});

test('owner cannot earn coins from an ad impression on their own rejected report', function () {
    $f = srr_setup('rejected');
    $owner = User::find($f['ownerId']);

    $response = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => $f['reportId'],
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'coins_earned' => 0,
        'eligibility' => 'not_eligible',
    ]);

    srr_assert_no_coins($f);
    srr_cleanup($f);
});

test('owner cannot earn coins from an ad click on their own report', function () {
    $f = srr_setup('pending');
    $owner = User::find($f['ownerId']);

    $response = $this->actingAs($owner)->postJson('/api/v1/ads/track-click', [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => $f['reportId'],
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'coins_earned' => 0,
        'eligibility' => 'not_eligible',
    ]);

    srr_assert_no_coins($f);
    expect(
        CoinTransaction::where('user_id', $f['ownerId'])->where('type', 'click_earning')->count()
    )->toBe(0);

    srr_cleanup($f);
});

/*
|--------------------------------------------------------------------------
| 4. Legitimate cross-user rewards keep working
|--------------------------------------------------------------------------
*/

test('another user engaging with the report still earns coins for the owner under existing rules', function () {
    $f = srr_setup('pending');
    $viewerId = srr_make_user();
    $f['userIds'][] = $viewerId;
    $viewer = User::find($viewerId);

    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => $f['reportId'],
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    expect((float) $response->json('coins_earned'))->toBeGreaterThan(0);

    $wallet = OriporiCoinWallet::where('user_id', $f['ownerId'])->first();
    expect($wallet)->not->toBeNull();
    expect((float) $wallet->balance)->toBeGreaterThan(0);

    srr_cleanup($f);
});

/*
|--------------------------------------------------------------------------
| 5. Client bypass attempts
|--------------------------------------------------------------------------
*/

test('client-supplied identity or ownership claims cannot bypass the ownership rule', function () {
    $f = srr_setup('pending');
    $owner = User::find($f['ownerId']);
    $forgedUserId = srr_make_user();
    $f['userIds'][] = $forgedUserId;

    // Forged claims in the payload: server resolves owner/auth itself
    $response = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => $f['reportId'],
        'context' => 'report',
        'user_id' => $forgedUserId,
        'report_owner_id' => $forgedUserId,
        'owner_id' => $forgedUserId,
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'coins_earned' => 0,
        'eligibility' => 'not_eligible',
    ]);

    // Neither the real owner nor the forged identity receives anything
    srr_assert_no_coins($f);
    expect(CoinTransaction::where('user_id', $forgedUserId)->count())->toBe(0);
    expect(OriporiCoinWallet::where('user_id', $forgedUserId)->exists())->toBeFalse();

    // Tampered (nonexistent) report id is rejected by server-side validation
    $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => 999999999,
        'context' => 'report',
    ])->assertStatus(422);

    srr_cleanup($f);
});

test('reward requests without a report id never produce coins', function () {
    $f = srr_setup('pending');
    $owner = User::find($f['ownerId']);

    $response = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $f['campaignId'],
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson(['success' => true, 'coins_earned' => 0]);
    srr_assert_no_coins($f);

    srr_cleanup($f);
});

/*
|--------------------------------------------------------------------------
| 6. Direct API call (no Flutter UI involved)
|--------------------------------------------------------------------------
*/

test('direct API calls bypassing the Flutter client are still subject to the ownership rule', function () {
    $f = srr_setup('approved');
    $owner = User::find($f['ownerId']);

    // Raw request: no app UI, no `context` field (server defaults it to report)
    $response = $this->withHeaders(['Accept' => 'application/json'])
        ->actingAs($owner)
        ->postJson('/api/v1/ads/track-impression', [
            'ad_campaign_id' => $f['campaignId'],
            'report_id' => $f['reportId'],
        ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'coins_earned' => 0,
        'eligibility' => 'not_eligible',
    ]);

    srr_assert_no_coins($f);
    srr_cleanup($f);
});

/*
|--------------------------------------------------------------------------
| 7. Repeated attempts + existing idempotency protections intact
|--------------------------------------------------------------------------
*/

test('repeated self-reward attempts never credit coins and idempotency stays intact', function () {
    $f = srr_setup('pending');
    $owner = User::find($f['ownerId']);

    // Bypass request-level middleware to exercise endpoint idempotency directly
    $this->withoutMiddleware(\App\Http\Middleware\IdempotencyMiddleware::class);

    $payload = [
        'ad_campaign_id' => $f['campaignId'],
        'report_id' => $f['reportId'],
        'context' => 'report',
    ];

    $first = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', $payload);
    $first->assertOk()->assertJson([
        'success' => true,
        'coins_earned' => 0,
        'eligibility' => 'not_eligible',
    ]);

    $second = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', $payload);
    $second->assertStatus(422)->assertJson(['error' => 'Impression already recorded']);

    $third = $this->actingAs($owner)->postJson('/api/v1/ads/track-impression', $payload);
    $third->assertStatus(422)->assertJson(['error' => 'Impression already recorded']);

    // No coin credit, no wallet balance increase, single reward event
    srr_assert_no_coins($f);
    expect(AdRewardEvent::where('ad_campaign_id', $f['campaignId'])->count())->toBe(1);
    expect((float) AdRewardEvent::where('ad_campaign_id', $f['campaignId'])->value('coins_credited'))->toBe(0.0);

    srr_cleanup($f);
});

/*
|--------------------------------------------------------------------------
| 8. Service layer is authoritative (cannot be bypassed by other flows)
|--------------------------------------------------------------------------
*/

test('reward service blocks self-reward even when invoked directly', function () {
    $f = srr_setup('pending');
    $owner = User::find($f['ownerId']);
    $viewerId = srr_make_user();
    $f['userIds'][] = $viewerId;
    $viewer = User::find($viewerId);

    $campaign = AdCampaign::find($f['campaignId']);
    $report = Report::find($f['reportId']);
    $service = app(CoinService::class);

    // Explicit actor = report owner -> blocked
    expect($service->creditImpression($owner, $campaign, $report, $f['ownerId']))->toBeNull();
    expect($service->creditClick($owner, $campaign, $report, $f['ownerId']))->toBeNull();
    srr_assert_no_coins($f);

    // Authenticated as owner, actor omitted -> Auth fallback still blocks
    $this->actingAs($owner);
    expect($service->creditImpression($owner, $campaign, $report))->toBeNull();
    srr_assert_no_coins($f);

    // Legitimate cross-user engagement still credits the owner
    $txn = $service->creditImpression($owner, $campaign, $report, $viewer->id);
    expect($txn)->not->toBeNull();

    $wallet = OriporiCoinWallet::where('user_id', $f['ownerId'])->first();
    expect($wallet)->not->toBeNull();
    expect((float) $wallet->balance)->toBeGreaterThan(0);

    srr_cleanup($f);
});

/*
|--------------------------------------------------------------------------
| 9. Central ledger is the last line of defense
|--------------------------------------------------------------------------
*/

test('central ledger blocks self-reward credits even when called directly', function () {
    $f = srr_setup('pending');
    $owner = User::find($f['ownerId']);

    $this->actingAs($owner);

    $txn = app(FinancialLedgerService::class)->creditCoins(
        userId: $f['ownerId'],
        amount: 5.0,
        type: 'impression_earning',
        description: 'Direct ledger call attempting self-reward',
        metadata: ['report_id' => $f['reportId']],
    );

    expect($txn)->toBeNull();
    srr_assert_no_coins($f);

    srr_cleanup($f);
});

test('central ledger fails closed when the actor cannot be identified', function () {
    $f = srr_setup('pending');

    $txn = app(FinancialLedgerService::class)->creditCoins(
        userId: $f['ownerId'],
        amount: 5.0,
        type: 'impression_earning',
        description: 'Unattributed reward credit with report context',
        metadata: ['report_id' => $f['reportId']],
    );

    expect($txn)->toBeNull();
    srr_assert_no_coins($f);

    srr_cleanup($f);
});
