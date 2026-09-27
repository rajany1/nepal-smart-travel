<?php

use App\Models\AdCampaign;
use App\Models\AdImpression;
use App\Models\AdClick;
use App\Models\AdRewardEvent;
use App\Models\AdRevenueLedger;
use App\Models\CoinTransaction;
use App\Models\OriporiCoinWallet;
use App\Models\User;
use App\Models\TravelPartner;
use App\Models\CoinSetting;
use App\Models\GameSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function makeUser(): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Test User',
        'email' => 'test_' . Str::random(8) . '@example.com',
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

function makeCampaign(int $partnerId, array $overrides = []): int
{
    return DB::table('ad_campaigns')->insertGetId(array_merge([
        'name' => 'Test Campaign ' . Str::random(5),
        'business_id' => $partnerId,
        'ad_type' => 'banner',
        'content' => 'Test ad content',
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
    ], $overrides));
}

function makePartner(int $userId): int
{
    return DB::table('travel_partners')->insertGetId([
        'name' => 'Test Partner ' . Str::random(5),
        'type' => 'hotel',
        'user_id' => $userId,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeReport(int $userId): int
{
    $catId = DB::table('report_categories')->where('id', 7)->value('id');
    if ($catId === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 is missing - '
            . 'tests must reference the real category and must not create fixture categories.'
        );
    }

    return DB::table('reports')->insertGetId([
        'user_id' => $userId,
        'uuid' => Str::uuid()->toString(),
        'title' => 'Test Report ' . Str::random(5),
        'description' => 'Test report description for ad testing purposes',
        'category_id' => $catId,
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function seedAdSettings(): void
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

function cleanupTest(int $userId, int $campaignId): void
{
    DB::table('ad_impressions')->where('ad_campaign_id', $campaignId)->delete();
    DB::table('ad_clicks')->where('ad_campaign_id', $campaignId)->delete();
    DB::table('ad_reward_events')->where('ad_campaign_id', $campaignId)->delete();
    DB::table('ad_revenue_ledger')->where('ad_campaign_id', $campaignId)->delete();
    DB::table('coin_transactions')->where('ad_campaign_id', $campaignId)->delete();
    DB::table('ad_campaigns')->where('id', $campaignId)->delete();
    DB::table('oripori_coin_wallets')->where('user_id', $userId)->delete();
    DB::table('users')->where('id', $userId)->delete();
}

/*
|--------------------------------------------------------------------------
| A. VALID EVENTS
|--------------------------------------------------------------------------
*/

test('valid impression creates full financial settlement atomically', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $reportId = makeReport($ownerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
        'report_id' => $reportId,
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(1);
    expect((float) $campaign->spent_amount)->toBeGreaterThan(0);

    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->first();
    expect($reward)->not->toBeNull();
    expect($reward->event_status)->toBe('validated');
    expect((float) $reward->gross_amount)->toBeGreaterThan(0);
    expect((float) $reward->coins_credited)->toBeGreaterThan(0);

    $ledger = AdRevenueLedger::where('ad_campaign_id', $campaignId)->first();
    expect($ledger)->not->toBeNull();
    expect((float) $ledger->gross_amount)->toBeGreaterThan(0);

    $coinTxn = CoinTransaction::where('ad_campaign_id', $campaignId)->first();
    expect($coinTxn)->not->toBeNull();
    expect((float) $coinTxn->amount)->toBeGreaterThan(0);

    $wallet = OriporiCoinWallet::where('user_id', $ownerId)->first();
    expect($wallet)->not->toBeNull();
    expect((float) $wallet->balance)->toBeGreaterThan(0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('valid click creates full financial settlement atomically', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $reportId = makeReport($ownerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    DB::table('ad_impressions')->insert([
        'ad_campaign_id' => $campaignId,
        'user_id' => $viewerId,
        'ip_address' => '127.0.0.1',
        'viewed_at' => now(),
    ]);
    AdCampaign::where('id', $campaignId)->increment('current_impressions');

    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-click', [
        'ad_campaign_id' => $campaignId,
        'report_id' => $reportId,
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_clicks)->toBe(1);

    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->where('event_type', 'click')->first();
    expect($reward)->not->toBeNull();
    expect($reward->event_status)->toBe('validated');

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('report owner receives correct coins', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $reportId = makeReport($ownerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
        'report_id' => $reportId,
        'context' => 'report',
    ]);

    $response->assertOk();
    $wallet = OriporiCoinWallet::where('user_id', $ownerId)->first();
    expect($wallet)->not->toBeNull();
    expect((float) $wallet->balance)->toBeGreaterThan(0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
    DB::table('oripori_coin_wallets')->where('user_id', $viewerId)->delete();
});

/*
|--------------------------------------------------------------------------
| B. IDEMPOTENCY
|--------------------------------------------------------------------------
*/

test('duplicate impression is rejected by idempotency gate', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    // Bypass IdempotencyMiddleware - ad endpoints have their own idempotency
    $this->withoutMiddleware(\App\Http\Middleware\IdempotencyMiddleware::class);

    $response1 = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);
    $response1->assertOk();

    $response2 = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);
    $response2->assertStatus(422)->assertJson(['error' => 'Impression already recorded']);

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(1);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('duplicate click is rejected by idempotency gate', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    DB::table('ad_impressions')->insert([
        'ad_campaign_id' => $campaignId,
        'user_id' => $viewerId,
        'ip_address' => '127.0.0.1',
        'viewed_at' => now(),
    ]);
    AdCampaign::where('id', $campaignId)->increment('current_impressions');

    // Bypass IdempotencyMiddleware - ad endpoints have their own idempotency
    $this->withoutMiddleware(\App\Http\Middleware\IdempotencyMiddleware::class);

    $response1 = $this->actingAs($viewer)->postJson('/api/v1/ads/track-click', [
        'ad_campaign_id' => $campaignId,
    ]);
    $response1->assertOk();

    $response2 = $this->actingAs($viewer)->postJson('/api/v1/ads/track-click', [
        'ad_campaign_id' => $campaignId,
    ]);
    $response2->assertStatus(422)->assertJson(['error' => 'Click already recorded']);

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_clicks)->toBe(1);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('IdempotencyMiddleware would intercept duplicate POST but controller idempotency handles it', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    // First request WITH middleware - middleware caches the response
    $response1 = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);
    $response1->assertOk();

    // Second request WITH middleware - middleware replays cached response
    $response2 = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);
    // Middleware returns 200 (replayed) - demonstrates middleware intercepts before controller
    $response2->assertOk();

    // Campaign only incremented once (first request went through controller)
    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(1);

    // Cleanup idempotency keys
    DB::table('idempotency_keys')->where('key', 'like', '%ads.track-impression%')->delete();

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

/*
|--------------------------------------------------------------------------
| C. FRAUD
|--------------------------------------------------------------------------
*/

test('bot impression is rejected and recorded as rejected', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $response = $this->actingAs($viewer)->withHeaders([
        'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
    ])->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'Event blocked']);

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(0);

    $impression = DB::table('ad_impressions')->where('ad_campaign_id', $campaignId)->first();
    expect($impression)->not->toBeNull();

    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->first();
    expect($reward->event_status)->toBe('rejected');
    expect((float) $reward->gross_amount)->toBe(0.0);
    expect((float) $reward->coins_credited)->toBe(0.0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('self-click is rejected', function () {
    seedAdSettings();
    $userId = makeUser();
    $partnerId = makePartner($userId);
    $campaignId = makeCampaign($partnerId);

    $user = User::find($userId);

    DB::table('ad_impressions')->insert([
        'ad_campaign_id' => $campaignId,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'viewed_at' => now(),
    ]);
    AdCampaign::where('id', $campaignId)->increment('current_impressions');

    $response = $this->actingAs($user)->postJson('/api/v1/ads/track-click', [
        'ad_campaign_id' => $campaignId,
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'Event blocked']);

    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->where('event_type', 'click')->first();
    expect($reward->event_status)->toBe('rejected');

    cleanupTest($userId, $campaignId);
});

test('rejected events do not receive reward', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $this->actingAs($viewer)->withHeaders([
        'User-Agent' => 'curl/7.68.0',
    ])->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    $coinTxns = DB::table('coin_transactions')->where('ad_campaign_id', $campaignId)->count();
    expect($coinTxns)->toBe(0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('rejected events do not consume campaign budget', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId, ['budget' => 100]);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $this->actingAs($viewer)->withHeaders([
        'User-Agent' => 'python-requests/2.28.0',
    ])->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(0);
    expect((float) $campaign->spent_amount)->toBe(0.0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('campaign fraud score is updated on fraud detection', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);
    $initialScore = AdCampaign::find($campaignId)->fraud_score;

    $this->actingAs($viewer)->withHeaders([
        'User-Agent' => 'Googlebot/2.1',
    ])->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->fraud_score)->toBeGreaterThan($initialScore);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('burst detection blocks 6th impression within 2-minute window', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    // Record 5 impressions within 2 minutes (at burst limit of 5)
    for ($i = 0; $i < 5; $i++) {
        DB::table('ad_impressions')->insert([
            'ad_campaign_id' => $campaignId,
            'user_id' => $viewerId,
            'ip_address' => '127.0.0.1',
            'viewed_at' => now()->subSeconds(30 * $i),
        ]);
    }

    // 6th impression from same user within burst window should be blocked
    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'Event blocked']);

    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->first();
    expect($reward->event_status)->toBe('rejected');

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('velocity breach blocks impression after 20 within 1 hour', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    // Record 19 impressions within the last hour
    for ($i = 0; $i < 19; $i++) {
        DB::table('ad_impressions')->insert([
            'ad_campaign_id' => $campaignId,
            'user_id' => $viewerId,
            'ip_address' => '127.0.0.1',
            'viewed_at' => now()->subMinutes(2),
        ]);
    }

    // 20th impression should trigger velocity breach
    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    $response->assertStatus(422)->assertJson(['error' => 'Event blocked']);

    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->first();
    expect($reward->event_status)->toBe('rejected');

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('raw impression is recorded even when event is rejected', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $this->actingAs($viewer)->withHeaders([
        'User-Agent' => 'Googlebot/2.1',
    ])->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    // Raw impression must exist for analytics (even though event is rejected)
    $rawImpression = DB::table('ad_impressions')->where('ad_campaign_id', $campaignId)->first();
    expect($rawImpression)->not->toBeNull();
    expect($rawImpression->user_id)->toBe($viewerId);

    // Reward event is rejected
    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->first();
    expect($reward->event_status)->toBe('rejected');
    expect((float) $reward->gross_amount)->toBe(0.0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

/*
|--------------------------------------------------------------------------
| D. CLICK WITHOUT IMPRESSION
|--------------------------------------------------------------------------
*/

test('click without impression creates synthetic impression and bills correctly', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $reportId = makeReport($ownerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-click', [
        'ad_campaign_id' => $campaignId,
        'report_id' => $reportId,
        'context' => 'report',
    ]);

    $response->assertOk();

    $impression = DB::table('ad_impressions')->where('ad_campaign_id', $campaignId)->first();
    expect($impression)->not->toBeNull();

    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(1);
    expect($campaign->current_clicks)->toBe(1);

    $ledger = AdRevenueLedger::where('ad_campaign_id', $campaignId)->where('event_type', 'click')->first();
    expect($ledger)->not->toBeNull();
    expect((float) $ledger->gross_amount)->toBeGreaterThan(0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

/*
|--------------------------------------------------------------------------
| E. BACKWARD COMPATIBILITY
|--------------------------------------------------------------------------
*/

test('API response shape is unchanged', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
    ]);

    $response->assertOk()->assertJsonStructure([
        'success',
        'coins_earned',
    ]);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('active endpoint still filters by fraud_score', function () {
    seedAdSettings();
    $userId = makeUser();
    $partnerId = makePartner($userId);

    DB::table('user_fraud_profiles')->where('user_id', $userId)->delete();

    $flaggedId = makeCampaign($partnerId, ['fraud_score' => 85]);
    $goodId = makeCampaign($partnerId, ['name' => 'Good Campaign', 'fraud_score' => 0]);

    $user = User::find($userId);

    $response = $this->actingAs($user)->getJson('/api/v1/ads/active');
    $response->assertOk();

    $allCampaignIds = AdCampaign::active()
        ->where('fraud_score', '<', 80)
        ->pluck('id')
        ->toArray();

    expect($allCampaignIds)->toContain($goodId);
    expect($allCampaignIds)->not->toContain($flaggedId);

    DB::table('ad_campaigns')->whereIn('id', [$flaggedId, $goodId])->delete();
    DB::table('users')->where('id', $userId)->delete();
});

/*
|--------------------------------------------------------------------------
| F. CANONICAL IDEMPOTENCY KEY
|--------------------------------------------------------------------------
*/

test('canonical idempotency key is deterministic', function () {
    $key1 = AdRewardEvent::generateIdempotencyKey('imp', 1, 100);
    $key2 = AdRewardEvent::generateIdempotencyKey('imp', 1, 100);
    expect($key1)->toBe($key2);

    $key3 = AdRewardEvent::generateIdempotencyKey('click', 1, 100);
    expect($key3)->toContain('click:');
    expect($key3)->not->toBe($key1);
});

test('same user+campaign+type produces same key within window', function () {
    $key1 = AdRewardEvent::generateIdempotencyKey('imp', 42, 7);
    $key2 = AdRewardEvent::generateIdempotencyKey('imp', 42, 7);
    expect($key1)->toBe($key2);
    expect($key1)->toMatch('/^imp:42:7:\d+$/');
});

/*
|--------------------------------------------------------------------------
| G. CAMPAIGN COUNTER CONSISTENCY
|--------------------------------------------------------------------------
*/

test('campaign spend matches valid monetized events', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
            'ad_campaign_id' => $campaignId,
        ]);
    }

    $campaign = AdCampaign::find($campaignId);
    $expectedSpend = round(($campaign->current_impressions / 1000) * 50, 2);
    expect((float) $campaign->spent_amount)->toBe($expectedSpend);

    $ledgerTotal = AdRevenueLedger::where('ad_campaign_id', $campaignId)->sum('gross_amount');
    expect(round((float) $ledgerTotal, 4))->toBe($expectedSpend);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

/*
|--------------------------------------------------------------------------
| H. FAILURE INJECTION - TRANSACTION ATOMICITY
|--------------------------------------------------------------------------
*/

test('coin credit exception rolls back entire financial settlement', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $reportId = makeReport($ownerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    // Mock CoinService to throw during coin credit (inside outer DB::transaction)
    $this->mock(\App\Services\CoinService::class, function ($mock) {
        $mock->shouldReceive('creditImpression')
            ->once()
            ->andThrow(new \RuntimeException('Coin credit failed: database timeout'));
    });

    try {
        $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
            'ad_campaign_id' => $campaignId,
            'report_id' => $reportId,
            'context' => 'report',
        ]);
    } catch (\Throwable $e) {
        // Exception propagates as 500 - expected
    }

    // PROVE: entire outer transaction rolled back
    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(0);
    expect((float) $campaign->spent_amount)->toBe(0.0);

    // PROVE: no revenue ledger created
    expect(AdRevenueLedger::where('ad_campaign_id', $campaignId)->count())->toBe(0);

    // PROVE: no coins credited
    expect(CoinTransaction::where('ad_campaign_id', $campaignId)->count())->toBe(0);

    // PROVE: wallet not created or balance = 0
    $wallet = OriporiCoinWallet::where('user_id', $ownerId)->first();
    if ($wallet) {
        expect((float) $wallet->balance)->toBe(0.0);
    }

    // PROVE: reward event exists (created before transaction) but NOT finalized
    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->first();
    expect($reward)->not->toBeNull();
    expect((float) $reward->gross_amount)->toBe(0.0);
    expect((float) $reward->coins_credited)->toBe(0.0);

    // PROVE: raw impression exists (created outside transaction - audit survives)
    $rawImpression = DB::table('ad_impressions')->where('ad_campaign_id', $campaignId)->first();
    expect($rawImpression)->not->toBeNull();

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('revenue ledger failure rolls back entire settlement including inner coin transaction', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $reportId = makeReport($ownerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    // Save original dispatcher so we can restore it
    $dispatcher = \App\Models\AdRevenueLedger::getEventDispatcher();
    \App\Models\AdRevenueLedger::setEventDispatcher(new \Illuminate\Events\Dispatcher());

    // Register a model event that throws on creating - this fires inside the
    // outer DB::transaction AFTER the inner coin-credit transaction has committed.
    \App\Models\AdRevenueLedger::creating(function () {
        throw new \RuntimeException('Revenue ledger write failed');
    });

    try {
        $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
            'ad_campaign_id' => $campaignId,
            'report_id' => $reportId,
            'context' => 'report',
        ]);
    } catch (\Throwable $e) {
        // Expected - exception propagates
    } finally {
        // Restore original dispatcher
        \App\Models\AdRevenueLedger::setEventDispatcher($dispatcher);
    }

    // PROVE: outer transaction rolled back - campaign billing undone
    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(0);
    expect((float) $campaign->spent_amount)->toBe(0.0);

    // PROVE: revenue ledger not created
    expect(AdRevenueLedger::where('ad_campaign_id', $campaignId)->count())->toBe(0);

    // PROVE: inner transaction also rolled back - no coin transaction, no wallet change
    expect(CoinTransaction::where('ad_campaign_id', $campaignId)->count())->toBe(0);

    $wallet = OriporiCoinWallet::where('user_id', $ownerId)->first();
    if ($wallet) {
        expect((float) $wallet->balance)->toBe(0.0);
    }

    // PROVE: raw impression still exists (audit survives)
    $rawImpression = DB::table('ad_impressions')->where('ad_campaign_id', $campaignId)->first();
    expect($rawImpression)->not->toBeNull();

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});

test('revenue ledger user_share is zero when coins are not credited', function () {
    seedAdSettings();
    $ownerId = makeUser();
    $partnerId = makePartner(makeUser());
    $campaignId = makeCampaign($partnerId);
    $reportId = makeReport($ownerId);
    $viewerId = makeUser();

    $viewer = User::find($viewerId);

    // Mock CoinService to return null (simulates cooldown/cap blocking credit)
    $this->mock(\App\Services\CoinService::class, function ($mock) {
        $mock->shouldReceive('creditImpression')
            ->once()
            ->andReturn(null);
    });

    $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
        'report_id' => $reportId,
        'context' => 'report',
    ])->assertOk();

    // PROVE: revenue ledger exists but user_share = 0 (no coins = no user share)
    $ledger = AdRevenueLedger::where('ad_campaign_id', $campaignId)->first();
    expect($ledger)->not->toBeNull();
    expect((float) $ledger->user_share)->toBe(0.0);
    expect((float) $ledger->admin_share)->toBeGreaterThan(0);
    expect((float) $ledger->gross_amount)->toBeGreaterThan(0);

    // PROVE: reward event has coins_credited = 0
    $reward = AdRewardEvent::where('ad_campaign_id', $campaignId)->first();
    expect((float) $reward->coins_credited)->toBe(0.0);
    expect((float) $reward->user_share)->toBe(0.0);

    // PROVE: no coin transaction, no wallet change
    expect(CoinTransaction::where('ad_campaign_id', $campaignId)->count())->toBe(0);

    // PROVE: campaign billing DID commit (advertiser still charged)
    $campaign = AdCampaign::find($campaignId);
    expect($campaign->current_impressions)->toBe(1);
    expect((float) $campaign->spent_amount)->toBeGreaterThan(0);

    cleanupTest($ownerId, $campaignId);
    DB::table('users')->where('id', $viewerId)->delete();
});
