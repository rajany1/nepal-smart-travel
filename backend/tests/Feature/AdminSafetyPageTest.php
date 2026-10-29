<?php

use App\Models\BadWord;
use App\Models\ContentViolation;
use App\Models\ModerationStrike;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Content Safety command center
|--------------------------------------------------------------------------
|
| Pins the enhanced moderation section: analytics/KPIs render, violation
| filters (search, content, severity, source, date, sort, per page) and
| offender filters work, the censor dictionary supports CRUD, manual
| warning/suspend/block/activate actions take effect, the offender history
| page renders, and regular users/moderators cannot reach the safety tools.
|
| Fixtures use the live DB (no RefreshDatabase in this project) and are
| removed in afterEach, including the account notices/strikes/violations
| created by the strike actions.
|
*/

beforeEach(function () {
    $GLOBALS['ast_user_ids'] = [];
});

afterEach(function () {
    $userIds = $GLOBALS['ast_user_ids'] ?? [];

    if ($userIds !== []) {
        DB::table('alerts')
            ->where(function ($query) use ($userIds) {
                $query->whereIn('target_user_id', $userIds)
                    ->orWhereIn('created_by', $userIds);
            })
            ->delete();
        DB::table('moderation_strikes')->whereIn('user_id', $userIds)->delete();
        DB::table('content_violations')->whereIn('user_id', $userIds)->delete();
        DB::table('audit_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }

    DB::table('bad_words')->where('word', 'like', 'aptsafety%')->delete();

    $GLOBALS['ast_user_ids'] = [];
});

function astUser(string $role = 'admin', array $overrides = []): User
{
    $user = User::create(array_merge([
        'name' => 'AST '.ucfirst($role).' '.Str::random(6),
        'email' => 'ast_'.Str::random(12).'@example.com',
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
    ], $overrides));

    User::whereKey($user->id)->update(['role_id' => Role::where('name', $role)->value('id')]);
    $GLOBALS['ast_user_ids'][] = $user->id;

    return $user->fresh();
}

function astViolation(User $user, array $overrides = []): ContentViolation
{
    // created_at is not mass-assignable, so apply it after the insert; the
    // date-range/sort tests depend on the fixture timestamps.
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $violation = ContentViolation::create(array_merge([
        'user_id' => $user->id,
        'entity_type' => 'place_review',
        'entity_id' => 100,
        'field' => 'description',
        'original_text' => 'AST original content',
        'censored_text' => 'AST censored content',
        'found_words' => ['stupid'],
        'severity' => 'moderate',
        'action' => 'censored',
        'source' => 'live',
    ], $overrides));

    if ($createdAt !== null) {
        DB::table('content_violations')->where('id', $violation->id)->update(['created_at' => $createdAt]);
        $violation->refresh();
    }

    return $violation;
}

function astStrike(User $user, array $overrides = []): ModerationStrike
{
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $strike = ModerationStrike::create(array_merge([
        'user_id' => $user->id,
        'level' => 'warning',
        'reason' => 'AST strike fixture',
        'source_type' => 'manual',
        'issued_by' => null,
    ], $overrides));

    if ($createdAt !== null) {
        DB::table('moderation_strikes')->where('id', $strike->id)->update(['created_at' => $createdAt]);
        $strike->refresh();
    }

    return $strike;
}

it('renders the content safety command center with KPIs, analytics and tabs', function () {
    $admin = astUser('admin');
    $offender = astUser('user');

    astViolation($offender, [
        'original_text' => 'AST severe violation text',
        'censored_text' => 'AST severe violation text'.' (censored)',
        'severity' => 'severe',
        'source' => 'live',
        'entity_type' => 'place_review',
        'created_at' => now(),
    ]);
    astViolation($offender, [
        'original_text' => 'AST moderate violation text',
        'censored_text' => 'AST moderate violation text (censored)',
        'severity' => 'moderate',
        'source' => 'sweep',
        'entity_type' => 'report',
        'created_at' => now()->subDays(3),
    ]);
    astStrike($offender, [
        'level' => 'warning',
        'reason' => 'AST warning fixture',
        'issued_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.moderation'));

    $response->assertOk()
        ->assertSee('Content safety command center', false)
        ->assertSee('Safety events', false)
        ->assertSee('Severe incidents', false)
        ->assertSee('Accounts under watch', false)
        ->assertSee('Dictionary coverage', false)
        ->assertSee('Safety activity', false)
        ->assertSee('Breakdown', false)
        ->assertSee('Violation log', false)
        ->assertSee('AST severe violation text', false)
        ->assertSee('safetyPageData', false)
        ->assertSee('safetyTrendChart', false)
        ->assertSee('safetySeverityChart', false)
        ->assertSee('Dictionary', false);

    $insights = $response->viewData('insights');

    expect($insights['counts']['violations_total'])->toBeGreaterThanOrEqual(2)
        ->and($insights['counts']['violations_30d'])->toBeGreaterThanOrEqual(2)
        ->and($insights['counts']['severe_total'])->toBeGreaterThanOrEqual(1)
        ->and($insights['kpis'])->toHaveCount(4)
        ->and($insights['trend']['labels'])->toHaveCount(30)
        ->and($insights['trend']['violations'])->toHaveCount(30)
        ->and($insights['trend']['enforcement'])->toHaveCount(30)
        ->and($insights['severities'])->toHaveCount(3)
        ->and($insights['enforcement'])->toHaveCount(3)
        ->and($insights['thresholds']['window_days'])->toBe(30)
        ->and($response->viewData('canManageSafety'))->toBeTrue()
        ->and($response->viewData('violations')->total())->toBeGreaterThanOrEqual(2);
});

it('renders the tab fragment for ajax list swaps', function () {
    $admin = astUser('admin');
    $offender = astUser('user');
    astViolation($offender, ['original_text' => 'AST fragment violation']);

    $response = $this->actingAs($admin)->get(
        route('admin.moderation', ['fragment' => 1]),
        ['X-Requested-With' => 'XMLHttpRequest']
    );

    $response->assertOk()
        ->assertSee('liveTable', false)
        ->assertSee('AST fragment violation', false)
        ->assertDontSee('<html', false)
        ->assertDontSee('id="sidebar"', false);
});

it('filters violations by search, content, severity, source and date', function () {
    $admin = astUser('admin');
    $alpha = astUser('user', ['name' => 'AST Violation Alpha']);
    $beta = astUser('user', ['name' => 'AST Violation Beta']);

    $today = astViolation($alpha, [
        'original_text' => 'APVTOKEN alpha entry',
        'entity_type' => 'place_review',
        'severity' => 'severe',
        'source' => 'live',
        'created_at' => now(),
    ]);
    $old = astViolation($beta, [
        'original_text' => 'APVTOKEN beta entry',
        'entity_type' => 'report',
        'severity' => 'mild',
        'source' => 'sweep',
        'created_at' => now()->subDays(40),
    ]);
    $recent = astViolation($alpha, [
        'original_text' => 'APVTOKEN gamma entry',
        'entity_type' => 'alert',
        'severity' => 'moderate',
        'source' => 'system',
        'created_at' => now()->subDays(10),
    ]);

    $all = $this->actingAs($admin)->get(route('admin.moderation', ['q' => 'APVTOKEN']));
    $all->assertOk()->assertSee('APVTOKEN alpha entry', false);
    expect($all->viewData('violations')->total())->toBe(3);

    $this->actingAs($admin)
        ->get(route('admin.moderation', ['q' => 'APVTOKEN', 'severity' => 'severe']))
        ->assertOk()
        ->assertSee('APVTOKEN alpha entry', false)
        ->assertDontSee('APVTOKEN beta entry', false)
        ->assertDontSee('APVTOKEN gamma entry', false);

    $this->actingAs($admin)
        ->get(route('admin.moderation', ['q' => 'APVTOKEN', 'entity' => 'report']))
        ->assertOk()
        ->assertSee('APVTOKEN beta entry', false)
        ->assertDontSee('APVTOKEN alpha entry', false)
        ->assertDontSee('APVTOKEN gamma entry', false);

    $this->actingAs($admin)
        ->get(route('admin.moderation', ['q' => 'APVTOKEN', 'source' => 'sweep']))
        ->assertOk()
        ->assertSee('APVTOKEN beta entry', false)
        ->assertDontSee('APVTOKEN alpha entry', false);

    $this->actingAs($admin)
        ->get(route('admin.moderation', ['q' => 'APVTOKEN', 'date_range' => 'today']))
        ->assertOk()
        ->assertSee('APVTOKEN alpha entry', false)
        ->assertDontSee('APVTOKEN beta entry', false)
        ->assertDontSee('APVTOKEN gamma entry', false);

    $last30 = $this->actingAs($admin)->get(route('admin.moderation', ['q' => 'APVTOKEN', 'date_range' => '30d']));
    expect($last30->viewData('violations')->pluck('id')->all())
        ->toEqualCanonicalizing([$today->id, $recent->id]);

    $last90 = $this->actingAs($admin)->get(route('admin.moderation', ['q' => 'APVTOKEN', 'date_range' => '90d']));
    expect($last90->viewData('violations')->pluck('id')->all())
        ->toEqualCanonicalizing([$today->id, $old->id, $recent->id]);

    // Searching by user name/email still works.
    $byName = $this->actingAs($admin)->get(route('admin.moderation', ['q' => 'AST Violation Alpha']));
    expect($byName->viewData('violations')->total())->toBe(2);
});

it('sorts violations and honors per page with query strings preserved', function () {
    $admin = astUser('admin');
    $offender = astUser('user');

    $severe = astViolation($offender, [
        'original_text' => 'APVPAGE severe',
        'severity' => 'severe',
        'created_at' => now()->subDays(2),
    ]);
    $mild = astViolation($offender, [
        'original_text' => 'APVPAGE mild',
        'severity' => 'mild',
        'created_at' => now(),
    ]);
    $moderate = astViolation($offender, [
        'original_text' => 'APVPAGE moderate',
        'severity' => 'moderate',
        'created_at' => now()->subDay(),
    ]);

    $severity = $this->actingAs($admin)->get(route('admin.moderation', [
        'q' => 'APVPAGE',
        'sort' => 'severity',
    ]));
    expect($severity->viewData('violations')->pluck('id')->all())
        ->toBe([$severe->id, $moderate->id, $mild->id]);

    $oldest = $this->actingAs($admin)->get(route('admin.moderation', [
        'q' => 'APVPAGE',
        'sort' => 'oldest',
    ]));
    expect($oldest->viewData('violations')->pluck('id')->all())
        ->toBe([$severe->id, $moderate->id, $mild->id]);

    $newest = $this->actingAs($admin)->get(route('admin.moderation', [
        'q' => 'APVPAGE',
        'sort' => 'newest',
    ]));
    expect($newest->viewData('violations')->pluck('id')->all())
        ->toBe([$mild->id, $moderate->id, $severe->id]);

    $paged = $this->actingAs($admin)->get(route('admin.moderation', [
        'q' => 'APVPAGE',
        'per_page' => 15,
    ]));
    expect($paged->viewData('violations')->perPage())->toBe(15)
        ->and($paged->viewData('violations')->url(2))->toContain('q=APVPAGE')
        ->and($paged->viewData('violations')->url(2))->toContain('per_page=15');

    $invalid = $this->actingAs($admin)->get(route('admin.moderation', [
        'q' => 'APVPAGE',
        'per_page' => 999,
    ]));
    expect($invalid->viewData('violations')->perPage())->toBe(20);
});

it('filters and sorts the offender watchlist', function () {
    $admin = astUser('admin');
    $suspended = astUser('user', ['name' => 'AST Watch Suspended']);
    $active = astUser('user', ['name' => 'AST Watch Active']);

    astViolation($suspended, ['original_text' => 'AST watch suspended event', 'created_at' => now()]);
    astViolation($active, ['original_text' => 'AST watch active event', 'created_at' => now()->subDays(5)]);

    astStrike($suspended);
    astStrike($suspended);
    astStrike($active);

    DB::table('users')->where('id', $suspended->id)->update([
        'status' => 'suspended',
        'suspended_until' => now()->addDay(),
    ]);

    $all = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
    ]));
    $all->assertOk()->assertSee('Offender watchlist', false);
    expect($all->viewData('users')->total())->toBe(2);

    $suspendedOnly = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
        'status' => 'suspended',
    ]));
    expect($suspendedOnly->viewData('users')->pluck('id')->all())->toBe([$suspended->id]);

    $activeOnly = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
        'status' => 'active',
    ]));
    expect($activeOnly->viewData('users')->pluck('id')->all())->toBe([$active->id]);

    $byStrikes = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
        'sort' => 'strikes',
    ]));
    expect($byStrikes->viewData('users')->pluck('id')->all())
        ->toBe([$suspended->id, $active->id]);

    $byRecent = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
        'sort' => 'recent',
    ]));
    expect($byRecent->viewData('users')->pluck('id')->all())
        ->toBe([$suspended->id, $active->id]);

    $byName = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
        'sort' => 'name',
    ]));
    expect($byName->viewData('users')->pluck('id')->all())
        ->toBe([$active->id, $suspended->id]);

    $paged = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
        'per_page' => 50,
    ]));
    expect($paged->viewData('users')->perPage())->toBe(50);

    $invalid = $this->actingAs($admin)->get(route('admin.moderation', [
        'tab' => 'users',
        'q' => 'AST Watch',
        'per_page' => 3,
    ]));
    expect($invalid->viewData('users')->perPage())->toBe(20);
});

it('manages the censor dictionary', function () {
    $admin = astUser('admin');
    $wordsUrl = route('admin.moderation', ['tab' => 'words']);

    $this->actingAs($admin)
        ->from($wordsUrl)
        ->post(route('admin.moderation.words.store'), [
            'word' => 'AptSafetyZed',
            'severity' => 'severe',
        ])
        ->assertRedirect($wordsUrl)
        ->assertSessionHas('success');

    $word = BadWord::where('word', 'aptsafetyzed')->first();
    expect($word)->not->toBeNull()
        ->and($word->severity)->toBe('severe')
        ->and($word->active)->toBeTrue();

    $this->actingAs($admin)
        ->from($wordsUrl)
        ->post(route('admin.moderation.words.store'), [
            'word' => 'APTSAFETYZED',
            'severity' => 'mild',
        ])
        ->assertRedirect($wordsUrl)
        ->assertSessionHas('error');
    expect(BadWord::where('word', 'aptsafetyzed')->count())->toBe(1);

    $this->actingAs($admin)
        ->from($wordsUrl)
        ->post(route('admin.moderation.words.store'), [
            'word' => 'A',
            'severity' => 'mild',
        ])
        ->assertSessionHasErrors('word');

    $this->actingAs($admin)
        ->from($wordsUrl)
        ->post(route('admin.moderation.words.store'), [
            'word' => 'AptSafetyBadSeverity',
            'severity' => 'extreme',
        ])
        ->assertSessionHasErrors('severity');
    expect(BadWord::where('word', 'aptsafetybadseverity')->exists())->toBeFalse();

    $this->actingAs($admin)
        ->put(route('admin.moderation.words.update', $word), ['severity' => 'mild'])
        ->assertRedirect()
        ->assertSessionHas('success');
    expect($word->fresh()->severity)->toBe('mild');

    $this->actingAs($admin)
        ->put(route('admin.moderation.words.update', $word), ['active' => '0'])
        ->assertSessionHas('success');
    expect($word->fresh()->active)->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.moderation', ['tab' => 'words', 'q' => 'aptsafetyzed']))
        ->assertOk()
        ->assertSee('Censor dictionary', false)
        ->assertSee('aptsafetyzed', false)
        ->assertSee('Disabled', false);

    $this->actingAs($admin)
        ->delete(route('admin.moderation.words.destroy', $word))
        ->assertSessionHas('success');
    $this->assertDatabaseMissing('bad_words', ['id' => $word->id]);
});

it('issues warnings, suspensions and blocks, then reactivates accounts', function () {
    $admin = astUser('admin');

    $warned = astUser('user', ['name' => 'AST Warn Target']);
    $this->actingAs($admin)
        ->from(route('admin.moderation', ['tab' => 'users']))
        ->post(route('admin.moderation.strike', $warned), [
            'level' => 'warning',
            'reason' => 'AST warning reason',
        ])
        ->assertSessionHas('success', 'Warning issued to AST Warn Target.');

    $this->assertDatabaseHas('moderation_strikes', [
        'user_id' => $warned->id,
        'level' => 'warning',
        'reason' => 'AST warning reason',
        'issued_by' => $admin->id,
    ]);
    expect($warned->fresh()->status)->toBe('active');

    $suspended = astUser('user', ['name' => 'AST Suspend Target']);
    $this->actingAs($admin)
        ->post(route('admin.moderation.strike', $suspended), [
            'level' => 'suspend',
            'reason' => 'AST suspend reason',
        ])
        ->assertSessionHas('success', 'AST Suspend Target has been suspended.');

    $suspended->refresh();
    expect($suspended->status)->toBe('suspended')
        ->and($suspended->suspended_until)->not->toBeNull()
        ->and($suspended->suspended_until->isFuture())->toBeTrue();
    $this->assertDatabaseHas('moderation_strikes', [
        'user_id' => $suspended->id,
        'level' => 'suspend',
    ]);

    $blocked = astUser('user', ['name' => 'AST Block Target']);
    $this->actingAs($admin)
        ->post(route('admin.moderation.strike', $blocked), [
            'level' => 'block',
            'reason' => 'AST block reason',
        ])
        ->assertSessionHas('success', 'AST Block Target has been blocked.');

    $blocked->refresh();
    expect($blocked->status)->toBe('banned')
        ->and($blocked->suspended_until)->toBeNull();
    $this->assertDatabaseHas('moderation_strikes', [
        'user_id' => $blocked->id,
        'level' => 'block',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.moderation.activate', $blocked))
        ->assertSessionHas('success', 'AST Block Target has been reactivated.');
    expect($blocked->fresh()->status)->toBe('active')
        ->and($blocked->fresh()->suspended_until)->toBeNull();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.activated',
        'resource_type' => 'user',
        'resource_id' => $blocked->id,
        'description' => 'Activated AST Block Target (was banned).',
    ]);

    // A warning still escalates if the account is already at the suspension
    // threshold (ladder is not bypassed by manual strikes).
    $repeat = astUser('user', ['name' => 'AST Repeat Target']);
    astStrike($repeat);
    $this->actingAs($admin)
        ->post(route('admin.moderation.strike', $repeat), [
            'level' => 'warning',
            'reason' => 'AST second strike',
        ])
        ->assertSessionHas('success');
    expect($repeat->fresh()->status)->toBe('suspended');
});

it('guards against self-punishment, admin targets and invalid strikes', function () {
    $admin = astUser('admin');
    $otherAdmin = astUser('admin');

    $this->actingAs($admin)
        ->from(route('admin.moderation', ['tab' => 'users']))
        ->post(route('admin.moderation.strike', $admin), [
            'level' => 'block',
            'reason' => 'AST self block',
        ])
        ->assertSessionHas('error', 'You cannot punish your own account.');
    expect(ModerationStrike::where('user_id', $admin->id)->exists())->toBeFalse();

    $this->actingAs($admin)
        ->from(route('admin.moderation', ['tab' => 'users']))
        ->post(route('admin.moderation.strike', $otherAdmin), [
            'level' => 'warning',
            'reason' => 'AST admin target',
        ])
        ->assertSessionHas('error', 'Cannot apply a strike to an admin user.');
    expect(ModerationStrike::where('user_id', $otherAdmin->id)->exists())->toBeFalse();

    $target = astUser('user');
    $this->actingAs($admin)
        ->from(route('admin.moderation', ['tab' => 'users']))
        ->post(route('admin.moderation.strike', $target), [
            'level' => 'warning',
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');
    expect(ModerationStrike::where('user_id', $target->id)->exists())->toBeFalse();
});

it('renders the offender history with the escalation ladder and content log', function () {
    $admin = astUser('admin');
    $offender = astUser('user', ['name' => 'AST History Target']);

    astViolation($offender, [
        'original_text' => 'AST history original one',
        'censored_text' => 'AST history censored one',
        'severity' => 'severe',
    ]);
    astViolation($offender, [
        'original_text' => 'AST history original two',
        'censored_text' => 'AST history censored two',
        'severity' => 'mild',
    ]);
    astStrike($offender, ['level' => 'warning', 'reason' => 'AST history warning reason']);
    astStrike($offender, ['level' => 'suspend', 'reason' => 'AST history suspend reason']);

    $response = $this->actingAs($admin)->get(route('admin.moderation.users', $offender));

    $response->assertOk()
        ->assertSee('AST History Target', false)
        ->assertSee($offender->email, false)
        ->assertSee('Escalation ladder', false)
        ->assertSee('Why this user was punished', false)
        ->assertSee('What they posted', false)
        ->assertSee('AST history warning reason', false)
        ->assertSee('AST history suspend reason', false)
        ->assertSee('AST history original one', false)
        ->assertSee('AST history censored two', false)
        ->assertSee('Permanent block', false)
        ->assertSee('Warning', false)
        ->assertSee('Suspend', false);

    $safety = $response->viewData('safety');
    expect($safety['active_strikes'])->toBe(2)
        ->and($safety['next_action'])->toBe('Permanent block')
        ->and($response->viewData('totalViolations'))->toBe(2)
        ->and($response->viewData('canManageSafety'))->toBeTrue();
});

it('denies safety access to regular users and moderators', function () {
    $regular = astUser('user');
    $moderator = astUser('moderator');
    $admin = astUser('admin');

    $this->actingAs($regular)->get(route('admin.moderation'))->assertForbidden();
    $this->actingAs($moderator)->get(route('admin.moderation'))->assertForbidden();
    $this->actingAs($moderator)
        ->post(route('admin.moderation.words.store'), [
            'word' => 'aptsafetymoderator',
            'severity' => 'mild',
        ])
        ->assertForbidden();
    $this->actingAs($regular)
        ->post(route('admin.moderation.strike', $moderator), [
            'level' => 'warning',
            'reason' => 'AST no permission',
        ])
        ->assertForbidden();

    $this->actingAs($admin)->get(route('admin.moderation'))->assertOk();
    expect(BadWord::where('word', 'aptsafetymoderator')->exists())->toBeFalse();
});
