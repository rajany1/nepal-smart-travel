<?php

use App\Models\Role;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportSatisfaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Admin Support command center
|--------------------------------------------------------------------------
|
| Pins the enhanced Support section: KPIs/analytics and tabs render, the
| queue filters by status/category/priority/assignee/search, sorting and
| pagination work, the polling endpoint returns server-rendered rows, the
| conversation workspace renders messages/actions/CSAT, every conversation
| action takes effect, load-earlier polling works, and regular users cannot
| reach the support tools.
|
| Fixtures use the live DB (no RefreshDatabase in this project) and are
| removed in afterEach, including messages/system events and CSAT rows
| created by the actions under test.
|
*/

beforeEach(function () {
    $GLOBALS['asp_user_ids'] = [];
    $GLOBALS['asp_conversation_ids'] = [];
});

afterEach(function () {
    $conversationIds = $GLOBALS['asp_conversation_ids'] ?? [];
    $userIds = $GLOBALS['asp_user_ids'] ?? [];

    if ($conversationIds !== []) {
        DB::table('support_satisfactions')->whereIn('support_conversation_id', $conversationIds)->delete();
        DB::table('support_messages')->whereIn('support_conversation_id', $conversationIds)->delete();
        DB::table('support_conversations')->whereIn('id', $conversationIds)->delete();
    }

    if ($userIds !== []) {
        DB::table('support_satisfactions')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }

    $GLOBALS['asp_user_ids'] = [];
    $GLOBALS['asp_conversation_ids'] = [];
});

function aspUser(string $role = 'admin', array $overrides = []): User
{
    $user = User::create(array_merge([
        'name' => 'ASP '.ucfirst($role).' '.Str::random(6),
        'email' => 'asp_'.Str::random(12).'@example.com',
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
    $GLOBALS['asp_user_ids'][] = $user->id;

    return $user->fresh();
}

function aspConversation(User $requester, array $overrides = []): SupportConversation
{
    $createdAt = $overrides['created_at'] ?? now();
    $lastReplyAt = $overrides['last_reply_at'] ?? $createdAt;
    unset($overrides['created_at'], $overrides['last_reply_at']);

    $conversation = SupportConversation::create(array_merge([
        'user_id' => $requester->id,
        'subject' => 'ASP conversation '.Str::random(8),
        'category' => 'general',
        'priority' => 'normal',
        'status' => 'open',
        'assigned_to' => null,
        'ai_handled' => false,
        'last_reply_at' => $lastReplyAt,
        'message_count' => 0,
    ], $overrides));

    DB::table('support_conversations')->where('id', $conversation->id)->update([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
        'last_reply_at' => $lastReplyAt,
    ]);

    $GLOBALS['asp_conversation_ids'][] = $conversation->id;

    return $conversation->fresh();
}

function aspMessage(SupportConversation $conversation, User $requester, array $overrides = []): SupportMessage
{
    $createdAt = $overrides['created_at'] ?? now();
    unset($overrides['created_at']);

    $message = SupportMessage::create(array_merge([
        'support_conversation_id' => $conversation->id,
        'user_id' => $requester->id,
        'sender_type' => 'user',
        'content' => 'ASP message '.Str::random(8),
        'is_internal_note' => false,
    ], $overrides));

    DB::table('support_messages')->where('id', $message->id)->update([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $conversation->update([
        'message_count' => $conversation->messages()->count(),
        'last_reply_at' => $createdAt,
    ]);

    return $message->fresh();
}

it('renders the support command center with KPIs, analytics and tabs', function () {
    $admin = aspUser('admin');
    $requester = aspUser('user', ['name' => 'ASP Requester Alpha']);

    $open = aspConversation($requester, [
        'subject' => 'ASPSUPPORT open urgent payment',
        'status' => 'open',
        'priority' => 'urgent',
        'category' => 'payment',
        'created_at' => now()->subHours(2),
    ]);
    aspMessage($open, $requester, ['content' => 'ASPSUPPORT opening message']);

    $ai = aspConversation($requester, [
        'subject' => 'ASPSUPPORT ai handling booking',
        'status' => 'ai_handling',
        'priority' => 'high',
        'category' => 'booking',
        'assigned_to' => $admin->id,
        'ai_handled' => true,
        'ai_confidence' => 0.87,
        'created_at' => now()->subDays(2),
    ]);
    aspMessage($ai, $requester, ['content' => 'ASPSUPPORT ai message']);

    $resolved = aspConversation($requester, [
        'subject' => 'ASPSUPPORT resolved general',
        'status' => 'resolved',
        'priority' => 'low',
        'category' => 'general',
        'assigned_to' => $admin->id,
        'created_at' => now()->subDays(5),
    ]);
    aspMessage($resolved, $requester, ['content' => 'ASPSUPPORT resolved message']);
    SupportSatisfaction::create([
        'support_conversation_id' => $resolved->id,
        'user_id' => $requester->id,
        'rating' => 5,
        'comment' => 'ASPSUPPORT great service',
    ]);

    $closed = aspConversation($requester, [
        'subject' => 'ASPSUPPORT closed account',
        'status' => 'closed',
        'priority' => 'normal',
        'category' => 'account',
        'assigned_to' => $admin->id,
        'created_at' => now()->subDays(10),
    ]);
    SupportSatisfaction::create([
        'support_conversation_id' => $closed->id,
        'user_id' => $requester->id,
        'rating' => 2,
        'comment' => 'ASPSUPPORT slow answer',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPSUPPORT']));

    $response->assertOk()
        ->assertSee('Support command center', false)
        ->assertSee('Active queue', false)
        ->assertSee('Awaiting human', false)
        ->assertSee('Unassigned', false)
        ->assertSee('Satisfaction', false)
        ->assertSee('Inbox activity', false)
        ->assertSee('Breakdown', false)
        ->assertSee('AI coverage', false)
        ->assertSee('Team coverage', false)
        ->assertSee('Resolution rate', false)
        ->assertSee('Priority pressure', false)
        ->assertSee('Conversation queue', false)
        ->assertSee('ASPSUPPORT open urgent payment', false)
        ->assertSee('ASPSUPPORT ai handling booking', false)
        ->assertSee('supportTrendChart', false)
        ->assertSee('supportFilters', false)
        ->assertSee('supportQueueBody', false);

    $insights = $response->viewData('insights');
    $statusTabs = $response->viewData('statusTabs');

    expect($insights['kpis'])->toHaveCount(4)
        ->and($insights['trend']['labels'])->toHaveCount(30)
        ->and($insights['trend']['conversations'])->toHaveCount(30)
        ->and($insights['trend']['replies'])->toHaveCount(30)
        ->and($insights['statuses'])->toHaveCount(6)
        ->and($insights['priorities'])->toHaveCount(4)
        ->and($insights['satisfaction'])->toHaveCount(5)
        ->and($insights['coverage']['reviewed'])->toBeGreaterThanOrEqual(2)
        ->and($insights['coverage']['avg_rating'])->not->toBeNull()
        ->and($statusTabs)->toHaveCount(8)
        ->and($response->viewData('conversations')->total())->toBe(2)
        ->and($response->viewData('filters')['search'])->toBe('ASPSUPPORT');
});

it('filters the queue by status, category, priority, assignee and search', function () {
    $admin = aspUser('admin');
    $requester = aspUser('user', ['name' => 'ASP Filter Requester']);

    $open = aspConversation($requester, [
        'subject' => 'ASPFILTER open payment',
        'status' => 'open',
        'priority' => 'urgent',
        'category' => 'payment',
        'assigned_to' => null,
    ]);
    $ai = aspConversation($requester, [
        'subject' => 'ASPFILTER ai booking',
        'status' => 'ai_handling',
        'priority' => 'high',
        'category' => 'booking',
        'assigned_to' => $admin->id,
    ]);
    $awaiting = aspConversation($requester, [
        'subject' => 'ASPFILTER awaiting account',
        'status' => 'awaiting_human',
        'priority' => 'normal',
        'category' => 'account',
        'assigned_to' => null,
    ]);
    $resolved = aspConversation($requester, [
        'subject' => 'ASPFILTER resolved general',
        'status' => 'resolved',
        'priority' => 'low',
        'category' => 'general',
        'assigned_to' => $admin->id,
    ]);

    $all = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPFILTER', 'status' => 'all']));
    expect($all->viewData('conversations')->pluck('id')->all())
        ->toEqualCanonicalizing([$open->id, $ai->id, $awaiting->id, $resolved->id]);

    $openOnly = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPFILTER', 'status' => 'open']));
    expect($openOnly->viewData('conversations')->pluck('id')->all())->toBe([$open->id]);

    $payment = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPFILTER', 'category' => 'payment']));
    expect($payment->viewData('conversations')->pluck('id')->all())->toBe([$open->id]);

    $high = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPFILTER', 'priority' => 'high']));
    expect($high->viewData('conversations')->pluck('id')->all())->toBe([$ai->id]);

    $unassigned = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPFILTER', 'assigned_to' => 'unassigned']));
    expect($unassigned->viewData('conversations')->pluck('id')->all())
        ->toEqualCanonicalizing([$open->id, $awaiting->id]);

    $assigned = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPFILTER', 'assigned_to' => $admin->id]));
    expect($assigned->viewData('conversations')->pluck('id')->all())->toBe([$ai->id]);

    $activeSearch = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPFILTER']));
    expect($activeSearch->viewData('conversations')->total())->toBe(3);

    $byEmail = $this->actingAs($admin)->get(route('admin.support', [
        'search' => $requester->email,
        'status' => 'all',
    ]));
    expect($byEmail->viewData('conversations')->total())->toBe(4);

    // Unknown filter values fall back to the active queue instead of erroring.
    $invalid = $this->actingAs($admin)->get(route('admin.support', [
        'search' => 'ASPFILTER',
        'status' => 'bogus',
        'category' => 'bogus',
        'priority' => 'bogus',
    ]));
    $invalid->assertOk();
    expect($invalid->viewData('conversations')->total())->toBe(3)
        ->and($invalid->viewData('filters'))->toMatchArray([
            'status' => null,
            'category' => null,
            'priority' => null,
        ]);
});

it('sorts the queue and honors per-page pagination', function () {
    $admin = aspUser('admin');
    $requester = aspUser('user');

    $urgent = aspConversation($requester, [
        'subject' => 'ASPSORT urgent oldest',
        'priority' => 'urgent',
        'created_at' => now()->subDays(5),
        'last_reply_at' => now()->subDays(5),
    ]);
    $normal = aspConversation($requester, [
        'subject' => 'ASPSORT normal newest',
        'priority' => 'normal',
        'created_at' => now()->subHours(2),
        'last_reply_at' => now()->subHours(2),
    ]);
    $high = aspConversation($requester, [
        'subject' => 'ASPSORT high middle',
        'priority' => 'high',
        'created_at' => now()->subDays(2),
        'last_reply_at' => now()->subDays(2),
    ]);

    for ($i = 0; $i < 3; $i++) {
        aspMessage($urgent, $requester, ['content' => 'ASPSORT urgent message '.$i]);
    }
    aspMessage($normal, $requester, ['content' => 'ASPSORT normal message']);

    for ($i = 1; $i <= 13; $i++) {
        $filler = aspConversation($requester, [
            'subject' => 'ASPSORT filler '.$i,
            'priority' => 'low',
            'created_at' => now()->subDays(20 + $i),
            'last_reply_at' => now()->subDays(20 + $i),
        ]);
    }
    $oldestFiller = $filler;

    $priority = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPSORT', 'sort' => 'priority']));
    expect($priority->viewData('conversations')->first()->id)->toBe($urgent->id);

    $oldest = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPSORT', 'sort' => 'oldest']));
    expect($oldest->viewData('conversations')->first()->id)->toBe($oldestFiller->id);

    $newest = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPSORT', 'sort' => 'newest']));
    expect($newest->viewData('conversations')->first()->id)->toBe($normal->id);

    $messages = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPSORT', 'sort' => 'messages']));
    expect($messages->viewData('conversations')->first()->id)->toBe($urgent->id);

    $invalidSort = $this->actingAs($admin)->get(route('admin.support', ['search' => 'ASPSORT', 'sort' => 'bogus']));
    expect($invalidSort->viewData('sort'))->toBe('priority')
        ->and($invalidSort->viewData('conversations')->first()->id)->toBe($urgent->id);

    $paged = $this->actingAs($admin)->get(route('admin.support', [
        'search' => 'ASPSORT',
        'per_page' => 15,
    ]));
    $paginator = $paged->viewData('conversations');
    expect($paginator->perPage())->toBe(15)
        ->and($paginator->total())->toBe(16)
        ->and($paginator->lastPage())->toBe(2)
        ->and($paginator->url(2))->toContain('search=ASPSORT')
        ->and($paginator->url(2))->toContain('per_page=15');

    $invalid = $this->actingAs($admin)->get(route('admin.support', [
        'search' => 'ASPSORT',
        'per_page' => 5,
    ]));
    expect($invalid->viewData('conversations')->perPage())->toBe(20);
});

it('returns server-rendered rows and stats from the inbox polling endpoint', function () {
    $admin = aspUser('admin');
    $requester = aspUser('user');

    $urgent = aspConversation($requester, [
        'subject' => 'ASPPOLL urgent open',
        'status' => 'open',
        'priority' => 'urgent',
        'assigned_to' => null,
    ]);
    $awaiting = aspConversation($requester, [
        'subject' => 'ASPPOLL awaiting human',
        'status' => 'awaiting_human',
        'priority' => 'normal',
        'assigned_to' => null,
    ]);
    $resolved = aspConversation($requester, [
        'subject' => 'ASPPOLL resolved closed',
        'status' => 'resolved',
        'priority' => 'low',
        'assigned_to' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->getJson(route('admin.support.poll-inbox', [
        'search' => 'ASPPOLL',
        'status' => 'all',
    ]));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('results_total', 3)
        ->assertJsonStructure([
            'success',
            'stats',
            'rows_html',
            'pagination_html',
            'results_total',
            'generated_at',
        ])
        ->assertJsonMissingPath('conversations');

    $payload = $response->json();
    expect($payload['rows_html'])->toContain('ASPPOLL urgent open')
        ->and($payload['rows_html'])->toContain('ASPPOLL awaiting human')
        ->and($payload['rows_html'])->toContain('ASPPOLL resolved closed')
        ->and($payload['pagination_html'])->toBeString()
        ->and($payload['stats']['total'])->toBeGreaterThanOrEqual(3);

    $filtered = $this->actingAs($admin)->getJson(route('admin.support.poll-inbox', [
        'search' => 'ASPPOLL',
        'status' => 'all',
        'priority' => 'urgent',
    ]));
    $filtered->assertOk()->assertJsonPath('results_total', 1);
    expect($filtered->json('rows_html'))->toContain('ASPPOLL urgent open')
        ->and($filtered->json('rows_html'))->not->toContain('ASPPOLL awaiting human');

    $unassigned = $this->actingAs($admin)->getJson(route('admin.support.poll-inbox', [
        'search' => 'ASPPOLL',
        'status' => 'all',
        'assigned_to' => 'unassigned',
    ]));
    $unassigned->assertOk()->assertJsonPath('results_total', 2);
    expect($unassigned->json('rows_html'))->toContain('ASPPOLL urgent open')
        ->and($unassigned->json('rows_html'))->toContain('ASPPOLL awaiting human')
        ->and($unassigned->json('rows_html'))->not->toContain('ASPPOLL resolved closed');
});

it('renders the conversation workspace with messages, actions and CSAT', function () {
    $admin = aspUser('admin');
    $requester = aspUser('user', ['name' => 'ASP Conversation Requester']);

    $conversation = aspConversation($requester, [
        'subject' => 'ASPCONVERSATION resolved request',
        'status' => 'resolved',
        'priority' => 'low',
        'category' => 'general',
        'assigned_to' => $admin->id,
    ]);
    aspMessage($conversation, $requester, ['content' => 'ASPCONVERSATION first user message']);
    aspMessage($conversation, $admin, [
        'sender_type' => 'support',
        'content' => 'ASPCONVERSATION staff reply',
    ]);
    SupportSatisfaction::create([
        'support_conversation_id' => $conversation->id,
        'user_id' => $requester->id,
        'rating' => 5,
        'comment' => 'ASPCONVERSATION excellent support',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.support.show', $conversation));

    $response->assertOk()
        ->assertSee('ASPCONVERSATION resolved request', false)
        ->assertSee('ASP Conversation Requester', false)
        ->assertSee($requester->email, false)
        ->assertSee('ASPCONVERSATION first user message', false)
        ->assertSee('ASPCONVERSATION staff reply', false)
        ->assertSee('Conversation controls', false)
        ->assertSee('Triage', false)
        ->assertSee('Requester', false)
        ->assertSee('Satisfaction review', false)
        ->assertSee('Very satisfied', false)
        ->assertSee('ASPCONVERSATION excellent support', false)
        ->assertSee('Reply to', false)
        ->assertSee('Internal note', false)
        ->assertSee('Close conversation', false)
        ->assertSee('Live updates', false)
        ->assertSee('Conversation #'.$conversation->id, false);

    expect($response->viewData('conversation')->id)->toBe($conversation->id)
        ->and($response->viewData('totalMessages'))->toBe(2)
        ->and($response->viewData('hasMoreMessages'))->toBeFalse();

    // Closed conversations replace the composer with a reopen action.
    $conversation->update(['status' => 'closed']);
    $closed = $this->actingAs($admin)->get(route('admin.support.show', $conversation));
    $closed->assertOk()
        ->assertSee('This conversation is closed', false)
        ->assertSee('Reopen conversation', false)
        ->assertDontSee('id="reply-form"', false);

    $this->actingAs($admin)->get(route('admin.support.show', 999999999))->assertNotFound();
});

it('supports replies, assignment, takeover, triage, resolution and reopen', function () {
    $admin = aspUser('admin');
    $otherStaff = aspUser('admin');
    $requester = aspUser('user');

    $conversation = aspConversation($requester, [
        'subject' => 'ASPACTION lifecycle',
        'status' => 'open',
        'assigned_to' => null,
    ]);
    aspMessage($conversation, $requester, ['content' => 'ASPACTION opening message']);

    $showUrl = route('admin.support.show', $conversation);

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.reply', $conversation), ['message' => 'ASPACTION staff reply'])
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');

    $this->assertDatabaseHas('support_messages', [
        'support_conversation_id' => $conversation->id,
        'sender_type' => 'support',
        'content' => 'ASPACTION staff reply',
    ]);
    $conversation->refresh();
    expect($conversation->assigned_to)->toBe($admin->id)
        ->and($conversation->status)->toBe(SupportConversation::STATUS_IN_PROGRESS)
        ->and($conversation->message_count)->toBe(2);

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.assign', $conversation), ['assigned_to' => $otherStaff->id])
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');
    expect($conversation->fresh()->assigned_to)->toBe($otherStaff->id);

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.takeover', $conversation))
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');
    $conversation->refresh();
    expect($conversation->assigned_to)->toBe($admin->id)
        ->and($conversation->status)->toBe(SupportConversation::STATUS_IN_PROGRESS);
    $this->assertDatabaseHas('support_messages', [
        'support_conversation_id' => $conversation->id,
        'sender_type' => 'system',
    ]);

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.category', $conversation), ['category' => 'account'])
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');
    expect($conversation->fresh()->category)->toBe('account');

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.priority', $conversation), ['priority' => 'urgent'])
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');
    expect($conversation->fresh()->priority)->toBe('urgent');

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.category', $conversation), ['category' => 'not-a-category'])
        ->assertSessionHasErrors('category');
    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.priority', $conversation), ['priority' => 'not-a-priority'])
        ->assertSessionHasErrors('priority');

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.resolve', $conversation))
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');
    expect($conversation->fresh()->status)->toBe(SupportConversation::STATUS_RESOLVED);

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.close', $conversation))
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');
    expect($conversation->fresh()->status)->toBe(SupportConversation::STATUS_CLOSED);

    $this->actingAs($admin)
        ->from($showUrl)
        ->post(route('admin.support.reopen', $conversation))
        ->assertRedirect($showUrl)
        ->assertSessionHas('success');
    expect($conversation->fresh()->status)->toBe(SupportConversation::STATUS_OPEN);

    $aiConversation = aspConversation($requester, [
        'subject' => 'ASPACTION ai handling',
        'status' => 'ai_handling',
        'ai_handled' => true,
    ]);
    $this->actingAs($admin)
        ->from(route('admin.support.show', $aiConversation))
        ->post(route('admin.support.stop-ai', $aiConversation))
        ->assertSessionHas('success');
    expect($aiConversation->fresh()->status)->toBe(SupportConversation::STATUS_AWAITING_HUMAN);
    $this->assertDatabaseHas('support_messages', [
        'support_conversation_id' => $aiConversation->id,
        'sender_type' => 'system',
        'content' => 'AI responses paused. Awaiting human support.',
    ]);

    $escalate = aspConversation($requester, [
        'subject' => 'ASPACTION escalate',
        'status' => 'ai_handling',
        'assigned_to' => $admin->id,
    ]);
    $this->actingAs($admin)
        ->from(route('admin.support.show', $escalate))
        ->post(route('admin.support.escalate', $escalate))
        ->assertSessionHas('success');
    $escalate->refresh();
    expect($escalate->status)->toBe(SupportConversation::STATUS_AWAITING_HUMAN)
        ->and($escalate->assigned_to)->toBeNull();
    $this->assertDatabaseHas('support_messages', [
        'support_conversation_id' => $escalate->id,
        'sender_type' => 'system',
        'content' => 'Escalated to human support: Manually escalated by staff.',
    ]);
});

it('returns new and earlier messages from the conversation polling endpoint', function () {
    $admin = aspUser('admin');
    $requester = aspUser('user');

    $conversation = aspConversation($requester, ['subject' => 'ASPPAGING conversation']);
    $messages = [];
    for ($i = 1; $i <= 55; $i++) {
        $messages[] = aspMessage($conversation, $requester, [
            'content' => 'ASPPAGING message '.$i,
            'created_at' => now()->subMinutes(60 - $i),
        ]);
    }
    $conversation->refresh();
    expect($conversation->message_count)->toBe(55);

    $show = $this->actingAs($admin)->get(route('admin.support.show', $conversation));
    $show->assertOk();
    expect($show->viewData('conversation')->messages)->toHaveCount(50)
        ->and($show->viewData('totalMessages'))->toBe(55)
        ->and($show->viewData('hasMoreMessages'))->toBeTrue();

    $since = $this->actingAs($admin)->getJson(route('admin.support.poll-messages', [
        'id' => $conversation->id,
        'since_id' => $messages[49]->id,
    ]));
    $since->assertOk()->assertJsonPath('success', true);
    expect($since->json('messages'))->toHaveCount(5)
        ->and($since->json('messages.0.id'))->toBe($messages[50]->id)
        ->and($since->json('messages.4.id'))->toBe($messages[54]->id);

    $earlier = $this->actingAs($admin)->getJson(route('admin.support.poll-messages', [
        'id' => $conversation->id,
        'before_message_id' => $messages[5]->id,
        'limit' => 50,
    ]));
    $earlier->assertOk();
    expect($earlier->json('messages'))->toHaveCount(5)
        ->and($earlier->json('messages.0.id'))->toBe($messages[0]->id)
        ->and($earlier->json('has_more'))->toBeFalse();

    $limited = $this->actingAs($admin)->getJson(route('admin.support.poll-messages', [
        'id' => $conversation->id,
        'before_message_id' => $messages[54]->id,
        'limit' => 10,
    ]));
    $limited->assertOk();
    expect($limited->json('messages'))->toHaveCount(10)
        ->and($limited->json('messages.0.id'))->toBe($messages[44]->id)
        ->and($limited->json('messages.9.id'))->toBe($messages[53]->id)
        ->and($limited->json('has_more'))->toBeTrue();

    $empty = $this->actingAs($admin)->getJson(route('admin.support.poll-messages', [
        'id' => $conversation->id,
        'since_id' => $messages[54]->id,
    ]));
    $empty->assertOk();
    expect($empty->json('messages'))->toHaveCount(0);
});

it('denies support access to regular users while allowing moderators', function () {
    $regular = aspUser('user');
    $moderator = aspUser('moderator');
    $admin = aspUser('admin');
    $requester = aspUser('user');

    $conversation = aspConversation($requester, ['subject' => 'ASPPERMISSION conversation']);
    aspMessage($conversation, $requester, ['content' => 'ASPPERMISSION opening']);

    $this->actingAs($regular)->get(route('admin.support'))->assertForbidden();
    $this->actingAs($regular)->get(route('admin.support.show', $conversation))->assertForbidden();
    $this->actingAs($regular)
        ->post(route('admin.support.reply', $conversation), ['message' => 'ASPPERMISSION nope'])
        ->assertForbidden();

    $this->actingAs($moderator)->get(route('admin.support'))->assertOk();
    $this->actingAs($moderator)->get(route('admin.support.show', $conversation))->assertOk();
    $this->actingAs($moderator)
        ->from(route('admin.support.show', $conversation))
        ->post(route('admin.support.reply', $conversation), ['message' => 'ASPPERMISSION moderator reply'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('support_messages', [
        'support_conversation_id' => $conversation->id,
        'sender_type' => 'support',
        'content' => 'ASPPERMISSION moderator reply',
    ]);

    $this->actingAs($admin)->get(route('admin.support'))->assertOk();
});
