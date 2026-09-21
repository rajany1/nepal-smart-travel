@extends('admin.layout')

@section('title', 'Support Inbox')

@section('content')
<div class="p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Support Inbox</h1>
            <p class="text-sm text-slate-500 mt-1">Manage user support conversations</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        <a href="{{ route('admin.support') }}" class="bg-white rounded-xl border border-slate-200 p-3 hover:shadow-md transition {{ !request('status') ? 'ring-2 ring-primary-500' : '' }}">
            <div class="text-2xl font-bold text-slate-900">{{ $stats['total'] }}</div>
            <div class="text-xs text-slate-500">All</div>
        </a>
        <a href="{{ route('admin.support', ['status' => 'open']) }}" class="bg-white rounded-xl border border-slate-200 p-3 hover:shadow-md transition {{ request('status') === 'open' ? 'ring-2 ring-blue-500' : '' }}">
            <div class="text-2xl font-bold text-blue-600">{{ $stats['open'] }}</div>
            <div class="text-xs text-slate-500">Open</div>
        </a>
        <a href="{{ route('admin.support', ['status' => 'ai_handling']) }}" class="bg-white rounded-xl border border-slate-200 p-3 hover:shadow-md transition {{ request('status') === 'ai_handling' ? 'ring-2 ring-purple-500' : '' }}">
            <div class="text-2xl font-bold text-purple-600">{{ $stats['ai_handling'] }}</div>
            <div class="text-xs text-slate-500">AI Handling</div>
        </a>
        <a href="{{ route('admin.support', ['status' => 'awaiting_human']) }}" class="bg-white rounded-xl border border-slate-200 p-3 hover:shadow-md transition {{ request('status') === 'awaiting_human' ? 'ring-2 ring-amber-500' : '' }}">
            <div class="text-2xl font-bold text-amber-600">{{ $stats['awaiting_human'] }}</div>
            <div class="text-xs text-slate-500">Awaiting Human</div>
        </a>
        <a href="{{ route('admin.support', ['status' => 'in_progress']) }}" class="bg-white rounded-xl border border-slate-200 p-3 hover:shadow-md transition {{ request('status') === 'in_progress' ? 'ring-2 ring-cyan-500' : '' }}">
            <div class="text-2xl font-bold text-cyan-600">{{ $stats['in_progress'] }}</div>
            <div class="text-xs text-slate-500">In Progress</div>
        </a>
        <a href="{{ route('admin.support', ['status' => 'resolved']) }}" class="bg-white rounded-xl border border-slate-200 p-3 hover:shadow-md transition {{ request('status') === 'resolved' ? 'ring-2 ring-green-500' : '' }}">
            <div class="text-2xl font-bold text-green-600">{{ $stats['resolved'] }}</div>
            <div class="text-xs text-slate-500">Resolved</div>
        </a>
        <a href="{{ route('admin.support', ['status' => 'open', 'assigned_to' => '']) }}" class="bg-white rounded-xl border border-slate-200 p-3 hover:shadow-md transition">
            <div class="text-2xl font-bold text-red-600">{{ $stats['unassigned'] }}</div>
            <div class="text-xs text-slate-500">Unassigned</div>
        </a>
    </div>

    <!-- CSAT Summary -->
    @if($stats['reviewed'] > 0)
    <div class="bg-white rounded-xl border border-slate-200 p-4 mb-6">
        <div class="flex items-center gap-6 text-sm">
            <div class="flex items-center gap-2">
                <i class="fas fa-star text-amber-400"></i>
                <span class="text-slate-600">Avg Rating:</span>
                <span class="font-bold text-slate-900">{{ $stats['avg_rating'] }}/5</span>
            </div>
            <div class="text-slate-400">|</div>
            <div>
                <span class="text-slate-600">Reviewed:</span>
                <span class="font-bold text-slate-900">{{ $stats['reviewed'] }}</span>
            </div>
            <div class="text-slate-400">|</div>
            <div class="flex items-center gap-1">
                @for($i = 5; $i >= 1; $i--)
                    @if(isset($stats['rating_distribution'][$i]))
                        <span class="text-xs text-slate-500">{{ $i }}★</span>
                        <span class="text-xs font-medium text-slate-700">{{ $stats['rating_distribution'][$i] }}</span>
                    @endif
                @endfor
            </div>
        </div>
    </div>
    @endif

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 mb-6">
        <form method="GET" action="{{ route('admin.support') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Subject or user name/email..."
                    class="w-full rounded-lg border-slate-300 text-sm focus:ring-primary-500 focus:border-primary-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                <select name="status" class="rounded-lg border-slate-300 text-sm focus:ring-primary-500">
                    <option value="">All</option>
                    @foreach(\App\Models\SupportConversation::STATUSES as $k => $v)
                        <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
                <select name="category" class="rounded-lg border-slate-300 text-sm focus:ring-primary-500">
                    <option value="">All</option>
                    @foreach(\App\Models\SupportConversation::CATEGORIES as $k => $v)
                        <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Priority</label>
                <select name="priority" class="rounded-lg border-slate-300 text-sm focus:ring-primary-500">
                    <option value="">All</option>
                    @foreach(\App\Models\SupportConversation::PRIORITIES as $k => $v)
                        <option value="{{ $k }}" {{ request('priority') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">Assigned To</label>
                <select name="assigned_to" class="rounded-lg border-slate-300 text-sm focus:ring-primary-500">
                    <option value="">Anyone</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}" {{ request('assigned_to') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-primary-500 text-white rounded-lg text-sm hover:bg-primary-600 transition">Filter</button>
            <a href="{{ route('admin.support') }}" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-lg text-sm hover:bg-slate-200 transition">Clear</a>
        </form>
    </div>

    <!-- Conversation List -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        @if($conversations->isEmpty())
            <div class="p-12 text-center text-slate-400">
                <i class="fas fa-inbox text-4xl mb-3"></i>
                <p>No conversations found.</p>
            </div>
        @else
            <div class="divide-y divide-slate-100">
                @foreach($conversations as $conv)
                    <a href="{{ route('admin.support.show', $conv->id) }}"
                       class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 transition">
                        <!-- Priority Indicator -->
                        <div class="w-1.5 h-10 rounded-full shrink-0
                            {{ $conv->priority === 'urgent' ? 'bg-red-500' : ($conv->priority === 'high' ? 'bg-amber-500' : ($conv->priority === 'normal' ? 'bg-blue-400' : 'bg-slate-300')) }}">
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-semibold text-sm text-slate-900 truncate">{{ $conv->subject }}</h3>
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold
                                    {{ $conv->status === 'open' ? 'bg-blue-100 text-blue-700' :
                                       ($conv->status === 'ai_handling' ? 'bg-purple-100 text-purple-700' :
                                       ($conv->status === 'awaiting_human' ? 'bg-amber-100 text-amber-700' :
                                       ($conv->status === 'in_progress' ? 'bg-cyan-100 text-cyan-700' :
                                       ($conv->status === 'resolved' ? 'bg-green-100 text-green-700' :
                                       'bg-slate-100 text-slate-600')))) }}">
                                    {{ $conv->statusLabel() }}
                                </span>
                                @if($conv->ai_handled)
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-600">
                                        <i class="fas fa-robot mr-1"></i>AI
                                    </span>
                                @endif
                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold
                                    {{ $conv->category === 'payment' || $conv->category === 'wallet' ? 'bg-red-50 text-red-600' :
                                       ($conv->category === 'abuse' || $conv->category === 'moderation' ? 'bg-orange-50 text-orange-600' :
                                       'bg-slate-100 text-slate-600') }}">
                                    {{ $conv->categoryLabel() }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-slate-500">
                                <span><i class="fas fa-user mr-1"></i>{{ $conv->user->name ?? 'Deleted' }}</span>
                                @if($conv->latestMessage)
                                    <span class="truncate max-w-[200px]">{{ Str::limit($conv->latestMessage->content, 60) }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Meta -->
                        <div class="text-right shrink-0 text-xs text-slate-400">
                            <div>{{ $conv->message_count }} msg{{ $conv->message_count !== 1 ? 's' : '' }}</div>
                            <div class="mt-1">{{ $conv->last_reply_at?->diffForHumans() ?? '—' }}</div>
                            @if($conv->assignee)
                                <div class="mt-1 text-primary-600 font-medium">{{ $conv->assignee->name }}</div>
                            @else
                                <div class="mt-1 text-amber-500 font-medium">Unassigned</div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Pagination -->
    @if($conversations->hasPages())
        <div class="mt-4">
            {{ $conversations->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
(function () {
    const POLL_MS = 8000;
    let pollTimer = null;
    let inFlight = false;

    function pollInbox() {
        if (inFlight || document.visibilityState === 'hidden') return;
        inFlight = true;

        const params = new URLSearchParams(window.location.search);
        const url = '{{ route("admin.support.poll-inbox") }}' + (params.toString() ? '?' + params.toString() : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { inFlight = false; return; }
                updateStats(data.stats);
                updateConversationList(data.conversations);
                inFlight = false;
            })
            .catch(() => { inFlight = false; });
    }

    function updateStats(stats) {
        const cards = document.querySelectorAll('.grid a > div:first-child');
        const values = [stats.total, stats.open, stats.ai_handling, stats.awaiting_human, stats.in_progress, stats.resolved, stats.unassigned];
        cards.forEach((el, i) => {
            if (values[i] !== undefined && el.textContent.trim() !== String(values[i])) {
                el.textContent = values[i];
            }
        });
    }

    function updateConversationList(conversations) {
        const container = document.querySelector('.divide-y');
        if (!container) return;

        const existingRows = container.querySelectorAll('a');
        const existingIds = new Set();
        existingRows.forEach(row => {
            const href = row.getAttribute('href');
            const match = href && href.match(/\/support\/(\d+)$/);
            if (match) existingIds.add(parseInt(match[1]));
        });

        const newIds = new Set(conversations.map(c => c.id));

        existingRows.forEach(row => {
            const href = row.getAttribute('href');
            const match = href && href.match(/\/support\/(\d+)$/);
            if (match && !newIds.has(parseInt(match[1]))) {
                row.remove();
            }
        });

        conversations.forEach(conv => {
            let row = container.querySelector('a[href$="/support/' + conv.id + '"]');
            if (row) {
                const badge = row.querySelector('.shrink-0.px-2.py-0\\.5.rounded-full');
                if (badge) {
                    const statusMap = {
                        'open': 'bg-blue-100 text-blue-700',
                        'ai_handling': 'bg-purple-100 text-purple-700',
                        'awaiting_human': 'bg-amber-100 text-amber-700',
                        'in_progress': 'bg-cyan-100 text-cyan-700',
                        'resolved': 'bg-green-100 text-green-700',
                    };
                    const cls = statusMap[conv.status] || 'bg-slate-100 text-slate-600';
                    badge.className = 'shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold ' + cls;
                    badge.textContent = conv.status_label;
                }
                const metaRight = row.querySelector('.text-right.shrink-0');
                if (metaRight) {
                    const divs = metaRight.querySelectorAll('div');
                    if (divs[0]) divs[0].textContent = conv.message_count + ' msg' + (conv.message_count !== 1 ? 's' : '');
                    if (divs[1]) divs[1].textContent = conv.last_reply_at;
                    if (divs[2]) {
                        divs[2].textContent = conv.assignee_name || 'Unassigned';
                        divs[2].className = 'mt-1 ' + (conv.assignee_name ? 'text-primary-600 font-medium' : 'text-amber-500 font-medium');
                    }
                }
            } else {
                rebuildList(conversations);
                return;
            }
        });
    }

    function rebuildList(conversations) {
        const container = document.querySelector('.divide-y');
        if (!container) return;
        if (conversations.length === 0) {
            container.parentElement.innerHTML = '<div class="p-12 text-center text-slate-400"><i class="fas fa-inbox text-4xl mb-3"></i><p>No conversations found.</p></div>';
            return;
        }
        container.innerHTML = conversations.map(c => {
            const priorityColor = c.priority === 'urgent' ? 'bg-red-500' : (c.priority === 'high' ? 'bg-amber-500' : (c.priority === 'normal' ? 'bg-blue-400' : 'bg-slate-300'));
            const statusMap = {
                'open': 'bg-blue-100 text-blue-700',
                'ai_handling': 'bg-purple-100 text-purple-700',
                'awaiting_human': 'bg-amber-100 text-amber-700',
                'in_progress': 'bg-cyan-100 text-cyan-700',
                'resolved': 'bg-green-100 text-green-700',
            };
            const statusCls = statusMap[c.status] || 'bg-slate-100 text-slate-600';
            const catCls = (c.category === 'payment' || c.category === 'wallet') ? 'bg-red-50 text-red-600' : ((c.category === 'abuse' || c.category === 'moderation') ? 'bg-orange-50 text-orange-600' : 'bg-slate-100 text-slate-600');
            return '<a href="' + c.url + '" class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 transition">'
                + '<div class="w-1.5 h-10 rounded-full shrink-0 ' + priorityColor + '"></div>'
                + '<div class="flex-1 min-w-0">'
                + '<div class="flex items-center gap-2 mb-1">'
                + '<h3 class="font-semibold text-sm text-slate-900 truncate">' + escHtml(c.subject) + '</h3>'
                + '<span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold ' + statusCls + '">' + escHtml(c.status_label) + '</span>'
                + (c.ai_handled ? '<span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-600"><i class="fas fa-robot mr-1"></i>AI</span>' : '')
                + '<span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold ' + catCls + '">' + escHtml(c.category_label) + '</span>'
                + '</div>'
                + '<div class="flex items-center gap-3 text-xs text-slate-500">'
                + '<span><i class="fas fa-user mr-1"></i>' + escHtml(c.user_name) + '</span>'
                + (c.latest_message ? '<span class="truncate max-w-[200px]">' + escHtml(c.latest_message) + '</span>' : '')
                + '</div></div>'
                + '<div class="text-right shrink-0 text-xs text-slate-400">'
                + '<div>' + c.message_count + ' msg' + (c.message_count !== 1 ? 's' : '') + '</div>'
                + '<div class="mt-1">' + escHtml(c.last_reply_at) + '</div>'
                + '<div class="mt-1 ' + (c.assignee_name ? 'text-primary-600 font-medium' : 'text-amber-500 font-medium') + '">' + escHtml(c.assignee_name || 'Unassigned') + '</div>'
                + '</div></a>';
        }).join('');
    }

    function escHtml(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function startPolling() {
        clearInterval(pollTimer);
        pollTimer = setInterval(pollInbox, POLL_MS);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            pollInbox();
            startPolling();
        }
    });

    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('focus', () => { clearInterval(pollTimer); });
        searchInput.addEventListener('blur', () => { startPolling(); });
    }

    startPolling();
})();
</script>
@endsection
