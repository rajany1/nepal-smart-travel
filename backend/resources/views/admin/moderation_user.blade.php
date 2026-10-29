@extends('admin.layout')

@section('title', "Moderation History - {$user->name}")

@section('content')
@php
    $statusStyles = [
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'suspended' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'banned' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
    $levelStyles = [
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'suspend' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'block' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
    $levelColors = ['warning' => 'text-amber-500', 'suspend' => 'text-orange-600', 'block' => 'text-rose-600'];
    $initial = strtoupper(mb_substr($user->name, 0, 1));
@endphp

<div id="adminSafetyUser" class="space-y-5 p-4 sm:p-5 lg:p-6">
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <a href="{{ route('admin.moderation', ['tab' => 'users']) }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-100 transition hover:text-white">
                    <i class="fas fa-arrow-left"></i> Back to offender watchlist
                </a>
                <div class="mt-4 flex items-center gap-3">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-xl font-bold text-white">{{ $initial }}</span>
                    <div>
                        <h1 class="text-2xl font-semibold sm:text-3xl">{{ $user->name }}</h1>
                        <p class="text-sm text-primary-100">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold ring-1 {{ $statusStyles[$user->status] ?? 'bg-white/10 text-white ring-white/20' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $user->status === 'active' ? 'bg-emerald-400' : ($user->status === 'suspended' ? 'bg-amber-400' : 'bg-rose-400') }}"></span>
                        {{ ucfirst($user->status) }}
                    </span>
                    @if($user->status === 'suspended' && $user->suspended_until)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-primary-100">
                            <i class="fas fa-hourglass-half text-[10px]"></i> until {{ $user->suspended_until->format('M j, Y g:i A') }}
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-primary-100">
                        <i class="fas fa-calendar text-[10px]"></i> member since {{ $user->created_at?->format('M Y') ?? '—' }}
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canManageSafety && $user->status !== 'banned')
                    <button type="button" class="js-strike inline-flex h-10 items-center gap-2 rounded-2xl bg-amber-400/20 px-3 text-xs font-semibold text-amber-100 transition hover:bg-amber-400/30" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-level="warning">
                        <i class="fas fa-flag"></i> Warn
                    </button>
                @endif
                @if($canManageSafety && $user->status !== 'banned' && $user->status !== 'suspended')
                    <button type="button" class="js-strike inline-flex h-10 items-center gap-2 rounded-2xl bg-orange-400/20 px-3 text-xs font-semibold text-orange-100 transition hover:bg-orange-400/30" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-level="suspend">
                        <i class="fas fa-pause"></i> Suspend
                    </button>
                @endif
                @if($canManageSafety && $user->status !== 'banned')
                    <button type="button" class="js-strike inline-flex h-10 items-center gap-2 rounded-2xl bg-rose-500/25 px-3 text-xs font-semibold text-rose-100 transition hover:bg-rose-500/35" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-level="block">
                        <i class="fas fa-ban"></i> Block
                    </button>
                @endif
                @if($canManageSafety && $user->status !== 'active')
                    <form method="POST" action="{{ route('admin.moderation.activate', $user) }}" data-native>
                        @csrf
                        <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-emerald-400/20 px-3 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-400/30">
                            <i class="fas fa-undo"></i> Activate
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Total violations</p>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($totalViolations) }}</p>
            <p class="mt-2 text-xs text-slate-400">{{ number_format($safety['violations_30d']) }} in the last {{ $safety['window_days'] }} days</p>
        </div>
        @foreach(['warning' => 'Warnings', 'suspend' => 'Suspensions', 'block' => 'Blocks'] as $level => $label)
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold {{ $levelColors[$level] }}">{{ number_format($strikes->where('level', $level)->count()) }}</p>
                <p class="mt-2 text-xs text-slate-400">
                    {{ $level === 'warning' ? 'On record' : ($level === 'suspend' ? 'Temporary account holds' : 'Permanent bans') }}
                </p>
            </div>
        @endforeach
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Escalation ladder</h2>
                <p class="mt-1 text-xs text-slate-500">Every strike in the last {{ $safety['window_days'] }} days counts toward the next penalty.</p>
            </div>
            <span class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold {{ $user->status === 'banned' ? 'bg-rose-50 text-rose-700' : ($user->status === 'suspended' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                <i class="fas fa-arrow-trend-up"></i> Next strike: {{ $safety['next_action'] }}
            </span>
        </div>
        <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl bg-slate-50 px-3 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Active strikes</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ number_format($safety['active_strikes']) }}</p>
            </div>
            <div class="rounded-2xl bg-amber-50/70 px-3 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700/70">Warning at</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $safety['warn_at'] }} <span class="text-xs font-normal text-slate-400">strike(s)</span></p>
            </div>
            <div class="rounded-2xl bg-orange-50/70 px-3 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-orange-700/70">Suspension at</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $safety['suspend_at'] }} <span class="text-xs font-normal text-slate-400">strike(s)</span></p>
            </div>
            <div class="rounded-2xl bg-rose-50/70 px-3 py-2.5">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-700/70">Block at</p>
                <p class="mt-1 text-lg font-semibold text-slate-900">{{ $safety['ban_at'] }} <span class="text-xs font-normal text-slate-400">strike(s)</span></p>
            </div>
        </div>
        <p class="mt-3 text-[10px] text-slate-400">Automated: {{ number_format($safety['auto_strikes']) }} · Manual: {{ number_format($safety['manual_strikes']) }} · Blocked accounts cannot be reactivated from this page.</p>
    </section>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900"><i class="fas fa-gavel mr-1 text-rose-500"></i> Why this user was punished</h2>
                <p class="mt-1 text-xs text-slate-500">Every warning, suspension and block with its reason.</p>
            </div>
            <div class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto">
                @forelse($strikes as $strike)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $levelStyles[$strike->level] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ ucfirst($strike->level) }}</span>
                            <span class="text-xs text-slate-400">{{ $strike->created_at?->format('M j, Y g:i A') }}</span>
                            <span class="ml-auto text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $strike->issued_by ? 'Admin' : 'System (AI agent)' }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-700">{{ $strike->reason }}</p>
                        @if($strike->level === 'suspend' && $strike->expires_at)
                            <p class="mt-1 text-xs text-amber-600">Suspended until {{ $strike->expires_at->format('M j, Y g:i A') }}</p>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <i class="fas fa-shield-halved mb-2 block text-2xl text-emerald-400"></i>
                        <p class="text-sm text-slate-500">No strikes recorded. This user is clean.</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900"><i class="fas fa-file-lines mr-1 text-rose-500"></i> What they posted</h2>
                <p class="mt-1 text-xs text-slate-500">Original content and the censored version stored by the agent.</p>
            </div>
            <div class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto">
                @forelse($violations as $v)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium">{{ $v->entity_type }} #{{ $v->entity_id }} · {{ $v->field }}</span>
                            <span class="text-slate-400">{{ $v->created_at?->format('M j, g:i A') }}</span>
                            <span class="ml-auto inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 {{ $v->severity === 'severe' ? 'bg-rose-50 text-rose-700 ring-rose-200' : ($v->severity === 'moderate' ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-slate-100 text-slate-600 ring-slate-200') }}">{{ ucfirst($v->severity) }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500 line-through decoration-rose-400">{{ \Illuminate\Support\Str::limit($v->original_text, 140) }}</p>
                        <p class="mt-1 text-sm text-emerald-700">{{ \Illuminate\Support\Str::limit($v->censored_text, 140) }}</p>
                        @if($v->found_words)
                            <div class="mt-1 text-[10px] text-rose-500">words: {{ implode(', ', $v->found_words) }}</div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <i class="fas fa-file-shield mb-2 block text-2xl text-emerald-400"></i>
                        <p class="text-sm text-slate-500">No violations on record.</p>
                    </div>
                @endforelse
            </div>
            @if($violations->hasPages())
                <div class="border-t border-slate-100 px-5 py-3">{{ $violations->links() }}</div>
            @endif
        </section>
    </div>
</div>

{{-- Strike modal --}}
<div id="strikeModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="mb-1 text-lg font-bold text-slate-800" id="strikeTitle">{{ $user->name }}</h3>
        <p class="mb-4 text-sm text-slate-500" id="strikeDesc"></p>
        <form method="POST" action="{{ route('admin.moderation.strike', $user) }}" id="strikeForm">
            @csrf
            <input type="hidden" name="level" id="strikeLevel">
            <label class="mb-1 block text-sm font-medium text-slate-700">Reason (kept on record)</label>
            <textarea name="reason" rows="3" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 outline-none focus:ring-2 focus:ring-red-500" placeholder="e.g. Repeatedly posting abusive language despite warnings"></textarea>
            <div class="mt-4 flex justify-end gap-3">
                <button type="button" onclick="closeStrike()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700" id="strikeSubmit">Apply</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    function openStrike(userId, name, level) {
        var isWarning = level === 'warning';
        var isSuspend = level === 'suspend';

        document.getElementById('strikeForm').action = '/' + (window.adminPrefix || 'admin') + '/moderation/strike/' + userId;
        document.getElementById('strikeLevel').value = level;
        document.getElementById('strikeTitle').textContent = name || 'Account action';
        document.getElementById('strikeDesc').textContent = isWarning
            ? 'Record a formal warning for this account. The reason is kept in the moderation history.'
            : (isSuspend
                ? 'Temporarily suspend this user with a record of the reason.'
                : 'Permanently block this user with a record of the reason.');
        var submit = document.getElementById('strikeSubmit');
        submit.textContent = isWarning ? 'Issue Warning' : (isSuspend ? 'Suspend User' : 'Block User');
        submit.className = 'rounded-lg px-5 py-2 text-sm font-semibold text-white ' + (isWarning ? 'bg-amber-500 hover:bg-amber-600' : (isSuspend ? 'bg-orange-500 hover:bg-orange-600' : 'bg-red-600 hover:bg-red-700'));
        document.getElementById('strikeModal').classList.remove('hidden');
    }

    function closeStrike() {
        document.getElementById('strikeModal').classList.add('hidden');
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('.js-strike') : null;
        if (trigger) {
            event.preventDefault();
            openStrike(trigger.getAttribute('data-user-id'), trigger.getAttribute('data-user-name'), trigger.getAttribute('data-level'));
            return;
        }
        if (event.target === document.getElementById('strikeModal')) closeStrike();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeStrike();
    });

    window.openStrike = openStrike;
    window.closeStrike = closeStrike;
})();
</script>
@endsection
