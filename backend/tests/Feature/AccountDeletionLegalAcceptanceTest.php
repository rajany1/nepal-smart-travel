<?php

use App\Models\User;
use App\Models\LegalDocument;
use App\Models\LegalDocumentAcceptance;
use App\Models\OriporiCoinWallet;
use App\Models\CoinTransaction;
use App\Models\Report;
use App\Models\PushToken;
use App\Models\EmergencyContact;
use App\Models\SosAlert;
use App\Models\PlaceReview;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createTestUser(array $overrides = []): User
{
    $defaults = [
        'name' => 'Test User',
        'email' => 'test_' . Str::random(8) . '@example.com',
        'phone' => '98' . mt_rand(10000000, 99999999),
        'password' => bcrypt('TestPass123'),
        'uuid' => Str::uuid()->toString(),
        'status' => 'active',
        'is_active' => 1,
        'badges' => [],
        'expertise_regions' => [],
        'settings' => [],
        'total_xp' => 100,
        'current_level' => 3,
    ];

    $data = array_merge($defaults, $overrides);
    return User::create($data);
}

function createUserToken(User $user, string $name = 'app'): string
{
    return $user->createToken($name)->plainTextToken;
}

function uniqueEmail(): string
{
    return 'u_' . Str::random(8) . '@example.com';
}

/*
|--------------------------------------------------------------------------
| Account Deletion Tests
|--------------------------------------------------------------------------
*/

it('requires authentication to delete account', function () {
    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => 'test@example.com',
    ]);

    $response->assertStatus(401);
});

it('requires confirmation field', function () {
    $user = createTestUser();
    $token = createUserToken($user);

    $response = $this->deleteJson('/api/v1/users/me', [], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('confirmation');
});

it('rejects incorrect email confirmation', function () {
    $user = createTestUser();
    $token = createUserToken($user);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => 'wrong@email.com',
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
    ]);
});

it('deletes account with correct confirmation', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $user->refresh();
    expect($user->name)->toBe('Deleted User');
    expect($user->email)->toContain('@deleted.local');
    expect($user->phone)->toBeNull();
    expect($user->avatar)->toBeNull();
});

it('revokes all tokens on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);
    createUserToken($user, 'refresh');

    expect($user->tokens()->count())->toBeGreaterThanOrEqual(2);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('deletes push tokens on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    PushToken::create([
        'user_id' => $user->id,
        'fcm_token' => 'test_fcm_token_123',
        'device_type' => 'android',
    ]);

    expect($user->pushTokens()->count())->toBe(1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->pushTokens()->count())->toBe(0);
});

it('deletes social accounts on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    DB::table('social_accounts')->insert([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google_' . Str::random(8),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->socialAccounts()->count())->toBe(1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->socialAccounts()->count())->toBe(0);
});

it('zeroes wallet balance on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    OriporiCoinWallet::create([
        'user_id' => $user->id,
        'balance' => 500,
    ]);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect((float) $user->fresh()->wallet->balance)->toBe(0.0);
});

it('anonymizes coin transactions instead of deleting', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    OriporiCoinWallet::create(['user_id' => $user->id, 'balance' => 100]);

    CoinTransaction::create([
        'user_id' => $user->id,
        'type' => 'credit',
        'amount' => 100,
        'description' => 'Ad impression reward',
        'balance_after' => 100,
    ]);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();

    $tx = CoinTransaction::where('user_id', $user->id)->first();
    expect($tx)->not->toBeNull();
    expect($tx->description)->toContain('[deleted]');
});

it('retains reports with anonymized author', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    $categoryId = DB::table('report_categories')->where('id', 7)->value('id');
    if ($categoryId === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 is missing - '
            . 'tests must reference the real category and must not create fixture categories.'
        );
    }

    Report::create([
        'user_id' => $user->id,
        'category_id' => $categoryId,
        'title' => 'Test Report ' . Str::random(4),
        'description' => 'A test report',
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'status' => 'approved',
    ]);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();

    $report = Report::where('title', 'LIKE', 'Test Report%')->first();
    expect($report)->not->toBeNull();
    expect($report->user_id)->toBeNull();
});

it('deletes xp transactions on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    DB::table('xp_transactions')->insert([
        'user_id' => $user->id,
        'amount' => 50,
        'action_type' => 'report_approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->xpTransactions()->count())->toBe(1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->xpTransactions()->count())->toBe(0);
});

it('deletes active subscription on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    $planId = DB::table('subscription_plans')->insertGetId([
        'name' => 'Free Plan',
        'slug' => 'free_' . Str::random(4),
        'price' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    UserSubscription::create([
        'user_id' => $user->id,
        'subscription_plan_id' => $planId,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    expect($user->subscription()->count())->toBeGreaterThanOrEqual(1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->subscription()->count())->toBe(0);
});

it('is idempotent - repeated deletion returns success', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    $response1 = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response1->assertOk();

    $service = new \App\Services\AccountDeletionService();
    $result = $service->delete($user, 'user_requested');
    expect($result['success'])->toBeTrue();
});

it('deletes emergency contacts on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    EmergencyContact::create([
        'user_id' => $user->id,
        'name' => 'Mom',
        'phone' => '9841234567',
        'relationship' => 'mother',
    ]);

    expect($user->emergencyContacts()->count())->toBe(1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->emergencyContacts()->count())->toBe(0);
});

it('deletes SOS alerts on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    SosAlert::create([
        'user_id' => $user->id,
        'latitude' => 27.7172,
        'longitude' => 85.3240,
        'status' => 'resolved',
        'emergency_type' => 'other',
        'started_at' => now(),
    ]);

    expect($user->sosAlerts()->count())->toBe(1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->sosAlerts()->count())->toBe(0);
});

it('deletes support conversations on deletion', function () {
    $email = uniqueEmail();
    $user = createTestUser(['email' => $email]);
    $token = createUserToken($user);

    $convId = DB::table('support_conversations')->insertGetId([
        'user_id' => $user->id,
        'subject' => 'Test issue',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('support_messages')->insert([
        'support_conversation_id' => $convId,
        'user_id' => $user->id,
        'content' => 'Help me',
        'sender_type' => 'user',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->supportConversations()->count())->toBe(1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertOk();
    expect($user->fresh()->supportConversations()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Legal Acceptance Tests
|--------------------------------------------------------------------------
*/

it('records legal acceptance on registration', function () {
    LegalDocument::updateOrCreate(
        ['type' => 'terms_conditions', 'version' => 'test_1.0'],
        ['title' => 'Terms of Use', 'content' => '<h2>Terms</h2>', 'is_published' => true, 'published_at' => now()]
    );

    LegalDocument::updateOrCreate(
        ['type' => 'privacy_policy', 'version' => 'test_1.0'],
        ['title' => 'Privacy Policy', 'content' => '<h2>Privacy</h2>', 'is_published' => true, 'published_at' => now()]
    );

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Consent User',
        'email' => 'consent_' . Str::random(8) . '@example.com',
        'password' => 'TestPass123',
        'terms_accepted' => true,
        'privacy_accepted' => true,
        'age_confirmed' => true,
    ]);

    $response->assertOk();

    $userId = $response->json('data.id');
    $acceptances = LegalDocumentAcceptance::where('user_id', $userId)->get();

    expect($acceptances->count())->toBeGreaterThanOrEqual(2);

    $terms = $acceptances->firstWhere('document_type', 'terms_conditions');
    expect($terms)->not->toBeNull();

    $privacy = $acceptances->firstWhere('document_type', 'privacy_policy');
    expect($privacy)->not->toBeNull();
});

it('rejects registration without terms acceptance', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'No Terms',
        'email' => 'noterms_' . Str::random(8) . '@example.com',
        'password' => 'TestPass123',
        'terms_accepted' => false,
        'privacy_accepted' => true,
        'age_confirmed' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('terms_accepted');
});

it('rejects registration without privacy acceptance', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'No Privacy',
        'email' => 'nopriv_' . Str::random(8) . '@example.com',
        'password' => 'TestPass123',
        'terms_accepted' => true,
        'privacy_accepted' => false,
        'age_confirmed' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('privacy_accepted');
});

it('rejects registration without age confirmation', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Underage',
        'email' => 'under_' . Str::random(8) . '@example.com',
        'password' => 'TestPass123',
        'terms_accepted' => true,
        'privacy_accepted' => true,
        'age_confirmed' => false,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('age_confirmed');
});

it('records acceptance version matching published document', function () {
    $doc = LegalDocument::updateOrCreate(
        ['type' => 'terms_conditions', 'version' => 'test_3.0'],
        ['title' => 'Terms v3', 'content' => '<h2>Terms v3</h2>', 'is_published' => true, 'published_at' => now()->addSecond()]
    );

    LegalDocument::updateOrCreate(
        ['type' => 'privacy_policy', 'version' => 'test_privacy_2.0'],
        ['title' => 'Privacy', 'content' => '<h2>Privacy</h2>', 'is_published' => true, 'published_at' => now()->addSecond()]
    );

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Version Check',
        'email' => 'verchk_' . Str::random(8) . '@example.com',
        'password' => 'TestPass123',
        'terms_accepted' => true,
        'privacy_accepted' => true,
        'age_confirmed' => true,
    ]);

    $response->assertOk();

    $userId = $response->json('data.id');
    $acceptance = LegalDocumentAcceptance::where('user_id', $userId)
        ->where('document_type', 'terms_conditions')
        ->first();

    expect($acceptance->document_version)->toBe('test_3.0');
    expect($acceptance->legal_document_id)->toBe($doc->id);
});

/*
|--------------------------------------------------------------------------
| Public Page Tests
|--------------------------------------------------------------------------
*/

it('serves public deletion page without auth', function () {
    $response = $this->get('/delete-account');
    $response->assertOk();
    $response->assertSee('Delete Your Oripori Account');
    $response->assertSee('cannot be undone');
    $response->assertSee('What Data Is Deleted');
    $response->assertSee('What Is Retained');
});

it('serves privacy policy page', function () {
    DB::table('legal_document_types')->updateOrInsert(['slug' => 'privacy_policy'], ['label' => 'Privacy Policy', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $response = $this->get('/legal/privacy_policy');
    $response->assertOk();
});

it('serves terms page', function () {
    DB::table('legal_document_types')->updateOrInsert(['slug' => 'terms_conditions'], ['label' => 'Terms of Use', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $response = $this->get('/legal/terms_conditions');
    $response->assertOk();
});

/*
|--------------------------------------------------------------------------
| Security Tests
|--------------------------------------------------------------------------
*/

it('prevents deletion of another users account', function () {
    $user1 = createTestUser();
    $user2 = createTestUser();
    $token = createUserToken($user1);

    $response = $this->deleteJson('/api/v1/users/me', [
        'confirmation' => $user2->email,
    ], [
        'Authorization' => 'Bearer ' . $token,
    ]);

    $response->assertStatus(422);
    expect(User::find($user2->id))->not->toBeNull();
});
