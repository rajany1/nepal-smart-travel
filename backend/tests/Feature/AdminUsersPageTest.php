<?php

use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFraudProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Users command center
|--------------------------------------------------------------------------
|
| Pins the enhanced Users section: KPIs/analytics and tabs render, the
| directory filters by role/status/verification/risk/search, sorting and
| pagination work, the moderator permissions panel renders, every user
| management action takes effect, and access stays limited to staff with
| manage_users.
|
| Fixtures use the live DB (no RefreshDatabase in this project) and are
| removed in afterEach, including fraud profiles, reports and the audit
| entries created by the actions under test.
|
*/

beforeEach(function () {
    $GLOBALS['aup_user_ids'] = [];
    $GLOBALS['aup_fraud_ids'] = [];
    $GLOBALS['aup_report_ids'] = [];
});

afterEach(function () {
    $userIds = $GLOBALS['aup_user_ids'] ?? [];
    $fraudIds = $GLOBALS['aup_fraud_ids'] ?? [];
    $reportIds = $GLOBALS['aup_report_ids'] ?? [];

    if ($fraudIds !== []) {
        DB::table('user_fraud_profiles')->whereIn('id', $fraudIds)->delete();
    }

    if ($reportIds !== []) {
        DB::table('reports')->whereIn('id', $reportIds)->delete();
    }

    if ($userIds !== []) {
        DB::table('user_fraud_profiles')->whereIn('user_id', $userIds)->delete();
        DB::table('reports')->whereIn('user_id', $userIds)->delete();
        DB::table('audit_logs')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->orWhere(function ($resourceQuery) use ($userIds) {
                        $resourceQuery->where('resource_type', 'user')
                            ->whereIn('resource_id', $userIds);
                    });
            })
            ->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }

    $GLOBALS['aup_user_ids'] = [];
    $GLOBALS['aup_fraud_ids'] = [];
    $GLOBALS['aup_report_ids'] = [];
});

function aupUser(string $role = 'user', array $overrides = []): User
{
    $dbOnly = [];
    foreach (['status', 'is_active', 'total_xp', 'current_level'] as $key) {
        if (array_key_exists($key, $overrides)) {
            $dbOnly[$key] = $overrides[$key];
            unset($overrides[$key]);
        }
    }

    $user = User::create(array_merge([
        'name' => 'AUP '.ucfirst($role).' '.Str::random(6),
        'email' => 'aup_'.Str::random(12).'@example.com',
        'phone' => '98'.mt_rand(10000000, 99999999),
        'password' => bcrypt('TestPass123'),
        'uuid' => Str::uuid()->toString(),
        'badges' => [],
        'expertise_regions' => [],
        'settings' => [],
    ], $overrides));

    DB::table('users')->where('id', $user->id)->update(array_merge([
        'role_id' => Role::where('name', $role)->value('id'),
        'status' => 'active',
        'is_active' => 1,
        'total_xp' => 0,
        'current_level' => 1,
    ], $dbOnly));

    $GLOBALS['aup_user_ids'][] = $user->id;

    return $user->fresh();
}

function aupFraud(User $user, int $score, bool $suspicious = false): UserFraudProfile
{
    $profile = UserFraudProfile::create([
        'user_id' => $user->id,
        'fraud_score' => $score,
        'fraud_flags' => [],
        'is_suspicious' => $suspicious,
        'suspicious_reason' => $suspicious ? 'AUP suspicious fixture' : null,
    ]);

    $GLOBALS['aup_fraud_ids'][] = $profile->id;

    return $profile;
}

function aupReport(User $user, string $title): Report
{
    $report = Report::create([
        'uuid' => Str::uuid()->toString(),
        'user_id' => $user->id,
        'category_id' => ReportCategorie::query()->value('id') ?? 1,
        'title' => $title,
        'description' => 'AUP report fixture',
        'priority' => 'medium',
        'status' => 'pending',
        'source' => 'user',
        'is_active' => 1,
        'latitude' => 27.7172,
        'longitude' => 85.3240,
    ]);

    $GLOBALS['aup_report_ids'][] = $report->id;

    return $report;
}

it('renders the users command center with KPIs, analytics and tabs', function () {
    $admin = aupUser('admin');
    aupUser('user', ['name' => 'AUP Directory Target']);

    $response = $this->actingAs($admin)->get(route('admin.users', ['search' => 'AUP Directory Target']));

    $response->assertOk()
        ->assertSee('Users command center', false)
        ->assertSee('Total users', false)
        ->assertSee('Active accounts', false)
        ->assertSee('New accounts', false)
        ->assertSee('Under watch', false)
        ->assertSee('Signup activity', false)
        ->assertSee('Breakdown', false)
        ->assertSee('Email verified', false)
        ->assertSee('Phone on file', false)
        ->assertSee('Profile complete', false)
        ->assertSee('User directory', false)
        ->assertSee('Moderator permissions', false)
        ->assertSee('Edit roles', false)
        ->assertSee('usersTrendChart', false)
        ->assertSee('AUP Directory Target', false);

    $insights = $response->viewData('insights');

    expect($insights['kpis'])->toHaveCount(4)
        ->and($insights['trend']['labels'])->toHaveCount(30)
        ->and($insights['trend']['signups'])->toHaveCount(30)
        ->and($insights['trend']['verifications'])->toHaveCount(30)
        ->and($insights['roles'])->toHaveCount(5)
        ->and($insights['statuses'])->toHaveCount(3)
        ->and($insights['verification'])->toHaveCount(4)
        ->and($insights['risk'])->toHaveCount(4)
        ->and(array_sum(array_column($insights['roles'], 'value')))->toBe($insights['counts']['total']);
});

it('filters the directory by role, status, verification, risk and search', function () {
    $super = aupUser('super_admin');
    $adminUser = aupUser('admin', ['name' => 'AUP Admin Zeta']);
    $regular = aupUser('user', ['name' => 'AUP Regular Alpha', 'phone' => '9800000001']);
    $moderator = aupUser('moderator', ['name' => 'AUP Moderator Beta']);
    $business = aupUser('business', ['name' => 'AUP Business Gamma']);
    $suspicious = aupUser('user', ['name' => 'AUP Suspicious Delta', 'email_verified_at' => now()]);
    $medium = aupUser('user', ['name' => 'AUP Medium Epsilon']);
    $clean = aupUser('user', ['name' => 'AUP Clean Theta']);
    $profile = aupUser('user', ['name' => 'AUP Profile Iota', 'profile_completed' => true]);
    $noPhone = aupUser('user', ['name' => 'AUP NoPhone Kappa', 'phone' => null]);

    aupFraud($suspicious, 90, true);
    aupFraud($medium, 60, false);

    DB::table('users')->where('id', $regular->id)->update(['status' => 'suspended']);

    $ids = function (array $params) use ($super) {
        return $this->actingAs($super)
            ->get(route('admin.users', $params))
            ->assertOk()
            ->viewData('users')
            ->pluck('id')
            ->all();
    };

    expect($ids(['role' => 'user', 'search' => 'AUP']))
        ->toContain($regular->id)
        ->toContain($suspicious->id)
        ->toContain($medium->id)
        ->not->toContain($moderator->id)
        ->not->toContain($business->id)
        ->not->toContain($adminUser->id);

    expect($ids(['role' => 'moderator', 'search' => 'AUP']))
        ->toContain($moderator->id)
        ->not->toContain($regular->id);

    expect($ids(['role' => 'business', 'search' => 'AUP']))
        ->toContain($business->id)
        ->not->toContain($moderator->id);

    expect($ids(['role' => 'admin', 'search' => 'AUP']))
        ->toContain($adminUser->id)
        ->not->toContain($regular->id);

    expect($ids(['status' => 'suspended', 'search' => 'AUP']))
        ->toContain($regular->id)
        ->not->toContain($moderator->id);

    expect($ids(['status' => 'suspicious', 'search' => 'AUP']))
        ->toContain($suspicious->id)
        ->not->toContain($regular->id);

    expect($ids(['status' => 'active', 'search' => 'AUP']))
        ->toContain($suspicious->id)
        ->not->toContain($regular->id);

    expect($ids(['verification' => 'email_verified', 'search' => 'AUP']))
        ->toContain($suspicious->id)
        ->not->toContain($regular->id);

    expect($ids(['verification' => 'email_unverified', 'search' => 'AUP']))
        ->toContain($regular->id)
        ->not->toContain($suspicious->id);

    expect($ids(['verification' => 'phone', 'search' => 'AUP']))
        ->toContain($regular->id)
        ->not->toContain($noPhone->id);

    expect($ids(['verification' => 'profile', 'search' => 'AUP']))
        ->toContain($profile->id)
        ->not->toContain($regular->id);

    expect($ids(['risk' => 'watch', 'search' => 'AUP']))
        ->toContain($suspicious->id)
        ->not->toContain($medium->id);

    expect($ids(['risk' => 'high', 'search' => 'AUP']))
        ->toContain($suspicious->id)
        ->not->toContain($medium->id);

    expect($ids(['risk' => 'scored', 'search' => 'AUP']))
        ->toContain($suspicious->id)
        ->toContain($medium->id)
        ->not->toContain($clean->id);

    expect($ids(['risk' => 'clean', 'search' => 'AUP']))
        ->toContain($clean->id)
        ->not->toContain($suspicious->id)
        ->not->toContain($medium->id);

    expect($ids(['search' => $regular->email]))
        ->toContain($regular->id)
        ->not->toContain($moderator->id);

    expect($ids(['search' => $regular->phone]))
        ->toContain($regular->id)
        ->not->toContain($moderator->id);

    expect($ids(['search' => (string) $regular->id]))
        ->toContain($regular->id);

    $response = $this->actingAs($super)->get(route('admin.users', [
        'role' => 'not-a-role',
        'status' => 'not-a-status',
        'verification' => 'not-a-signal',
        'risk' => 'not-a-band',
        'sort' => 'not-a-sort',
        'per_page' => 999,
        'search' => 'AUP Regular Alpha',
    ]));

    $filters = $response->viewData('filters');

    expect($filters['role'])->toBe('all')
        ->and($filters['status'])->toBe('all')
        ->and($filters['verification'])->toBe('')
        ->and($filters['risk'])->toBe('')
        ->and($filters['per_page'])->toBe(15)
        ->and($response->viewData('sort'))->toBe('newest');
});

it('sorts and paginates the directory', function () {
    $super = aupUser('super_admin');
    $alpha = aupUser('user', ['name' => 'AUP Sort Alpha', 'total_xp' => 500]);
    $beta = aupUser('user', ['name' => 'AUP Sort Beta', 'total_xp' => 100]);
    $gamma = aupUser('user', ['name' => 'AUP Sort Gamma', 'total_xp' => 900]);

    aupReport($alpha, 'AUP report alpha one');
    aupReport($alpha, 'AUP report alpha two');
    aupReport($beta, 'AUP report beta one');

    aupFraud($alpha, 10);
    aupFraud($beta, 80);
    aupFraud($gamma, 50);

    for ($i = 0; $i < 16; $i++) {
        aupUser('user', ['name' => 'AUP Page '.$i]);
    }

    DB::table('users')->where('id', $alpha->id)->update(['created_at' => now()->subDays(3)]);
    DB::table('users')->where('id', $beta->id)->update(['created_at' => now()->subDays(2)]);
    DB::table('users')->where('id', $gamma->id)->update(['created_at' => now()->subDay()]);

    $firstId = function (string $sort) use ($super) {
        return $this->actingAs($super)
            ->get(route('admin.users', ['search' => 'AUP Sort', 'sort' => $sort]))
            ->assertOk()
            ->viewData('users')
            ->first()
            ->id;
    };

    expect($firstId('xp'))->toBe($gamma->id)
        ->and($firstId('reports'))->toBe($alpha->id)
        ->and($firstId('risk'))->toBe($beta->id)
        ->and($firstId('name'))->toBe($alpha->id)
        ->and($firstId('oldest'))->toBe($alpha->id)
        ->and($firstId('newest'))->toBe($gamma->id);

    $perPage = $this->actingAs($super)
        ->get(route('admin.users', ['search' => 'AUP Sort', 'per_page' => 20]))
        ->viewData('users');
    expect($perPage->perPage())->toBe(20);

    $fallback = $this->actingAs($super)
        ->get(route('admin.users', ['search' => 'AUP Sort', 'per_page' => 999]))
        ->viewData('users');
    expect($fallback->perPage())->toBe(15);

    $secondPage = $this->actingAs($super)
        ->get(route('admin.users', ['search' => 'AUP Page', 'per_page' => 15, 'page' => 2]))
        ->viewData('users');
    expect($secondPage->currentPage())->toBe(2)
        ->and($secondPage->count())->toBe(1);
});

it('performs every user management action and records an audit entry', function () {
    $super = aupUser('super_admin');
    $target = aupUser('user');
    $from = route('admin.users', ['search' => $target->name]);

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.toggle-status', $target->id))
        ->assertRedirect($from)
        ->assertSessionHas('success');
    expect($target->fresh()->status)->toBe('suspended');

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.toggle-status', $target->id))
        ->assertRedirect($from);
    expect($target->fresh()->status)->toBe('banned');

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.toggle-status', $target->id))
        ->assertRedirect($from);
    expect($target->fresh()->status)->toBe('active');

    $moderatorRoleId = Role::where('name', 'moderator')->value('id');

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.assign-role', $target->id), ['role_id' => $moderatorRoleId])
        ->assertRedirect($from)
        ->assertSessionHas('success');
    expect($target->fresh()->role->name)->toBe('moderator');

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.make-admin', $target->id))
        ->assertRedirect($from)
        ->assertSessionHas('success');
    expect($target->fresh()->role->name)->toBe('admin');

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.remove-admin', $target->id))
        ->assertRedirect($from)
        ->assertSessionHas('success');
    expect($target->fresh()->role->name)->toBe('user');

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.make-moderator', $target->id))
        ->assertRedirect($from)
        ->assertSessionHas('success');
    expect($target->fresh()->role->name)->toBe('moderator');

    $this->actingAs($super)->from($from)
        ->post(route('admin.users.remove-moderator', $target->id))
        ->assertRedirect($from)
        ->assertSessionHas('success');
    expect($target->fresh()->role->name)->toBe('user');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.toggle-status',
        'resource_type' => 'user',
        'resource_id' => $target->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.assign-role',
        'resource_type' => 'user',
        'resource_id' => $target->id,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.remove-moderator',
        'resource_type' => 'user',
        'resource_id' => $target->id,
    ]);

    $admin = aupUser('admin');
    $rolesForAdmin = $this->actingAs($admin)->get($from)->viewData('roles');
    expect($rolesForAdmin->pluck('name'))->not->toContain('super_admin');

    $this->actingAs($admin)->from($from)
        ->post(route('admin.users.assign-role', $target->id), [
            'role_id' => Role::where('name', 'super_admin')->value('id'),
        ])
        ->assertRedirect($from)
        ->assertSessionHas('error');
    expect($target->fresh()->role->name)->toBe('user');

    $before = $super->fresh()->status;
    $this->actingAs($super)->from($from)
        ->post(route('admin.users.toggle-status', $super->id))
        ->assertRedirect($from)
        ->assertSessionHas('error');
    expect($super->fresh()->status)->toBe($before);
});

it('denies the directory to regular users while allowing moderators', function () {
    $regular = aupUser('user');
    $moderator = aupUser('moderator');
    $target = aupUser('user');

    $this->actingAs($regular)->get(route('admin.users'))->assertForbidden();

    $this->actingAs($moderator)->get(route('admin.users'))
        ->assertOk()
        ->assertSee('Users command center', false)
        ->assertSee('Permissions are managed by an administrator.', false)
        ->assertDontSee('Edit roles', false)
        ->assertDontSee('assign-role', false);

    $this->actingAs($moderator)
        ->post(route('admin.users.assign-role', $target->id), [
            'role_id' => Role::where('name', 'moderator')->value('id'),
        ])
        ->assertForbidden();
});
