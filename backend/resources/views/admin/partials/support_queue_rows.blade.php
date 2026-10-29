@php
    $statusStyles = [
        'open' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'ai_handling' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'awaiting_human' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'in_progress' => 'bg-primary-50 text-primary-700 ring-primary-200',
        'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'closed' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $priorityStyles = [
        'urgent' => ['label' => 'Urgent', 'badge' => 'bg-rose-50 text-rose-700 ring-rose-200', 'dot' => 'bg-rose-500'],
        'high' => ['label' => 'High', 'badge' => 'bg-orange-50 text-orange-700 ring-orange-200', 'dot' => 'bg-orange-500'],
        'normal' => ['label' => 'Normal', 'badge' => 'bg-sky-50 text-sky-700 ring-sky-200', 'dot' => 'bg-sky-500'],
        'low' => ['label' => 'Low', 'badge' => 'bg-slate-100 text-slate-600 ring-slate-200', 'dot' => 'bg-slate-400'],
    ];
    $categoryStyles = [
        'payment' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'wallet' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'abuse' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'moderation' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'bug' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'booking' => 'bg-primary-50 text-primary-700 ring-primary-200',
        'feature_request' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    ];
@endphp

@forelse($conversations as $conv)
    @php
        $priority = $priorityStyles[$conv->priority] ?? $priorityStyles['normal'];
        $categoryClass = $categoryStyles[$conv->category] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
    @endphp
    <tr class="transition hover:bg-slate-50/70">
        <td class="px-5 py-3.5">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 h-9 w-1 flex-shrink-0 rounded-full {{ $priority['dot'] }}"></span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <a href="{{ route('admin.support.show', $conv->id) }}" class="max-w-[340px] truncate font-medium text-slate-800 transition hover:text-primary-700 hover:underline">{{ $conv->subject }}</a>
                        @if($conv->ai_handled)
                            <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 ring-1 ring-indigo-200">
                                <i class="fas fa-robot text-[9px]"></i> AI
                            </span>
                        @endif
                    </div>
                    <div class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400">
                        <span>#{{ $conv->id }}</span>
                        @if($conv->latestMessage)
                            <span class="truncate">{{ \Illuminate\Support\Str::limit($conv->latestMessage->content, 76) }}</span>
                        @else
                            <span>No messages yet</span>
                        @endif
                    </div>
                </div>
            </div>
        </td>
        <td class="px-4 py-3.5">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">{{ strtoupper(mb_substr($conv->user->name ?? 'D', 0, 1)) }}</span>
                <div class="min-w-0">
                    <div class="truncate text-xs font-medium text-slate-700">{{ $conv->user->name ?? 'Deleted user' }}</div>
                    <div class="truncate text-[11px] text-slate-400">{{ $conv->user->email ?? '—' }}</div>
                </div>
            </div>
        </td>
        <td class="px-4 py-3.5">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $priority['badge'] }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $priority['dot'] }}"></span>{{ $priority['label'] }}
            </span>
        </td>
        <td class="px-4 py-3.5">
            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $statusStyles[$conv->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $conv->statusLabel() }}</span>
        </td>
        <td class="px-4 py-3.5">
            <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium ring-1 {{ $categoryClass }}">{{ $conv->categoryLabel() }}</span>
        </td>
        <td class="px-4 py-3.5">
            @if($conv->assignee)
                <div class="flex items-center gap-1.5 text-xs font-medium text-primary-700">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-50 text-[10px] font-bold text-primary-700">{{ strtoupper(mb_substr($conv->assignee->name, 0, 1)) }}</span>
                    <span class="max-w-[110px] truncate">{{ $conv->assignee->name }}</span>
                </div>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200">
                    <i class="fas fa-user-clock text-[9px]"></i> Unassigned
                </span>
            @endif
        </td>
        <td class="px-4 py-3.5">
            <div class="text-xs font-medium text-slate-600">{{ number_format($conv->message_count) }} message{{ $conv->message_count === 1 ? '' : 's' }}</div>
            <div class="mt-0.5 text-[11px] text-slate-400">{{ $conv->last_reply_at?->diffForHumans() ?? 'No activity' }}</div>
        </td>
        <td class="px-5 py-3.5 text-right">
            <a href="{{ route('admin.support.show', $conv->id) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                <i class="fas fa-arrow-right"></i> Open
            </a>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="px-5 py-14 text-center">
            <i class="fas fa-headset mb-3 block text-3xl {{ $conversations->total() === 0 ? 'text-emerald-400' : 'text-slate-300' }}"></i>
            <p class="text-sm font-medium text-slate-600">{{ $conversations->total() === 0 ? 'No conversations in this view' : 'No conversations match these filters' }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $conversations->total() === 0 ? 'New support requests will appear here as soon as they arrive.' : 'Try a different status, category, priority or search term.' }}</p>
            <a href="{{ route('admin.support', ['status' => 'all']) }}" class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                <i class="fas fa-inbox"></i> View all conversations
            </a>
        </td>
    </tr>
@endforelse
