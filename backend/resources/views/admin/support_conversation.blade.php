@extends('admin.layout')

@section('title', $conversation->subject . ' - Support')

@section('content')
<div class="flex h-[calc(100vh-4rem)]">
    <!-- Conversation Thread -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 px-6 py-4 shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.support') }}" class="text-slate-400 hover:text-slate-600 transition">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg font-bold text-slate-900">{{ $conversation->subject }}</h1>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
                                {{ $conversation->status === 'open' ? 'bg-blue-100 text-blue-700' :
                                   ($conversation->status === 'ai_handling' ? 'bg-purple-100 text-purple-700' :
                                   ($conversation->status === 'awaiting_human' ? 'bg-amber-100 text-amber-700' :
                                   ($conversation->status === 'in_progress' ? 'bg-cyan-100 text-cyan-700' :
                                   ($conversation->status === 'resolved' ? 'bg-green-100 text-green-700' :
                                   'bg-slate-100 text-slate-600')))) }}">
                                {{ $conversation->statusLabel() }}
                            </span>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-slate-500 mt-1">
                            <span>From: <strong>{{ $conversation->user->name ?? 'Deleted' }}</strong> ({{ $conversation->user->email ?? '' }})</span>
                            <span>Category: {{ $conversation->categoryLabel() }}</span>
                            <span>Priority: {{ $conversation->priorityLabel() }}</span>
                            <span>{{ $conversation->message_count }} messages</span>
                            <span>Created: {{ $conversation->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($conversation->assignee)
                        <span class="text-xs text-slate-500">Assigned: <strong>{{ $conversation->assignee->name }}</strong></span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4" id="messages-container">
            @if($hasMoreMessages)
            <div class="text-center py-2" id="load-earlier-wrapper">
                <button onclick="loadEarlier()" id="load-earlier-btn"
                    class="px-4 py-2 text-xs font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 transition">
                    <i class="fas fa-arrow-up mr-1"></i>Load earlier messages
                </button>
                <div class="text-[10px] text-slate-400 mt-1">Showing {{ $conversation->messages->count() }} of {{ $totalMessages }} messages</div>
            </div>
            @endif
            @forelse($conversation->messages as $msg)
                <div class="flex gap-3 {{ $msg->sender_type === 'user' ? '' : ($msg->sender_type === 'ai' ? 'flex-row-reverse' : '') }}">
                    <!-- Avatar -->
                    <div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-white text-xs font-bold
                        {{ $msg->sender_type === 'user' ? 'bg-blue-500' : ($msg->sender_type === 'ai' ? 'bg-purple-500' : ($msg->sender_type === 'system' ? 'bg-slate-400' : 'bg-primary-500')) }}">
                        @if($msg->sender_type === 'user')
                            {{ strtoupper(substr($conversation->user->name ?? 'U', 0, 1)) }}
                        @elseif($msg->sender_type === 'ai')
                            <i class="fas fa-robot text-[10px]"></i>
                        @elseif($msg->sender_type === 'system')
                            <i class="fas fa-cog text-[10px]"></i>
                        @else
                            S
                        @endif
                    </div>

                    <!-- Message Bubble -->
                    <div class="max-w-[70%] {{ $msg->sender_type === 'ai' ? 'text-right' : '' }}">
                        <div class="flex items-center gap-2 mb-1 {{ $msg->sender_type === 'ai' ? 'justify-end' : '' }}">
                            <span class="text-xs font-semibold
                                {{ $msg->sender_type === 'user' ? 'text-blue-600' : ($msg->sender_type === 'ai' ? 'text-purple-600' : ($msg->sender_type === 'system' ? 'text-slate-400' : 'text-primary-600')) }}">
                                @if($msg->sender_type === 'user')
                                    {{ $conversation->user->name ?? 'User' }}
                                @elseif($msg->sender_type === 'ai')
                                    AI Assistant
                                    @if($msg->ai_model)
                                        <span class="text-[10px] font-normal text-slate-400">({{ $msg->ai_model }})</span>
                                    @endif
                                @elseif($msg->sender_type === 'system')
                                    System
                                @else
                                    {{ $msg->user->name ?? 'Staff' }}
                                @endif
                            </span>
                            <span class="text-[10px] text-slate-400">{{ $msg->created_at->format('M d, g:ia') }}</span>
                            @if($msg->is_internal_note)
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-amber-100 text-amber-700">INTERNAL NOTE</span>
                            @endif
                            @if($msg->ai_confidence !== null)
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-purple-50 text-purple-600">
                                    confidence: {{ number_format($msg->ai_confidence * 100, 0) }}%
                                </span>
                            @endif
                        </div>
                        <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed
                            {{ $msg->sender_type === 'user' ? 'bg-blue-50 text-slate-800' :
                               ($msg->sender_type === 'ai' ? 'bg-purple-50 text-slate-800' :
                               ($msg->sender_type === 'system' ? 'bg-slate-100 text-slate-500 italic' :
                               'bg-primary-50 text-slate-800')) }}">
                            {!! nl2br(e($msg->content)) !!}
                        </div>
                        @if($msg->ai_actions && count($msg->ai_actions) > 0)
                            <div class="mt-1 text-[10px] text-slate-400">
                                Actions: {{ json_encode($msg->ai_actions) }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-center text-slate-400 py-12">
                    <p>No messages yet.</p>
                </div>
            @endforelse
        </div>

        <!-- Reply Box -->
        @if($conversation->status !== 'closed')
        <div class="bg-white border-t border-slate-200 px-6 py-4 shrink-0">
            <form method="POST" action="{{ route('admin.support.reply', $conversation->id) }}" id="reply-form">
                @csrf
                <div class="flex gap-3">
                    <div class="flex-1">
                        <textarea name="message" rows="3" placeholder="Type your reply..."
                            class="w-full rounded-xl border-slate-300 text-sm focus:ring-primary-500 focus:border-primary-500 resize-none"
                            required></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <label class="flex items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="is_internal_note" value="1" class="rounded border-slate-300 text-amber-500 focus:ring-amber-500">
                                Internal note (not visible to user)
                            </label>
                            <button type="submit" class="px-5 py-2 bg-primary-500 text-white rounded-xl text-sm font-medium hover:bg-primary-600 transition">
                                <i class="fas fa-paper-plane mr-1"></i>Send Reply
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        @else
        <div class="bg-slate-50 border-t border-slate-200 px-6 py-4 text-center shrink-0">
            <p class="text-sm text-slate-500 mb-2">This conversation is closed.</p>
            <form method="POST" action="{{ route('admin.support.reopen', $conversation->id) }}" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm hover:bg-amber-600 transition">
                    <i class="fas fa-redo mr-1"></i>Reopen Conversation
                </button>
            </form>
        </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="w-72 bg-white border-l border-slate-200 shrink-0 overflow-y-auto">
        <div class="p-5 space-y-5">
            <!-- Actions -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Actions</h3>
                <div class="space-y-2">
                    @if($conversation->assigned_to !== auth()->id())
                    <form method="POST" action="{{ route('admin.support.takeover', $conversation->id) }}">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 bg-primary-500 text-white rounded-lg text-xs font-medium hover:bg-primary-600 transition">
                            <i class="fas fa-hand-paper mr-1"></i>Take Over
                        </button>
                    </form>
                    @endif

                    @if($conversation->status === 'ai_handling')
                    <form method="POST" action="{{ route('admin.support.stop-ai', $conversation->id) }}">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition">
                            <i class="fas fa-robot mr-1"></i>Stop AI / Escalate
                        </button>
                    </form>
                    @endif

                    @if(in_array($conversation->status, ['open', 'ai_handling', 'awaiting_human', 'in_progress']))
                    <form method="POST" action="{{ route('admin.support.resolve', $conversation->id) }}">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 bg-green-500 text-white rounded-lg text-xs font-medium hover:bg-green-600 transition">
                            <i class="fas fa-check mr-1"></i>Resolve
                        </button>
                    </form>
                    @endif

                    @if($conversation->status !== 'closed')
                    <form method="POST" action="{{ route('admin.support.close', $conversation->id) }}">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 bg-slate-500 text-white rounded-lg text-xs font-medium hover:bg-slate-600 transition">
                            <i class="fas fa-times mr-1"></i>Close
                        </button>
                    </form>
                    @endif
                </div>
            </div>

            <!-- Assignment -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Assignment</h3>
                <form method="POST" action="{{ route('admin.support.assign', $conversation->id) }}">
                    @csrf
                    <select name="assigned_to" class="w-full rounded-lg border-slate-300 text-xs focus:ring-primary-500" onchange="this.form.submit()">
                        <option value="">Unassigned</option>
                        @foreach($staff as $s)
                            <option value="{{ $s->id }}" {{ $conversation->assigned_to === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <!-- Category -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Category</h3>
                <form method="POST" action="{{ route('admin.support.category', $conversation->id) }}">
                    @csrf
                    <select name="category" class="w-full rounded-lg border-slate-300 text-xs focus:ring-primary-500" onchange="this.form.submit()">
                        @foreach(\App\Models\SupportConversation::CATEGORIES as $k => $v)
                            <option value="{{ $k }}" {{ $conversation->category === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <!-- Priority -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Priority</h3>
                <form method="POST" action="{{ route('admin.support.priority', $conversation->id) }}">
                    @csrf
                    <select name="priority" class="w-full rounded-lg border-slate-300 text-xs focus:ring-primary-500" onchange="this.form.submit()">
                        @foreach(\App\Models\SupportConversation::PRIORITIES as $k => $v)
                            <option value="{{ $k }}" {{ $conversation->priority === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <!-- User Info -->
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">User</h3>
                <div class="bg-slate-50 rounded-lg p-3 text-xs space-y-1">
                    <div class="font-medium text-slate-900">{{ $conversation->user->name ?? 'Deleted' }}</div>
                    <div class="text-slate-500">{{ $conversation->user->email ?? '' }}</div>
                    <div class="text-slate-500">{{ $conversation->user->phone ?? '' }}</div>
                    @if($conversation->user)
                    <div class="text-slate-400 mt-2">
                        Joined: {{ $conversation->user->created_at->format('M d, Y') }}
                    </div>
                    @endif
                </div>
            </div>

            <!-- AI Info -->
            @if($conversation->ai_handled)
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">AI Handling</h3>
                <div class="bg-purple-50 rounded-lg p-3 text-xs space-y-1">
                    <div class="text-purple-700">AI responded to this conversation</div>
                    @if($conversation->ai_confidence !== null)
                        <div>Avg confidence: {{ number_format($conversation->ai_confidence * 100, 0) }}%</div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Satisfaction Review -->
            @if($conversation->satisfaction)
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Satisfaction Review</h3>
                <div class="bg-green-50 rounded-lg p-3 text-xs space-y-2">
                    <div class="flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star {{ $i <= $conversation->satisfaction->rating ? 'text-amber-400' : 'text-slate-300' }}"></i>
                        @endfor
                        <span class="ml-1 text-slate-600 font-medium">{{ $conversation->satisfaction->rating }}/5</span>
                    </div>
                    <div class="text-slate-500">{{ $conversation->satisfaction->ratingLabel() }}</div>
                    @if($conversation->satisfaction->comment)
                        <div class="text-slate-600 italic">"{{ $conversation->satisfaction->comment }}"</div>
                    @endif
                    <div class="text-slate-400">Submitted: {{ $conversation->satisfaction->created_at->diffForHumans() }}</div>
                </div>
            </div>
            @elseif(in_array($conversation->status, ['resolved', 'closed']))
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Satisfaction Review</h3>
                <div class="bg-slate-50 rounded-lg p-3 text-xs text-slate-400">
                    No review submitted yet
                </div>
            </div>
            @endif

            <!-- Metadata -->
            @if($conversation->metadata)
            <div>
                <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Metadata</h3>
                <div class="bg-slate-50 rounded-lg p-3 text-xs">
                    <pre class="whitespace-pre-wrap text-slate-600">{{ json_encode($conversation->metadata, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif

            <!-- Conversation ID -->
            <div class="text-[10px] text-slate-400">
                Conversation #{{ $conversation->id }}
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    const POLL_MS = 8000;
    const conversationId = {{ $conversation->id }};
    let lastMessageId = {{ $conversation->messages->count() > 0 ? $conversation->messages->last()->id : 0 }};
    let firstMessageId = {{ $conversation->messages->count() > 0 ? $conversation->messages->first()->id : 0 }};
    let pollTimer = null;
    let inFlight = false;
    let isNearBottom = true;
    let loadingEarlier = false;
    let hasMoreMessages = {{ $hasMoreMessages ? 'true' : 'false' }};

    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
        container.addEventListener('scroll', () => {
            const threshold = 100;
            isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < threshold;
        });
    }

    window.loadEarlier = function() {
        if (loadingEarlier || !hasMoreMessages) return;
        loadingEarlier = true;
        const btn = document.getElementById('load-earlier-btn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Loading...';
            btn.disabled = true;
        }

        const scrollHeightBefore = container.scrollHeight;

        fetch('/admin/support/' + conversationId + '/poll?before_message_id=' + firstMessageId + '&limit=50', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.messages || data.messages.length === 0) {
                hasMoreMessages = false;
                const wrapper = document.getElementById('load-earlier-wrapper');
                if (wrapper) wrapper.remove();
                loadingEarlier = false;
                return;
            }

            const html = buildMessagesHtml(data.messages);
            const firstChild = container.querySelector('.flex.gap-3');
            if (firstChild) {
                firstChild.insertAdjacentHTML('beforebegin', html);
            } else {
                container.insertAdjacentHTML('afterbegin', html);
            }

            // Maintain scroll position
            const scrollHeightAfter = container.scrollHeight;
            container.scrollTop = scrollHeightAfter - scrollHeightBefore;

            firstMessageId = data.messages[0].id;
            hasMoreMessages = data.has_more;

            const loadedCount = container.querySelectorAll('.flex.gap-3').length;
            if (!hasMoreMessages) {
                const wrapper = document.getElementById('load-earlier-wrapper');
                if (wrapper) wrapper.remove();
            } else {
                if (btn) {
                    btn.innerHTML = '<i class="fas fa-arrow-up mr-1"></i>Load earlier messages';
                    btn.disabled = false;
                }
                const counter = document.querySelector('#load-earlier-wrapper .text-[10px]');
                if (counter) counter.textContent = 'Showing ' + loadedCount + ' of ' + {{ $totalMessages }} + ' messages';
            }
            loadingEarlier = false;
        })
        .catch(() => {
            loadingEarlier = false;
            if (btn) {
                btn.innerHTML = '<i class="fas fa-arrow-up mr-1"></i>Load earlier messages';
                btn.disabled = false;
            }
        });
    };

    function buildMessagesHtml(messages) {
        return messages.map(msg => {
            const isUser = msg.sender_type === 'user';
            const isAI = msg.sender_type === 'ai';
            const isSystem = msg.sender_type === 'system';
            const alignment = isAI ? 'flex-row-reverse' : '';
            const avatarColor = isUser ? 'bg-blue-500' : (isAI ? 'bg-purple-500' : (isSystem ? 'bg-slate-400' : 'bg-primary-500'));
            const avatarContent = isUser ? escHtml(msg.avatar_letter) : (isAI ? '<i class="fas fa-robot text-[10px]"></i>' : (isSystem ? '<i class="fas fa-cog text-[10px]"></i>' : 'S'));
            const nameColor = isUser ? 'text-blue-600' : (isAI ? 'text-purple-600' : (isSystem ? 'text-slate-400' : 'text-primary-600'));
            const nameAlign = isAI ? 'justify-end' : '';
            const contentAlign = isAI ? 'text-right' : '';
            const bubbleColor = isUser ? 'bg-blue-50 text-slate-800' : (isAI ? 'bg-purple-50 text-slate-800' : (isSystem ? 'bg-slate-100 text-slate-500 italic' : 'bg-primary-50 text-slate-800'));
            const noteHtml = msg.is_internal_note ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-amber-100 text-amber-700">INTERNAL NOTE</span>' : '';
            const confidenceHtml = msg.ai_confidence !== null ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-purple-50 text-purple-600">confidence: ' + Math.round(msg.ai_confidence * 100) + '%</span>' : '';
            const modelHtml = msg.ai_model ? '<span class="text-[10px] font-normal text-slate-400">(' + escHtml(msg.ai_model) + ')</span>' : '';

            return '<div class="flex gap-3 ' + alignment + '">'
                + '<div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-white text-xs font-bold ' + avatarColor + '">' + avatarContent + '</div>'
                + '<div class="max-w-[70%] ' + contentAlign + '">'
                + '<div class="flex items-center gap-2 mb-1 ' + nameAlign + '">'
                + '<span class="text-xs font-semibold ' + nameColor + '">' + escHtml(msg.user_name) + ' ' + modelHtml + '</span>'
                + '<span class="text-[10px] text-slate-400">' + escHtml(msg.created_at) + '</span>'
                + noteHtml + ' ' + confidenceHtml
                + '</div>'
                + '<div class="rounded-2xl px-4 py-3 text-sm leading-relaxed ' + bubbleColor + '">' + nl2br(escHtml(msg.content)) + '</div>'
                + '</div></div>';
        }).join('');
    }

    function pollConversation() {
        if (inFlight || document.visibilityState === 'hidden') return;
        inFlight = true;

        const url = '/admin/support/' + conversationId + '/poll?since_id=' + lastMessageId;

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.messages || data.messages.length === 0) {
                    inFlight = false;
                    return;
                }
                appendMessages(data.messages);
                lastMessageId = data.messages[data.messages.length - 1].id;
                updateHeaderStatus(data.status, data.status_label, data.message_count);
                inFlight = false;
            })
            .catch(() => { inFlight = false; });
    }

    function appendMessages(messages) {
        if (!container) return;
        messages.forEach(msg => {
            const isUser = msg.sender_type === 'user';
            const isAI = msg.sender_type === 'ai';
            const isSystem = msg.sender_type === 'system';
            const isStaff = msg.sender_type === 'support';

            const bubbleColor = isUser ? 'bg-blue-50 text-slate-800' : (isAI ? 'bg-purple-50 text-slate-800' : (isSystem ? 'bg-slate-100 text-slate-500 italic' : 'bg-primary-50 text-slate-800'));
            const nameColor = isUser ? 'text-blue-600' : (isAI ? 'text-purple-600' : (isSystem ? 'text-slate-400' : 'text-primary-600'));
            const avatarColor = isUser ? 'bg-blue-500' : (isAI ? 'bg-purple-500' : (isSystem ? 'bg-slate-400' : 'bg-primary-500'));
            const avatarContent = isUser ? escHtml(msg.avatar_letter) : (isAI ? '<i class="fas fa-robot text-[10px]"></i>' : (isSystem ? '<i class="fas fa-cog text-[10px]"></i>' : 'S'));
            const alignment = isAI ? 'flex-row-reverse' : '';
            const nameAlign = isAI ? 'justify-end' : '';
            const contentAlign = isAI ? 'text-right' : '';
            const noteHtml = msg.is_internal_note ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-amber-100 text-amber-700">INTERNAL NOTE</span>' : '';
            const confidenceHtml = msg.ai_confidence !== null ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-purple-50 text-purple-600">confidence: ' + Math.round(msg.ai_confidence * 100) + '%</span>' : '';
            const modelHtml = msg.ai_model ? '<span class="text-[10px] font-normal text-slate-400">(' + escHtml(msg.ai_model) + ')</span>' : '';

            const html = '<div class="flex gap-3 ' + alignment + '">'
                + '<div class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center text-white text-xs font-bold ' + avatarColor + '">' + avatarContent + '</div>'
                + '<div class="max-w-[70%] ' + contentAlign + '">'
                + '<div class="flex items-center gap-2 mb-1 ' + nameAlign + '">'
                + '<span class="text-xs font-semibold ' + nameColor + '">' + escHtml(msg.user_name) + ' ' + modelHtml + '</span>'
                + '<span class="text-[10px] text-slate-400">' + escHtml(msg.created_at) + '</span>'
                + noteHtml + ' ' + confidenceHtml
                + '</div>'
                + '<div class="rounded-2xl px-4 py-3 text-sm leading-relaxed ' + bubbleColor + '">' + nl2br(escHtml(msg.content)) + '</div>'
                + '</div></div>';

            container.insertAdjacentHTML('beforeend', html);
        });

        if (isNearBottom) {
            container.scrollTop = container.scrollHeight;
        }
    }

    function updateHeaderStatus(status, label, count) {
        const badge = document.querySelector('.shrink-0.px-2.py-0\\.5.rounded-full');
        if (badge) {
            const statusMap = {
                'open': 'bg-blue-100 text-blue-700',
                'ai_handling': 'bg-purple-100 text-purple-700',
                'awaiting_human': 'bg-amber-100 text-amber-700',
                'in_progress': 'bg-cyan-100 text-cyan-700',
                'resolved': 'bg-green-100 text-green-700',
            };
            const cls = statusMap[status] || 'bg-slate-100 text-slate-600';
            badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-semibold ' + cls;
            badge.textContent = label;
        }
        const countSpan = document.querySelector('.flex.items-center.gap-4.text-xs.text-slate-500 span:last-child');
        if (countSpan) countSpan.textContent = count + ' messages';
    }

    function nl2br(s) {
        return s.replace(/\n/g, '<br>');
    }

    function escHtml(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function startPolling() {
        clearInterval(pollTimer);
        pollTimer = setInterval(pollConversation, POLL_MS);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            pollConversation();
            startPolling();
        }
    });

    const replyInput = document.querySelector('textarea[name="message"]');
    if (replyInput) {
        replyInput.addEventListener('focus', () => { clearInterval(pollTimer); });
        replyInput.addEventListener('blur', () => { startPolling(); });
    }

    startPolling();
})();
</script>
@endsection
