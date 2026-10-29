<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Alerts command center
|--------------------------------------------------------------------------
|
| Pins the enhanced alerts list: KPIs/analytics render, status/severity/type/
| search filters work, per-page is validated, moderators can compose/delete,
| and regular users cannot reach the admin area. Fixtures use the live DB and
| are removed in afterEach.
|
*/

beforeEach(function () {
    $GLOBALS['aat_user_ids'] = [];
});

afterEach(function () {
    DB::table('alerts')->where('title', 'like', 'AAT %')->delete();
    DB::table('alerts')->where('title', 'like', 'AAT-MOD %')->delete();

    $userIds = $GLOBALS['aat_user_ids'] ?? [];
    if ($userIds !== []) {
        DB::table('audit_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }

    $GLOBALS['aat_user_ids'] = [];
});

function aatUser(string $role = 'admin'): User
{
    $user = User::create([
        'name' => 'AAT '.ucfirst($role),
        'email' => 'aat_'.Str::random(12).'@example.com',
        'phone' => '98'.mt_rand(10000000, 99999999),
        'password' => bcrypt('TestPass123'),
        'uuid' => Str::uuid()->toString(),
        'status' => 'active',
        'is_active' => 1,
        'badges' => [],
        'expertise_regions' => [],
        'settings' => [],
        'total_xp' => 0,
        'current_level' => 1,
    ]);

    User::whereKey($user->id)->update(['role_id' => Role::where('name', $role)->value('id')]);
    $GLOBALS['aat_user_ids'][] = $user->id;

    return $user->fresh();
}

function aatAlert(array $overrides = []): int
{
    return DB::table('alerts')->insertGetId(array_merge([
        'uuid' => Str::uuid()->toString(),
        'title' => 'AAT alert '.Str::random(8),
        'description' => 'AAT alerts command center fixture.',
        'alert_type' => 'weather',
        'severity' => 'high',
        'affected_district' => 'Kathmandu',
        'expires_at' => now()->addDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
}

it('renders the alerts command center with global KPIs and alert rows', function () {
    $admin = aatUser('admin');
    $activeId = aatAlert(['title' => 'AAT active weather warning', 'severity' => 'high']);
    $expiredId = aatAlert([
        'title' => 'AAT expired flood notice',
        'alert_type' => 'flood',
        'severity' => 'critical',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.alerts'));

    $response->assertOk()
        ->assertSee('Alerts command center', false)
        ->assertSee('Active alerts', false)
        ->assertSee('Critical &amp; high', false)
        ->assertSee('Alert queue', false)
        ->assertSee('Compose an alert', false)
        ->assertSee('AAT active weather warning', false)
        ->assertSee('AAT expired flood notice', false)
        ->assertSee('Expired', false);

    $insights = $response->viewData('insights');
    expect($insights['counts']['all'])->toBeGreaterThanOrEqual(2)
        ->and($insights['counts']['severity'])->toHaveKeys(['critical', 'high', 'medium', 'low', 'info'])
        ->and($insights['trend']['labels'])->toHaveCount(30)
        ->and($insights['types'])->not->toBeEmpty()
        ->and($alerts = $response->viewData('alerts'))->not->toBeNull()
        ->and($alerts->total())->toBeGreaterThanOrEqual(2)
        ->and($activeId)->toBeInt()
        ->and($expiredId)->toBeInt();
});

it('filters alerts by status, severity, type, search and per page', function () {
    $admin = aatUser('admin');
    aatAlert(['title' => 'AAT active weather warning', 'severity' => 'high', 'alert_type' => 'weather']);
    aatAlert([
        'title' => 'AAT expired flood notice',
        'severity' => 'critical',
        'alert_type' => 'flood',
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.alerts', ['status' => 'expired']))
        ->assertOk()
        ->assertSee('AAT expired flood notice', false)
        ->assertDontSee('AAT active weather warning', false);

    $this->actingAs($admin)
        ->get(route('admin.alerts', [
            'status' => 'active',
            'severity' => 'high',
            'alert_type' => 'weather',
            'search' => 'AAT active',
            'per_page' => 25,
        ]))
        ->assertOk()
        ->assertSee('AAT active weather warning', false)
        ->assertDontSee('AAT expired flood notice', false);

    $response = $this->actingAs($admin)->get(route('admin.alerts', ['per_page' => 25]));
    expect($response->viewData('alerts')->perPage())->toBe(25);

    $invalid = $this->actingAs($admin)->get(route('admin.alerts', ['per_page' => 999]));
    expect($invalid->viewData('alerts')->perPage())->toBe(15);
});

it('lets an admin publish an alert through the composer', function () {
    $admin = aatUser('admin');

    $this->actingAs($admin)
        ->post(route('admin.alerts.create'), [
            'title' => 'AAT alert published from test',
            'description' => 'AAT alert description from the test composer.',
            'title_ne' => 'AAT परीक्षण चेतावनी',
            'description_ne' => 'AAT परीक्षण विवरण।',
            'alert_type' => 'emergency',
            'severity' => 'critical',
            'affected_district' => 'Lalitpur',
            'is_broadcast' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Alert created');

    $this->assertDatabaseHas('alerts', [
        'title' => 'AAT alert published from test',
        'title_ne' => 'AAT परीक्षण चेतावनी',
        'alert_type' => 'emergency',
        'severity' => 'critical',
        'affected_district' => 'Lalitpur',
        'created_by' => $admin->id,
        'is_broadcast' => 1,
    ]);
});

it('rejects an alert without a canonical title or description', function () {
    $admin = aatUser('admin');

    $this->actingAs($admin)
        ->post(route('admin.alerts.create'), [
            'title' => '',
            'description' => '',
            'alert_type' => 'weather',
            'severity' => 'medium',
            'affected_district' => 'Kathmandu',
        ])
        ->assertSessionHasErrors(['title', 'description']);
});

it('lets an admin delete an alert', function () {
    $admin = aatUser('admin');
    $alertId = aatAlert(['title' => 'AAT alert to delete']);

    $this->actingAs($admin)
        ->from(route('admin.alerts'))
        ->post(route('admin.alerts.delete', $alertId))
        ->assertRedirect(route('admin.alerts'))
        ->assertSessionHas('success', 'Alert deleted');

    $this->assertDatabaseMissing('alerts', ['id' => $alertId]);
});

it('lets a moderator open the command center and publish an alert', function () {
    $moderator = aatUser('moderator');
    $alertId = aatAlert(['title' => 'AAT alert visible to moderator']);

    $this->actingAs($moderator)
        ->get(route('admin.alerts'))
        ->assertOk()
        ->assertSee('Alerts command center', false)
        ->assertSee('AAT alert visible to moderator', false)
        ->assertSee('Delete alert #'.$alertId, false)
        ->assertSee('Publish alert', false);

    $this->actingAs($moderator)
        ->post(route('admin.alerts.create'), [
            'title' => 'AAT-MOD alert from moderator',
            'description' => 'AAT-MOD moderator publish test.',
            'alert_type' => 'system',
            'severity' => 'info',
            'affected_district' => 'Nepal',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('alerts', [
        'title' => 'AAT-MOD alert from moderator',
        'created_by' => $moderator->id,
        'severity' => 'info',
    ]);
});

it('blocks regular users from the alerts command center', function () {
    $user = aatUser('user');

    $this->actingAs($user)
        ->get(route('admin.alerts'))
        ->assertForbidden();
});
