<?php

use App\Models\CoinTransaction;
use App\Models\OriporiCoinWallet;
use App\Models\User;
use App\Services\FinancialLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Compensating reversals for invalid coin rewards
|--------------------------------------------------------------------------
|
| Invariants under test:
|
|   A. Reversals are linked compensating transactions (append-only ledger):
|      original untouched, wallet corrected by exactly the original amount.
|   B. Idempotent in code AND at the database level (unique index).
|   C. Only positive credits can be reversed; bad inputs are rejected.
|   D. No client-triggerable endpoint exists; forged metadata can never
|      change the reversed amount or owner (both come from the original row).
|   E. Balances are never negative — insufficient funds defer, never force.
|   F. Reconciliation finds historical self-reward credits (actor == owner),
|      reverses them via the central mechanism, and is idempotent.
|   G. Legitimate cross-user rewards are never touched.
|
| Helper names are prefixed with `crv_` to avoid clashes with other suites.
*/

function crv_seed_settings(): void
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

function crv_make_user(string $label): int
{
    return DB::table('users')->insertGetId([
        'name' => $label,
        'email' => 'crv_' . Str::random(12) . '@example.com',
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

function crv_make_partner(int $userId): int
{
    return DB::table('travel_partners')->insertGetId([
        'name' => 'CoinReversal Partner ' . Str::random(5),
        'type' => 'hotel',
        'user_id' => $userId,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function crv_make_campaign(int $partnerId): int
{
    return DB::table('ad_campaigns')->insertGetId([
        'name' => 'CoinReversal Campaign ' . Str::random(5),
        'business_id' => $partnerId,
        'ad_type' => 'banner',
        'content' => 'Test ad content for coin reversal reconciliation',
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

function crv_make_report(int $userId, string $status = 'pending'): int
{
    $catId = DB::table('report_categories')->where('id', 7)->value('id')
        ?? DB::table('report_categories')->value('id');

    return DB::table('reports')->insertGetId([
        'user_id' => $userId,
        'uuid' => Str::uuid()->toString(),
        'title' => 'CoinReversal Report ' . Str::random(5),
        'description' => 'Fixture for coin reversal reconciliation tests',
        'category_id' => $catId,
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'district' => 'Kathmandu',
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function crv_seed_historical_credit(int $userId, int $reportId, int $campaignId, float $amount): int
{
    DB::table('oripori_coin_wallets')->insert([
        'user_id' => $userId,
        'balance' => $amount,
        'total_earned' => $amount,
        'total_withdrawn' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return DB::table('coin_transactions')->insertGetId([
        'user_id' => $userId,
        'type' => 'impression_earning',
        'amount' => $amount,
        'ad_campaign_id' => $campaignId,
        'report_id' => $reportId,
        'description' => 'Historical self-reward credit (credited before the ownership rule existed)',
        'metadata' => json_encode(['ad_campaign_id' => $campaignId]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function crv_make_reward_event(int $userId, int $reportId, int $campaignId, float $coins, string $eventType = 'impression'): int
{
    return DB::table('ad_reward_events')->insertGetId([
        'ad_campaign_id' => $campaignId,
        'user_id' => $userId,
        'report_id' => $reportId,
        'event_type' => $eventType,
        'event_status' => 'validated',
        'gross_amount' => $coins,
        'user_share' => $coins,
        'admin_share' => 0,
        'coins_credited' => $coins,
        'coin_to_npr_rate' => 1,
        'user_share_percent' => 47,
        'idempotency_key' => 'crv_' . Str::random(40),
        'metadata' => json_encode(['fixture' => true]),
        'event_time' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function crv_cleanup(array $ids): void
{
    $ids += ['users' => [], 'partners' => [], 'campaigns' => [], 'reports' => []];

    DB::table('ad_impressions')->whereIn('ad_campaign_id', $ids['campaigns'])->delete();
    DB::table('ad_clicks')->whereIn('ad_campaign_id', $ids['campaigns'])->delete();
    DB::table('ad_reward_events')->whereIn('ad_campaign_id', $ids['campaigns'])->delete();
    DB::table('ad_revenue_ledger')->whereIn('ad_campaign_id', $ids['campaigns'])->delete();
    DB::table('coin_transactions')->where(function ($q) use ($ids) {
        $q->whereIn('user_id', $ids['users'])
            ->orWhereIn('ad_campaign_id', $ids['campaigns'])
            ->orWhereIn('report_id', $ids['reports']);
    })->delete();
    DB::table('ad_campaigns')->whereIn('id', $ids['campaigns'])->delete();
    DB::table('reports')->whereIn('id', $ids['reports'])->delete();
    DB::table('travel_partners')->whereIn('id', $ids['partners'])->delete();

    foreach ($ids['users'] as $id) {
        DB::table('user_fraud_profiles')->where('user_id', $id)->delete();
        DB::table('oripori_coin_wallets')->where('user_id', $id)->delete();
        DB::table('users')->where('id', $id)->delete();
    }
}

/*
|--------------------------------------------------------------------------
| A. Central reversal creates a linked compensating transaction
|    and corrects the wallet (append-only ledger)
|--------------------------------------------------------------------------
*/

test('central reversal creates a linked compensating transaction and corrects the wallet', function () {
    $ledger = app(FinancialLedgerService::class);
    $uid = crv_make_user('CoinReversal A');

    $ledger->creditCoins(userId: $uid, amount: 1.0, type: 'admin_adjustment', description: 'crv fixture funding');
    $original = $ledger->creditCoins(userId: $uid, amount: 0.5, type: 'impression_earning', description: 'crv fixture reward');

    $reversal = $ledger->reverseCoinTransaction(
        $original->id,
        'self_reward_reversal',
        metadata: ['automatic' => true],
    );

    expect($reversal->id)->not->toBe($original->id);
    expect((int) $reversal->reverses_transaction_id)->toBe($original->id);
    expect($reversal->type)->toBe('self_reward_reversal');
    expect((float) $reversal->amount)->toBe(-0.5);
    expect((int) $reversal->user_id)->toBe((int) $original->user_id);

    expect((int) $reversal->metadata['original_transaction_id'])->toBe($original->id);
    expect($reversal->metadata['original_transaction_type'])->toBe('impression_earning');
    expect((float) $reversal->metadata['original_amount'])->toBe(0.5);
    expect($reversal->metadata['reason'])->toBe('self_reward_reversal');
    expect($reversal->metadata['automatic'])->toBeTrue();
    expect($reversal->metadata['idempotency_key'])->toBe("reversal:self_reward_reversal:{$original->id}");

    $original->refresh();
    expect((float) $original->amount)->toBe(0.5);
    expect($original->type)->toBe('impression_earning');
    expect($original->reverses_transaction_id)->toBeNull();

    $wallet = OriporiCoinWallet::where('user_id', $uid)->first();
    expect((float) $wallet->balance)->toBe(1.0);
    expect((float) $wallet->total_earned)->toBe(1.0);
    expect((float) $wallet->total_withdrawn)->toBe(0.0);

    $ledgerSum = round((float) CoinTransaction::where('user_id', $uid)->sum('amount'), 2);
    expect($ledgerSum)->toBe(1.0);

    crv_cleanup(['users' => [$uid]]);
});

/*
|--------------------------------------------------------------------------
| B. Idempotency: code-level and database-level
|--------------------------------------------------------------------------
*/

test('reversals are idempotent and backed by a database unique constraint', function () {
    $ledger = app(FinancialLedgerService::class);
    $uid = crv_make_user('CoinReversal B');
    $original = $ledger->creditCoins(userId: $uid, amount: 0.5, type: 'impression_earning', description: 'crv fixture reward');

    $first = $ledger->reverseCoinTransaction($original->id, 'self_reward_reversal');
    $second = $ledger->reverseCoinTransaction($original->id, 'self_reward_reversal');
    $third = $ledger->reverseCoinTransaction($original->id, 'admin_adjustment_reversal');

    expect($second->id)->toBe($first->id);
    expect($third->id)->toBe($first->id);
    expect(CoinTransaction::where('reverses_transaction_id', $original->id)->count())->toBe(1);

    $wallet = OriporiCoinWallet::where('user_id', $uid)->first();
    expect((float) $wallet->balance)->toBe(0.0);
    expect((float) $wallet->total_earned)->toBe(0.0);

    expect(fn () => CoinTransaction::create([
        'user_id' => $uid,
        'type' => 'self_reward_reversal',
        'amount' => -0.5,
        'reverses_transaction_id' => $original->id,
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    expect(CoinTransaction::where('reverses_transaction_id', $original->id)->count())->toBe(1);

    crv_cleanup(['users' => [$uid]]);
});

/*
|--------------------------------------------------------------------------
| C. Only positive credits are reversible
|--------------------------------------------------------------------------
*/

test('debits, reversals of reversals, unknown ids and invalid reasons are rejected', function () {
    $ledger = app(FinancialLedgerService::class);
    $uid = crv_make_user('CoinReversal C');

    $ledger->creditCoins(userId: $uid, amount: 2.0, type: 'admin_adjustment', description: 'crv fixture funding');
    $credit = $ledger->creditCoins(userId: $uid, amount: 1.0, type: 'impression_earning', description: 'crv fixture reward');
    $withdrawal = $ledger->debitCoins(userId: $uid, amount: 0.4, type: 'withdrawal', description: 'crv fixture cashout');
    $reversal = $ledger->reverseCoinTransaction($credit->id, 'self_reward_reversal');

    expect(fn () => $ledger->reverseCoinTransaction($withdrawal->id, 'self_reward_reversal'))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => $ledger->reverseCoinTransaction(999999999, 'self_reward_reversal'))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => $ledger->reverseCoinTransaction($reversal->id, 'self_reward_reversal'))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => $ledger->reverseCoinTransaction($credit->id, 'INVALID REASON'))
        ->toThrow(\InvalidArgumentException::class);

    expect(CoinTransaction::where('user_id', $uid)->whereNotNull('reverses_transaction_id')->count())->toBe(1);

    $wallet = OriporiCoinWallet::where('user_id', $uid)->first();
    expect((float) $wallet->balance)->toBe(1.6); // 2.0 + 1.0 - 0.4 - 1.0

    crv_cleanup(['users' => [$uid]]);
});

/*
|--------------------------------------------------------------------------
| D. No client path; forged metadata cannot alter amount or owner
|--------------------------------------------------------------------------
*/

test('no client endpoint exists for reversals and forged metadata cannot alter amount or owner', function () {
    $this->postJson('/api/v1/wallet/reverse', [])->assertNotFound();
    $this->postJson('/api/v1/wallet/coins/reverse', [])->assertNotFound();
    $this->postJson('/api/v1/coins/reverse', [])->assertNotFound();

    $ledger = app(FinancialLedgerService::class);
    $ownerId = crv_make_user('CoinReversal D Owner');
    $otherId = crv_make_user('CoinReversal D Other');

    $original = $ledger->creditCoins(userId: $ownerId, amount: 0.02, type: 'impression_earning', description: 'crv fixture reward');

    $reversal = $ledger->reverseCoinTransaction($original->id, 'self_reward_reversal', metadata: [
        'amount' => 1000.0,
        'user_id' => $otherId,
        'original_transaction_id' => 999999999,
    ]);

    expect((float) $reversal->amount)->toBe(-0.02);
    expect((int) $reversal->user_id)->toBe($ownerId);
    expect((int) $reversal->metadata['original_transaction_id'])->toBe($original->id);

    $wallet = OriporiCoinWallet::where('user_id', $ownerId)->first();
    expect((float) $wallet->balance)->toBe(0.0);

    expect(CoinTransaction::where('user_id', $otherId)->count())->toBe(0);
    expect(OriporiCoinWallet::where('user_id', $otherId)->exists())->toBeFalse();

    crv_cleanup(['users' => [$ownerId, $otherId]]);
});

/*
|--------------------------------------------------------------------------
| E. Never a negative balance; deferred reversal can be retried
|--------------------------------------------------------------------------
*/

test('reversals never create a negative balance and retry after funds return', function () {
    $ledger = app(FinancialLedgerService::class);
    $uid = crv_make_user('CoinReversal E');

    $original = $ledger->creditCoins(userId: $uid, amount: 0.02, type: 'impression_earning', description: 'crv fixture reward');
    $ledger->debitCoins(userId: $uid, amount: 0.02, type: 'withdrawal', description: 'crv fixture cashout');

    expect(fn () => $ledger->reverseCoinTransaction($original->id, 'self_reward_reversal'))
        ->toThrow(\RuntimeException::class);

    expect(CoinTransaction::where('user_id', $uid)->whereNotNull('reverses_transaction_id')->count())->toBe(0);

    $wallet = OriporiCoinWallet::where('user_id', $uid)->first();
    expect((float) $wallet->balance)->toBe(0.0);
    expect((float) $wallet->total_withdrawn)->toBe(0.02);

    $ledger->creditCoins(userId: $uid, amount: 0.02, type: 'admin_adjustment', description: 'crv fixture refund');

    $reversal = $ledger->reverseCoinTransaction($original->id, 'self_reward_reversal');
    expect((float) $reversal->amount)->toBe(-0.02);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe(0.0);
    expect((float) $wallet->total_withdrawn)->toBe(0.02);
    expect(CoinTransaction::where('user_id', $uid)->whereNotNull('reverses_transaction_id')->count())->toBe(1);

    crv_cleanup(['users' => [$uid]]);
});

/*
|--------------------------------------------------------------------------
| F. Reconciliation reverses historical self-reward credits
|--------------------------------------------------------------------------
*/

test('reconciliation reverses a historical self-reward credit and stays idempotent', function () {
    $ownerId = crv_make_user('CoinReversal F Owner');
    $partnerUserId = crv_make_user('CoinReversal F Partner');
    $partnerId = crv_make_partner($partnerUserId);
    $campaignId = crv_make_campaign($partnerId);
    $reportId = crv_make_report($ownerId, 'pending');

    // Historical data: credited while actor == report owner (pre-protection)
    $txnId = crv_seed_historical_credit($ownerId, $reportId, $campaignId, 0.02);
    $eventId = crv_make_reward_event($ownerId, $reportId, $campaignId, 0.02);

    $this->artisan('coins:reconcile-self-rewards', ['--dry-run' => true, '--limit' => 500, '--days' => 30])
        ->assertExitCode(0);

    expect(CoinTransaction::where('reverses_transaction_id', $txnId)->count())->toBe(0);
    expect((float) OriporiCoinWallet::where('user_id', $ownerId)->value('balance'))->toBe(0.02);

    $this->artisan('coins:reconcile-self-rewards', ['--limit' => 500, '--days' => 30])
        ->assertExitCode(0);

    $original = CoinTransaction::find($txnId);
    expect((float) $original->amount)->toBe(0.02);
    expect($original->type)->toBe('impression_earning');

    $reversal = CoinTransaction::where('reverses_transaction_id', $txnId)->first();
    expect($reversal)->not->toBeNull();
    expect((float) $reversal->amount)->toBe(-0.02);
    expect($reversal->type)->toBe('self_reward_reversal');
    expect((int) $reversal->user_id)->toBe($ownerId);
    expect((int) $reversal->report_id)->toBe($reportId);
    expect((int) $reversal->metadata['original_reward_event_id'])->toBe($eventId);
    expect($reversal->metadata['automatic'])->toBeTrue();
    expect($reversal->metadata['detection'])->toBe('self_reward_reconciliation');

    $wallet = OriporiCoinWallet::where('user_id', $ownerId)->first();
    expect((float) $wallet->balance)->toBe(0.0);
    expect((float) $wallet->total_earned)->toBe(0.0);

    $this->artisan('coins:reconcile-self-rewards', ['--limit' => 500, '--days' => 30])
        ->assertExitCode(0);

    expect(CoinTransaction::where('reverses_transaction_id', $txnId)->count())->toBe(1);
    expect((float) OriporiCoinWallet::where('user_id', $ownerId)->value('balance'))->toBe(0.0);

    crv_cleanup([
        'users' => [$ownerId, $partnerUserId],
        'partners' => [$partnerId],
        'campaigns' => [$campaignId],
        'reports' => [$reportId],
    ]);
});

/*
|--------------------------------------------------------------------------
| G. Legitimate rewards are never reversed
|--------------------------------------------------------------------------
*/

test('legitimate cross-user rewards are never reversed by reconciliation', function () {
    crv_seed_settings();

    $ownerId = crv_make_user('CoinReversal G Owner');
    $partnerUserId = crv_make_user('CoinReversal G Partner');
    $partnerId = crv_make_partner($partnerUserId);
    $campaignId = crv_make_campaign($partnerId);
    $reportId = crv_make_report($ownerId, 'pending');
    $viewerId = crv_make_user('CoinReversal G Viewer');

    $viewer = User::find($viewerId);
    $response = $this->actingAs($viewer)->postJson('/api/v1/ads/track-impression', [
        'ad_campaign_id' => $campaignId,
        'report_id' => $reportId,
        'context' => 'report',
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    expect((float) $response->json('coins_earned'))->toBeGreaterThan(0);

    $txn = CoinTransaction::where('report_id', $reportId)
        ->where('type', 'impression_earning')
        ->first();
    expect($txn)->not->toBeNull();

    $balanceBefore = (float) OriporiCoinWallet::where('user_id', $ownerId)->value('balance');

    $this->artisan('coins:reconcile-self-rewards', ['--limit' => 500, '--days' => 30])
        ->assertExitCode(0);

    expect(CoinTransaction::where('reverses_transaction_id', $txn->id)->count())->toBe(0);
    expect((float) OriporiCoinWallet::where('user_id', $ownerId)->value('balance'))->toBe($balanceBefore);

    crv_cleanup([
        'users' => [$ownerId, $partnerUserId, $viewerId],
        'partners' => [$partnerId],
        'campaigns' => [$campaignId],
        'reports' => [$reportId],
    ]);
});
