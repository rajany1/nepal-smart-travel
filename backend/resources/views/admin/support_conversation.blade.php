@extends('admin.layout')

@section('title', $conversation->subject . ' - Support')

@section('content')
@php
    $statusDots = [
        'open' => 'bg-sky-300',
        'ai_handling' => 'bg-indigo-300',
        'awaiting_human' => 'bg-amber-300',
        'in_progress' => 'bg-emerald-300',
        'resolved' => 'bg-emerald-300',
        'closed' => 'bg-slate-300',
    ];
    $statusDot = $statusDots[$conversation->status] ?? 'bg-slate-300';

    $priorityStyles = [
        'urgent' => 'bg-rose-400/20 text-rose-100 ring-rose-300/30',
        'high' => 'bg-orange-400/20 text-orange-100 ring-orange-300/30',
        'normal' => 'bg-sky-400/20 text-sky-100 ring-sky-300/30',
        'low' => 'bg-white/10 text-primary-100 ring-white/20',
    ];
    $priorityClass = $priorityStyles[$conversation->priority] ?? $priorityStyles['low'];

    $isActive = in_array($conversation->status, ['open', 'ai_handling', 'awaiting_human', 'in_progress'], true);
    $requesterName = $conversation->user->name ?? 'Deleted user';
    $requesterInitial = strtoupper(mb_substr($requesterName, 0, 1));
    $pollUrl = route('admin.support.poll-messages', $conversation->id);
@endphp

<div id="adminSupportConversation" class="flex flex-col gap-4 p-4 sm:p-5 lg:p-6 xl:h-[calc(100vh-4rem)]">
    {{-- Conversation header --}}
    <section class="relative shrink-0 overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 px-5 py-4 text-white shadow-xl sm:px-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-48 w-48 rounded-full bg-accent-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <a href="{{ route('admin.support') }}" class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-primary-100 transition hover:bg-white/20 hover:text-white" title="Back to inbox">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.28em] text-primary-200">Conversation #{{ $conversation->id }}</p>
                        <span data-conversation-status class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[10px] font-semibold text-white ring-1 ring-white/20">
                            <span data-conversation-status-dot class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                            <span data-conversation-status-label>{{ $conversation->statusLabel() }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-semibold ring-1 {{ $priorityClass }}">
                            <i class="fas fa-flag text-[9px]"></i>{{ $conversation->priorityLabel() }} priority
                        </span>
                    </div>
                    <h1 class="mt-1.5 truncate text-xl font-semibold tracking-tight sm:text-2xl">{{ $conversation->subject }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[11px] text-primary-100">
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-user text-[10px] text-primary-200"></i>{{ $requesterName }}@if($conversation->user?->email)<span class="text-primary-200/80">&middot; {{ $conversation->user->email }}</span>@endif</span>
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-tag text-[10px] text-primary-200"></i>{{ $conversation->categoryLabel() }}</span>
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-comments text-[10px] text-primary-200"></i><span data-conversation-message-count>{{ number_format($conversation->message_count) }}</span> messages</span>
                        <span class="inline-flex items-center gap-1.5"><i class="fas fa-clock text-[10px] text-primary-200"></i>Opened {{ $conversation->created_at->diffForHumans() }}</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-medium text-primary-100">
                    <i class="fas fa-bolt text-[10px] text-primary-200"></i>Live updates
                </span>
                @if($conversation->assignee)
                    <span class="inline-flex items-center gap-2 rounded-2xl bg-white/10 px-3 py-2 text-xs font-semibold text-white">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/20 text-[10px] font-bold">{{ strtoupper(mb_substr($conversation->assignee->name, 0, 1)) }}</span>
                        {{ $conversation->assignee->name }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-2xl bg-amber-400/15 px-3 py-2 text-xs font-semibold text-amber-100">
                        <i class="fas fa-user-clock text-[10px]"></i>Unassigned
                    </span>
                @endif
                <a href="{{ route('admin.support', ['status' => 'all']) }}" class="inline-flex h-9 items-center gap-1.5 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                    <i class="fas fa-inbox"></i>Queue
                </a>
            </div>
        </div>
    </section>

    <div class="flex min-h-0 flex-1 flex-col gap-4 xl:flex-row">
        {{-- Thread --}}
        <section class="flex min-h-[600px] min-w-0 flex-1 flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm xl:min-h-0">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 sm:px-5">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-xs font-bold text-sky-700">{{ $requesterInitial }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-slate-800">{{ $requesterName }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ $conversation->user?->email ?? 'No email on file' }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-400">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Live
                    </span>
                    <span>Last activity {{ $conversation->last_reply_at?->diffForHumans() ?? '—' }}</span>
                </div>
            </div>

            <div id="messages-container" class="max-h-[65vh] flex-1 space-y-4 overflow-y-auto bg-slate-50/60 px-4 py-5 sm:px-5 xl:max-h-none">
                @if($hasMoreMessages)
                    <div class="py-1 text-center" id="load-earlier-wrapper">
                        <button onclick="loadEarlier()" id="load-earlier-btn" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold text-primary-700 shadow-sm transition hover:border-primary-200 hover:bg-primary-50">
                            <i class="fas fa-arrow-up"></i>Load earlier messages
                        </button>
                        <div class="mt-1.5 text-[10px] text-slate-400">Showing {{ $conversation->messages->count() }} of {{ $totalMessages }} messages</div>
                    </div>
                @endif

                @forelse($conversation->messages as $msg)
                    @php
                        $isAi = $msg->sender_type === 'ai';
                        $isSystem = $msg->sender_type === 'system';
                        $isUser = $msg->sender_type === 'user';

                        $avatarClass = $isUser ? 'bg-sky-500 text-white' : ($isAi ? 'bg-indigo-500 text-white' : ($isSystem ? 'bg-slate-400 text-white' : 'bg-primary-600 text-white'));
                        $nameClass = $isUser ? 'text-sky-700' : ($isAi ? 'text-indigo-600' : ($isSystem ? 'text-slate-400' : 'text-primary-700'));
                        $bubbleClass = $isUser
                            ? 'border-slate-200 bg-white text-slate-800'
                            : ($isAi
                                ? 'border-indigo-100 bg-indigo-50 text-slate-800'
                                : ($isSystem
                                    ? 'border-slate-200 bg-slate-100 text-slate-500 italic'
                                    : 'border-primary-100 bg-primary-50/80 text-slate-800'));
                    @endphp
                    <div class="message-row flex gap-3 {{ $isAi ? 'flex-row-reverse' : '' }}">
                        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold shadow-sm {{ $avatarClass }}">
                            @if($isUser)
                                {{ strtoupper(mb_substr($conversation->user->name ?? 'U', 0, 1)) }}
                            @elseif($isAi)
                                <i class="fas fa-robot text-[10px]"></i>
                            @elseif($isSystem)
                                <i class="fas fa-cog text-[10px]"></i>
                            @else
                                {{ strtoupper(mb_substr($msg->user->name ?? 'S', 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0 max-w-[78%] {{ $isAi ? 'text-right' : '' }}">
                            <div class="mb-1 flex flex-wrap items-center gap-2 {{ $isAi ? 'justify-end' : '' }}">
                                <span class="text-[11px] font-semibold {{ $nameClass }}">
                                    @if($isUser)
                                        {{ $conversation->user->name ?? 'User' }}
                                    @elseif($isAi)
                                        AI Assistant
                                        @if($msg->ai_model)
                                            <span class="text-[10px] font-normal text-slate-400">({{ $msg->ai_model }})</span>
                                        @endif
                                    @elseif($isSystem)
                                        System
                                    @else
                                        {{ $msg->user->name ?? 'Staff' }}
                                    @endif
                                </span>
                                <span class="text-[10px] text-slate-400">{{ $msg->created_at->format('M d, g:ia') }}</span>
                                @if($msg->is_internal_note)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-semibold text-amber-700">Internal note</span>
                                @endif
                                @if($msg->ai_confidence !== null)
                                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[9px] font-semibold text-indigo-600">confidence: {{ number_format($msg->ai_confidence * 100, 0) }}%</span>
                                @endif
                            </div>
                            <div class="inline-block rounded-2xl border px-4 py-3 text-left text-sm leading-relaxed shadow-sm {{ $bubbleClass }}">
                                {!! nl2br(e($msg->content)) !!}
                            </div>
                            @if($msg->ai_actions && count($msg->ai_actions) > 0)
                                <div class="mt-1 text-[10px] text-slate-400">Actions: {{ json_encode($msg->ai_actions) }}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex h-full flex-col items-center justify-center py-12 text-center">
                        <span class="mb-3 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-300 shadow-sm"><i class="fas fa-comment-dots text-lg"></i></span>
                        <p class="text-sm font-medium text-slate-600">No messages yet</p>
                        <p class="mt-1 text-xs text-slate-400">Send the first reply below to start the conversation.</p>
                    </div>
                @endforelse
            </div>

            @if($conversation->status !== 'closed')
                <div class="shrink-0 border-t border-slate-100 bg-white p-4 sm:p-5">
                    <form method="POST" action="{{ route('admin.support.reply', $conversation->id) }}" id="reply-form">
                        @csrf
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <label for="supportReply" class="text-xs font-semibold text-slate-700">
                                <i class="fas fa-reply mr-1 text-primary-600"></i>Reply to {{ $requesterName }}
                            </label>
                            <span class="text-[10px] text-slate-400">Internal notes stay hidden from the requester.</span>
                        </div>
                        <textarea id="supportReply" name="message" rows="3" placeholder="Type your reply..." class="w-full resize-none rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:bg-white focus:ring-2 focus:ring-primary-100" required></textarea>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-amber-50 px-2.5 py-1.5 text-[11px] font-medium text-amber-700">
                                <input type="checkbox" name="is_internal_note" value="1" class="rounded border-amber-300 text-amber-500 focus:ring-amber-400">
                                Internal note (not visible to user)
                            </label>
                            <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-primary-700 px-4 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-paper-plane"></i>Send reply
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="shrink-0 border-t border-slate-100 bg-slate-50 px-4 py-4 text-center sm:px-5">
                    <p class="text-xs text-slate-500">This conversation is closed. Reopen it to reply to the requester.</p>
                    <form method="POST" action="{{ route('admin.support.reopen', $conversation->id) }}" class="mt-2 inline">
                        @csrf
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-amber-500 px-4 text-xs font-semibold text-white transition hover:bg-amber-600">
                            <i class="fas fa-rotate-left"></i>Reopen conversation
                        </button>
                    </form>
                </div>
            @endif
        </section>

        {{-- Sidebar --}}
        <aside class="w-full shrink-0 space-y-3 xl:w-80 xl:overflow-y-auto">
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Conversation controls</h2>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $isActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $isActive ? 'Active' : 'Settled' }}
                    </span>
                </div>
                <div class="mt-3 space-y-2">
                    @if($conversation->assigned_to !== auth()->id())
                        <form method="POST" action="{{ route('admin.support.takeover', $conversation->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-hand-paper"></i>Take over
                            </button>
                        </form>
                    @endif

                    @if($conversation->status === 'ai_handling')
                        <form method="POST" action="{{ route('admin.support.stop-ai', $conversation->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700">
                                <i class="fas fa-robot"></i>Stop AI / Escalate
                            </button>
                        </form>
                    @endif

                    @if($isActive)
                        <form method="POST" action="{{ route('admin.support.resolve', $conversation->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">
                                <i class="fas fa-circle-check"></i>Resolve conversation
                            </button>
                        </form>
                    @endif

                    @if($conversation->status !== 'closed')
                        <form method="POST" action="{{ route('admin.support.close', $conversation->id) }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                                <i class="fas fa-xmark"></i>Close conversation
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Triage</h2>
                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportAssignee">Assigned to</label>
                        <form method="POST" action="{{ route('admin.support.assign', $conversation->id) }}">
                            @csrf
                            <select id="supportAssignee" name="assigned_to" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100" onchange="this.form.submit()">
                                <option value="">Unassigned</option>
                                @foreach($staff as $s)
                                    <option value="{{ $s->id }}" {{ $conversation->assigned_to === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportCategory">Category</label>
                        <form method="POST" action="{{ route('admin.support.category', $conversation->id) }}">
                            @csrf
                            <select id="supportCategory" name="category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100" onchange="this.form.submit()">
                                @foreach(\App\Models\SupportConversation::CATEGORIES as $k => $v)
                                    <option value="{{ $k }}" {{ $conversation->category === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportPriority">Priority</label>
                        <form method="POST" action="{{ route('admin.support.priority', $conversation->id) }}">
                            @csrf
                            <select id="supportPriority" name="priority" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100" onchange="this.form.submit()">
                                @foreach(\App\Models\SupportConversation::PRIORITIES as $k => $v)
                                    <option value="{{ $k }}" {{ $conversation->priority === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Requester</h2>
                <div class="mt-3 flex items-center gap-3">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-700">{{ $requesterInitial }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">{{ $requesterName }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ $conversation->user?->email ?? '—' }}</p>
                    </div>
                </div>
                <dl class="mt-3 space-y-1.5 text-[11px]">
                    @if($conversation->user?->phone)
                        <div class="flex items-center justify-between gap-3"><dt class="text-slate-400">Phone</dt><dd class="truncate font-medium text-slate-600">{{ $conversation->user->phone }}</dd></div>
                    @endif
                    @if($conversation->user)
                        <div class="flex items-center justify-between gap-3"><dt class="text-slate-400">Joined</dt><dd class="font-medium text-slate-600">{{ $conversation->user->created_at->format('M d, Y') }}</dd></div>
                    @endif
                    <div class="flex items-center justify-between gap-3"><dt class="text-slate-400">Conversation</dt><dd class="font-medium text-slate-600">#{{ $conversation->id }}</dd></div>
                </dl>
            </div>

            @if($conversation->ai_handled)
                <div class="rounded-3xl border border-indigo-100 bg-indigo-50/70 p-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm"><i class="fas fa-robot text-[11px]"></i></span>
                        <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-700/70">AI handling</h2>
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-indigo-900/70">AI responded to this conversation.</p>
                    @if($conversation->ai_confidence !== null)
                        <div class="mt-2 flex items-center justify-between text-[11px]">
                            <span class="text-indigo-700/60">Average confidence</span>
                            <span class="font-semibold text-indigo-800">{{ number_format($conversation->ai_confidence * 100, 0) }}%</span>
                        </div>
                    @endif
                </div>
            @endif

            @if($conversation->satisfaction)
                <div class="rounded-3xl border border-emerald-100 bg-emerald-50/70 p-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm"><i class="fas fa-star text-[11px]"></i></span>
                        <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700/70">Satisfaction review</h2>
                    </div>
                    <div class="mt-2 flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star text-xs {{ $i <= $conversation->satisfaction->rating ? 'text-amber-400' : 'text-emerald-200' }}"></i>
                        @endfor
                        <span class="ml-1 text-xs font-semibold text-slate-700">{{ $conversation->satisfaction->rating }}/5</span>
                    </div>
                    <p class="mt-1 text-[11px] font-medium text-emerald-800">{{ $conversation->satisfaction->ratingLabel() }}</p>
                    @if($conversation->satisfaction->comment)
                        <p class="mt-2 rounded-xl bg-white/70 px-2.5 py-2 text-[11px] italic text-slate-600">"{{ $conversation->satisfaction->comment }}"</p>
                    @endif
                    <p class="mt-2 text-[10px] text-emerald-700/60">Submitted {{ $conversation->satisfaction->created_at->diffForHumans() }}</p>
                </div>
            @elseif(in_array($conversation->status, ['resolved', 'closed']))
                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Satisfaction review</h2>
                    <p class="mt-2 text-[11px] text-slate-400">No review submitted yet.</p>
                </div>
            @endif

            @if($conversation->metadata)
                <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Metadata</h2>
                    <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap rounded-xl bg-slate-50 p-2.5 text-[10px] text-slate-600">{{ json_encode($conversation->metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            @endif
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var POLL_MS = 8000;
    var conversationId = {{ $conversation->id }};
    var pollMessagesUrl = @json($pollUrl);
    var lastMessageId = {{ $conversation->messages->count() > 0 ? $conversation->messages->last()->id : 0 }};
    var firstMessageId = {{ $conversation->messages->count() > 0 ? $conversation->messages->first()->id : 0 }};
    var totalMessages = {{ $totalMessages }};
    var pollTimer = null;
    var inFlight = false;
    var isNearBottom = true;
    var loadingEarlier = false;
    var hasMoreMessages = {{ $hasMoreMessages ? 'true' : 'false' }};

    var container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
        container.addEventListener('scroll', function () {
            isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
        });
    }

    window.loadEarlier = function () {
        if (loadingEarlier || !hasMoreMessages || !container) return;
        loadingEarlier = true;
        var btn = document.getElementById('load-earlier-btn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>Loading...';
            btn.disabled = true;
        }

        var scrollHeightBefore = container.scrollHeight;

        fetch(pollMessagesUrl + '?before_message_id=' + firstMessageId + '&limit=50', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success || !data.messages || data.messages.length === 0) {
                hasMoreMessages = false;
                var wrapper = document.getElementById('load-earlier-wrapper');
                if (wrapper) wrapper.remove();
                loadingEarlier = false;
                return;
            }

            var firstChild = container.querySelector('.message-row');
            if (firstChild) {
                firstChild.insertAdjacentHTML('beforebegin', buildMessagesHtml(data.messages));
            } else {
                container.insertAdjacentHTML('afterbegin', buildMessagesHtml(data.messages));
            }

            container.scrollTop = container.scrollHeight - scrollHeightBefore;
            firstMessageId = data.messages[0].id;
            hasMoreMessages = data.has_more;

            var loadedCount = container.querySelectorAll('.message-row').length;
            if (!hasMoreMessages) {
                var wrapper = document.getElementById('load-earlier-wrapper');
                if (wrapper) wrapper.remove();
            } else if (btn) {
                btn.innerHTML = '<i class="fas fa-arrow-up"></i>Load earlier messages';
                btn.disabled = false;
                var counter = document.querySelector('#load-earlier-wrapper .text-\\[10px\\]');
                if (counter) counter.textContent = 'Showing ' + loadedCount + ' of ' + totalMessages + ' messages';
            }
            loadingEarlier = false;
        })
        .catch(function () {
            loadingEarlier = false;
            if (btn) {
                btn.innerHTML = '<i class="fas fa-arrow-up"></i>Load earlier messages';
                btn.disabled = false;
            }
        });
    };

    function messageClasses(msg) {
        var isUser = msg.sender_type === 'user';
        var isAi = msg.sender_type === 'ai';
        var isSystem = msg.sender_type === 'system';

        return {
            isUser: isUser,
            isAi: isAi,
            isSystem: isSystem,
            alignment: isAi ? 'flex-row-reverse' : '',
            avatar: isUser ? 'bg-sky-500 text-white' : (isAi ? 'bg-indigo-500 text-white' : (isSystem ? 'bg-slate-400 text-white' : 'bg-primary-600 text-white')),
            name: isUser ? 'text-sky-700' : (isAi ? 'text-indigo-600' : (isSystem ? 'text-slate-400' : 'text-primary-700')),
            nameAlign: isAi ? 'justify-end' : '',
            contentAlign: isAi ? 'text-right' : '',
            bubble: isUser ? 'border-slate-200 bg-white text-slate-800' : (isAi ? 'border-indigo-100 bg-indigo-50 text-slate-800' : (isSystem ? 'border-slate-200 bg-slate-100 text-slate-500 italic' : 'border-primary-100 bg-primary-50/80 text-slate-800'))
        };
    }

    function buildMessagesHtml(messages) {
        return messages.map(function (msg) {
            var c = messageClasses(msg);
            var avatarContent = c.isUser ? escHtml(msg.avatar_letter || 'U') : (c.isAi ? '<i class="fas fa-robot text-[10px]"></i>' : (c.isSystem ? '<i class="fas fa-cog text-[10px]"></i>' : 'S'));
            var noteHtml = msg.is_internal_note ? '<span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-semibold text-amber-700">Internal note</span>' : '';
            var confidenceHtml = msg.ai_confidence !== null ? '<span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[9px] font-semibold text-indigo-600">confidence: ' + Math.round(msg.ai_confidence * 100) + '%</span>' : '';
            var modelHtml = msg.ai_model ? '<span class="text-[10px] font-normal text-slate-400">(' + escHtml(msg.ai_model) + ')</span>' : '';
            var actionsHtml = msg.ai_actions && msg.ai_actions.length ? '<div class="mt-1 text-[10px] text-slate-400">Actions: ' + escHtml(JSON.stringify(msg.ai_actions)) + '</div>' : '';

            return '<div class="message-row flex gap-3 ' + c.alignment + '">'
                + '<div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold shadow-sm ' + c.avatar + '">' + avatarContent + '</div>'
                + '<div class="min-w-0 max-w-[78%] ' + c.contentAlign + '">'
                + '<div class="mb-1 flex flex-wrap items-center gap-2 ' + c.nameAlign + '">'
                + '<span class="text-[11px] font-semibold ' + c.name + '">' + escHtml(msg.user_name) + ' ' + modelHtml + '</span>'
                + '<span class="text-[10px] text-slate-400">' + escHtml(msg.created_at) + '</span>'
                + noteHtml + confidenceHtml
                + '</div>'
                + '<div class="inline-block rounded-2xl border px-4 py-3 text-left text-sm leading-relaxed shadow-sm ' + c.bubble + '">' + nl2br(escHtml(msg.content)) + '</div>'
                + actionsHtml
                + '</div></div>';
        }).join('');
    }

    function pollConversation() {
        if (inFlight || document.visibilityState === 'hidden') return;
        inFlight = true;

        fetch(pollMessagesUrl + '?since_id=' + lastMessageId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) { inFlight = false; return; }
                if (data.messages && data.messages.length > 0) {
                    appendMessages(data.messages);
                    lastMessageId = data.messages[data.messages.length - 1].id;
                }
                updateHeaderStatus(data.status, data.status_label, data.message_count);
                inFlight = false;
            })
            .catch(function () { inFlight = false; });
    }

    function appendMessages(messages) {
        if (!container) return;
        container.insertAdjacentHTML('beforeend', buildMessagesHtml(messages));

        if (isNearBottom) {
            container.scrollTop = container.scrollHeight;
        }
    }

    function updateHeaderStatus(status, label, count) {
        var dot = document.querySelector('[data-conversation-status-dot]');
        if (dot) {
            var dotMap = {
                'open': 'bg-sky-300',
                'ai_handling': 'bg-indigo-300',
                'awaiting_human': 'bg-amber-300',
                'in_progress': 'bg-emerald-300',
                'resolved': 'bg-emerald-300',
                'closed': 'bg-slate-300'
            };
            dot.className = 'h-1.5 w-1.5 rounded-full ' + (dotMap[status] || 'bg-slate-300');
        }
        var labelNode = document.querySelector('[data-conversation-status-label]');
        if (labelNode) labelNode.textContent = label;
        var countNode = document.querySelector('[data-conversation-message-count]');
        if (countNode) countNode.textContent = Number(count).toLocaleString();
    }

    function nl2br(s) {
        return s.replace(/\n/g, '<br>');
    }

    function escHtml(s) {
        if (!s) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function startPolling() {
        clearInterval(pollTimer);
        pollTimer = setInterval(pollConversation, POLL_MS);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            pollConversation();
            startPolling();
        }
    });

    var replyInput = document.querySelector('textarea[name="message"]');
    if (replyInput) {
        replyInput.addEventListener('focus', function () { clearInterval(pollTimer); });
        replyInput.addEventListener('blur', function () { startPolling(); });
    }

    startPolling();
})();
</script>
@endsection
