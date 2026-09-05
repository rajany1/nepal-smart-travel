@extends('admin.layout')

@section('title', 'Content Safety')

@section('content')
@php
    $severityColors = [
        'mild' => 'bg-slate-100 text-slate-600 border-slate-200',
        'moderate' => 'bg-amber-100 text-amber-700 border-amber-200',
        'severe' => 'bg-red-100 text-red-700 border-red-200',
    ];
    $entityLabels = [
        'place_review' => 'Place Review',
        'report' => 'Report',
        'report_comment' => 'Report Comment',
        'alert' => 'Alert',
        'place_correction' => 'Place Correction',
        'place' => 'Place',
        'reward_offer' => 'Reward Offer',
        'ad_campaign' => 'Ad Campaign',
        'user_bio' => 'User Bio',
    ];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">Content Safety</h2>
        <p class="text-sm text-slate-500 mt-1">Review AI agent â€” runs 24/7. Every censor, warning, suspension and block is recorded here.</p>
    </div>
    <div class="flex rounded-xl border border-slate-200 bg-white overflow-hidden text-sm">
        <a href="{{ route('admin.moderation', ['tab' => 'violations']) }}" class="px-4 py-2 {{ $tab !== 'users' ? 'bg-accent-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">Violations</a>
        <a href="{{ route('admin.moderation', ['tab' => 'users']) }}" class="px-4 py-2 {{ $tab === 'users' ? 'bg-accent-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">Users</a>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    @foreach([
        'violations_total' => ['Total Violations', 'text-slate-800'],
        'violations_today' => ['Today', 'text-slate-800'],
        'unique_users' => ['Users Affected', 'text-slate-800'],
        'suspended_now' => ['Suspended Now', 'text-amber-600'],
        'banned_now' => ['Blocked', 'text-red-600'],
    ] as $key => $label)
        <div class="bg-white rounded-2xl shadow p-4">
            <div class="text-2xl font-bold {{ $label[1] }}">{{ $stats[$key] }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $label[0] }}</div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach(['censored' => 'Censored (Auto)', 'warnings' => 'Warnings', 'suspensions' => 'Suspensions', 'blocks' => 'Blocks'] as $key => $label)
        <div class="bg-white rounded-2xl shadow p-4">
            <div class="text-2xl font-bold text-primary-600">{{ $stats[$key] }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $label }}</div>
        </div>
    @endforeach
</div>

@if($tab === 'users')
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
            <h3 class="font-bold text-slate-800">Users with violations</h3>
            <form method="GET" action="{{ route('admin.moderation') }}" class="flex items-center gap-2 ml-auto">
                <input type="hidden" name="tab" value="users">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or email..."
                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                <button type="submit" class="bg-primary-600 text-white text-sm font-semibold rounded-lg px-4 py-1.5"><i class="fas fa-search"></i></button>
            </form>
        </div>
        <div class="overflow-x-auto">
<div id="liveTable">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-6 py-3">User</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-left px-4 py-3">Strikes (30d)</th>
                        <th class="text-left px-4 py-3">Violations</th>
                        <th class="text-right px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $u)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-800">{{ $u->name }}</div>
                                <div class="text-xs text-slate-500">{{ $u->email }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs px-2.5 py-1 rounded-full border {{ $u->status === 'active' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($u->status === 'suspended' ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-red-100 text-red-700 border-red-200') }}">
                                    {{ ucfirst($u->status) }}
                                </span>
                                @if($u->status === 'suspended' && $u->suspended_until)
                                    <span class="block text-[10px] text-amber-600 mt-0.5">until {{ $u->suspended_until->format('M j, g:i A') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs px-2.5 py-1 rounded-lg font-semibold {{ $u->moderation_strikes_count > 0 ? 'bg-red-50 text-red-600' : 'bg-slate-50 text-slate-500' }}">{{ $u->moderation_strikes_count }}</span>
                            </td>
                            <td class="px-4 py-4 text-slate-700">{{ $u->content_violations_count }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.moderation.users', $u) }}"
                                       class="inline-flex items-center gap-1.5 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                        <i class="fas fa-eye"></i> History
                                    </a>
                                    @if($u->status !== 'banned')
                                        <button type="button" onclick="openStrike({{ $u->id }}, '{{ addslashes($u->name) }}', 'suspend')"
                                                class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                            <i class="fas fa-pause"></i> Suspend
                                        </button>
                                    @endif
                                    @if($u->status !== 'banned' && $u->status !== 'suspended')
                                        <button type="button" onclick="openStrike({{ $u->id }}, '{{ addslashes($u->name) }}', 'block')"
                                                class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                            <i class="fas fa-ban"></i> Block
                                        </button>
                                    @endif
                                    @if($u->status !== 'active')
                                        <form method="POST" action="{{ route('admin.moderation.activate', $u) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                                <i class="fas fa-undo"></i> Activate
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400">No users with violations yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4">{{ $users->links() }}</div>
</div>
    </div>
@else
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
            <h3 class="font-bold text-slate-800">Violation log</h3>
            <form method="GET" action="{{ route('admin.moderation') }}" class="flex items-center gap-2 ml-auto flex-wrap">
                <input type="hidden" name="tab" value="violations">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search user..."
                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                <select name="entity" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                    <option value="">All types</option>
                    @foreach(array_keys($entityLabels) as $e)
                        <option value="{{ $e }}" {{ request('entity') === $e ? 'selected' : '' }}>{{ $entityLabels[$e] }}</option>
                    @endforeach
                </select>
                <select name="severity" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                    <option value="">All severities</option>
                    @foreach(['mild', 'moderate', 'severe'] as $s)
                        <option value="{{ $s }}" {{ request('severity') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg px-4 py-1.5"><i class="fas fa-filter"></i></button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-6 py-3">User</th>
                        <th class="text-left px-4 py-3">Content</th>
                        <th class="text-left px-4 py-3">Original</th>
                        <th class="text-left px-4 py-3">Censored to</th>
                        <th class="text-left px-4 py-3">Severity</th>
                        <th class="text-left px-4 py-3">Source</th>
                        <th class="text-right px-6 py-3">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($violations as $v)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-6 py-4">
                                @if($v->user)
                                    <a href="{{ route('admin.moderation.users', $v->user) }}" class="font-medium text-primary-600 hover:underline">{{ $v->user->name }}</a>
                                    <div class="text-xs text-slate-500">{{ $v->user->email }}</div>
                                @else
                                    <span class="text-slate-400">â€”</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600">{{ $entityLabels[$v->entity_type] ?? $v->entity_type }}</span>
                                <div class="text-[10px] text-slate-400 mt-1">#{{ $v->entity_id }} Â· {{ $v->field }}</div>
                            </td>
                            <td class="px-4 py-4 text-slate-500 max-w-[200px] truncate" title="{{ $v->original_text }}">
                                <span class="line-through decoration-red-400">{{ \Illuminate\Support\Str::limit($v->original_text, 60) }}</span>
                            </td>
                            <td class="px-4 py-4 text-slate-700 max-w-[200px] truncate" title="{{ $v->censored_text }}">{{ \Illuminate\Support\Str::limit($v->censored_text, 60) }}</td>
                            <td class="px-4 py-4">
                                <span class="text-xs px-2.5 py-1 rounded-full border {{ $severityColors[$v->severity] ?? '' }}">{{ ucfirst($v->severity) }}</span>
                                @if($v->found_words)
                                    <div class="text-[10px] text-red-500 mt-1">{{ implode(', ', $v->found_words) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-500">{{ ucfirst($v->source) }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $v->created_at->format('M j, g:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <i class="fas fa-shield-halved text-3xl mb-3 block text-emerald-400"></i>
                                No violations recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4">{{ $violations->links() }}</div>
    </div>
@endif

<!-- Strike modal -->
<div id="strikeModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1" id="strikeTitle"></h3>
        <p class="text-sm text-slate-500 mb-4" id="strikeDesc"></p>
        <form method="POST" action="" id="strikeForm">
            @csrf
            <input type="hidden" name="level" id="strikeLevel">
            <label class="block text-sm font-medium text-slate-700 mb-1">Reason (kept on record)</label>
            <textarea name="reason" rows="3" required
                      class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-red-500 outline-none"
                      placeholder="e.g. Repeatedly posting abusive language despite warnings"></textarea>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeStrike()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg px-5 py-2" id="strikeSubmit">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStrike(userId, name, level) {
        document.getElementById('strikeForm').action = '/' + window.adminPrefix + '/moderation/strike/' + userId;
        document.getElementById('strikeLevel').value = level;
        document.getElementById('strikeTitle').textContent = name;
        document.getElementById('strikeDesc').textContent = level === 'suspend'
            ? 'Temporarily suspend this user with a record of the reason.'
            : 'Permanently block this user with a record of the reason.';
        document.getElementById('strikeSubmit').textContent = level === 'suspend' ? 'Suspend User' : 'Block User';
        document.getElementById('strikeModal').classList.remove('hidden');
    }
    function closeStrike() {
        document.getElementById('strikeModal').classList.add('hidden');
    }
</script>
@endsection
