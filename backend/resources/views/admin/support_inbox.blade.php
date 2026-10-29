@extends('admin.layout')

@section('title', 'Support Inbox')

@section('content')
@php
    $currentStatusTab = $filters['status'] ?: 'active';
    $statusTabUrl = function (string $key) {
        $params = request()->except(['status', 'page', 'fragment']);
        if ($key === 'all') {
            $params['status'] = 'all';
        } elseif ($key !== 'active') {
            $params['status'] = $key;
        }
        return route('admin.support', $params);
    };
    $filterUrl = function (array $remove = []) {
        return route('admin.support', request()->except(array_merge(['page', 'fragment'], $remove)));
    };

    $activeFilters = [];
    if ($filters['search']) {
        $activeFilters[] = ['label' => 'Search: "'.$filters['search'].'"', 'remove' => 'search'];
    }
    if ($filters['category']) {
        $activeFilters[] = ['label' => 'Category: '.(\App\Models\SupportConversation::CATEGORIES[$filters['category']] ?? $filters['category']), 'remove' => 'category'];
    }
    if ($filters['priority']) {
        $activeFilters[] = ['label' => 'Priority: '.(\App\Models\SupportConversation::PRIORITIES[$filters['priority']] ?? $filters['priority']), 'remove' => 'priority'];
    }
    if ($filters['assigned_to'] === 0) {
        $activeFilters[] = ['label' => 'Assignee: Unassigned', 'remove' => 'assigned_to'];
    } elseif ($filters['assigned_to']) {
        $assignee = $staff->firstWhere('id', $filters['assigned_to']);
        $activeFilters[] = ['label' => 'Assignee: '.($assignee->name ?? '#'.$filters['assigned_to']), 'remove' => 'assigned_to'];
    }
    $hasFilters = count($activeFilters) > 0;

    $resetUrl = route('admin.support', $filters['status'] ? ['status' => $filters['status']] : []);
    $refreshUrl = route('admin.support', request()->except('fragment'));
    $kpiKeys = ['active', 'awaiting_human', 'unassigned', 'satisfaction'];
    $kpiLinks = [
        $statusTabUrl('active'),
        $statusTabUrl('awaiting_human'),
        route('admin.support', array_merge(request()->except(['assigned_to', 'page', 'fragment']), ['assigned_to' => 'unassigned'])),
        null,
    ];
    $statusLabels = \App\Models\SupportConversation::STATUSES;
@endphp

<div id="adminSupport" class="space-y-5 p-4 sm:p-5 lg:p-6">
    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-accent-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-primary-200">Customer care &middot; Support</p>
                <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Support command center</h1>
                <p class="mt-1 max-w-xl text-sm leading-relaxed text-primary-100">Triage conversations, keep the human queue moving and measure the experience from first message to CSAT.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-comments text-[10px]"></i>{{ number_format($stats['total']) }} conversations
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-inbox text-[10px]"></i>{{ number_format($stats['active']) }} active
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-message text-[10px]"></i>{{ number_format($stats['messages']) }} messages
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-clock text-[10px]"></i>Nepal time
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <span data-support-queue-badge class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-semibold {{ $stats['awaiting_human'] > 0 ? 'bg-amber-400/15 text-amber-100' : 'bg-emerald-400/15 text-emerald-100' }}">
                        <span class="h-2 w-2 rounded-full {{ $stats['awaiting_human'] > 0 ? 'bg-amber-300' : 'bg-emerald-300' }}"></span>
                        {{ $stats['awaiting_human'] > 0 ? number_format($stats['awaiting_human']).' awaiting human' : ($stats['active'] > 0 ? 'Human queue clear' : 'Inbox clear') }}
                    </span>
                    <a href="{{ $refreshUrl }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20">
                        <i class="fas fa-rotate-right"></i> Refresh
                    </a>
                    <a href="{{ $statusTabUrl('all') }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                        <i class="fas fa-inbox"></i> All conversations
                    </a>
                </div>
                <p class="text-xs text-primary-200">Snapshot generated at <span data-support-snapshot>{{ $insights['generated_at'] }}</span></p>
            </div>
        </div>
    </section>

    {{-- KPI cards --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($insights['kpis'] as $index => $kpi)
            @php $key = $kpiKeys[$index]; @endphp
            @if($kpiLinks[$index])
                <a href="{{ $kpiLinks[$index] }}" class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md">
            @else
                <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            @endif
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900" data-support-stat-value="{{ $key }}">{{ $kpi['value'] }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-2xl {{ $kpi['icon_bg'] }}">
                        <i class="fas fa-{{ $kpi['icon'] }} text-sm"></i>
                    </span>
                </div>
                <p class="mt-2 text-xs leading-relaxed {{ $kpi['hint_class'] }}" data-support-stat-hint="{{ $key }}">{{ $kpi['hint'] }}</p>
            @if($kpiLinks[$index])
                </a>
            @else
                </div>
            @endif
        @endforeach
    </section>

    {{-- Analytics --}}
    <section class="grid gap-3 xl:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Inbox activity</h2>
                    <p class="mt-1 text-xs text-slate-500">New conversations and staff replies over the last 30 days.</p>
                </div>
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 font-semibold text-primary-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                        {{ number_format($insights['trend']['conversations_total']) }} new
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        {{ number_format($insights['trend']['replies_total']) }} replies
                    </span>
                </div>
            </div>
            <div class="relative mt-4 h-64">
                <canvas id="supportTrendChart" aria-label="New support conversations and staff replies over the last 30 days" role="img"></canvas>
                <div id="supportTrendFallback" class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                    Chart library unavailable &mdash; totals are shown above.
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-700/70"></span> New conversations</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-amber-500"></span> Staff replies</span>
                @if($stats['today'] === 0)
                    <span class="ml-auto text-slate-400">No new conversations today</span>
                @else
                    <span class="ml-auto font-semibold text-slate-600">{{ number_format($stats['today']) }} today</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Breakdown</h2>
                    <p class="mt-1 text-xs text-slate-500">Across all {{ number_format($stats['total']) }} conversations.</p>
                </div>
                <div class="flex flex-wrap justify-end gap-0.5 rounded-xl bg-slate-100 p-0.5 text-[10px] font-semibold">
                    <button type="button" data-support-breakdown-tab="status" class="rounded-lg bg-white px-2 py-1.5 text-slate-900 shadow-sm">Status</button>
                    <button type="button" data-support-breakdown-tab="category" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Category</button>
                    <button type="button" data-support-breakdown-tab="priority" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Priority</button>
                    <button type="button" data-support-breakdown-tab="satisfaction" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">CSAT</button>
                </div>
            </div>

            <div data-support-breakdown-panel="status" class="mt-4 flex-1 space-y-3">
                @foreach($insights['statuses'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="inline-flex items-center gap-2 text-slate-600"><span class="h-2 w-2 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span> &middot; {{ number_format($row['pct'], 1) }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
                <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Active includes open, AI handling, awaiting human and in progress.</p>
            </div>

            <div data-support-breakdown-panel="category" class="mt-4 hidden flex-1 space-y-3">
                @forelse($insights['categories'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="min-w-0 truncate font-medium text-slate-700">{{ $row['label'] }}</span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span> &middot; {{ number_format($row['pct'], 1) }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-slate-400">No category data yet.</p>
                @endforelse
                @if(count($insights['categories']) > 0)
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Top {{ count($insights['categories']) }} categories by conversation volume.</p>
                @endif
            </div>

            <div data-support-breakdown-panel="priority" class="mt-4 hidden flex-1 space-y-3">
                @foreach($insights['priorities'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="inline-flex items-center gap-2 text-slate-600"><span class="h-2 w-2 rounded-full" style="background: {{ $row['color'] }}"></span>{{ $row['label'] }}</span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span> &middot; {{ number_format($row['pct'], 1) }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
                <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">{{ number_format($stats['urgent_active']) }} urgent and {{ number_format($stats['high_active']) }} high-priority conversations are still active.</p>
            </div>

            <div id="supportSatisfaction" data-support-breakdown-panel="satisfaction" class="mt-4 hidden flex-1 flex-col">
                @if($stats['reviewed'] > 0)
                    <div class="flex items-center gap-4 rounded-2xl bg-slate-50 px-3 py-3">
                        <div>
                            <p class="text-3xl font-semibold tracking-tight text-slate-900">{{ number_format((float) $stats['avg_rating'], 1) }}<span class="text-sm font-normal text-slate-400">/5</span></p>
                            <div class="mt-1 flex items-center gap-0.5 text-[10px] text-amber-400">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="fas fa-star {{ $i <= round((float) $stats['avg_rating']) ? '' : 'text-slate-300' }}"></i>
                                @endfor
                            </div>
                        </div>
                        <div class="text-xs text-slate-500">
                            <span class="font-semibold text-slate-700">{{ number_format($stats['reviewed']) }}</span> review{{ $stats['reviewed'] === 1 ? '' : 's' }}
                            <div class="mt-1 text-[10px] text-slate-400">Submitted after resolution</div>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2.5">
                        @foreach($insights['satisfaction'] as $row)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-12 flex-shrink-0 text-slate-500">{{ $row['label'] }}</span>
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                                </div>
                                <span class="w-8 flex-shrink-0 text-right font-semibold text-slate-700">{{ number_format($row['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-1 flex-col items-center justify-center rounded-2xl bg-slate-50 px-4 py-8 text-center">
                        <i class="fas fa-star mb-2 text-2xl text-slate-300"></i>
                        <p class="text-xs font-medium text-slate-600">No satisfaction reviews yet</p>
                        <p class="mt-1 text-[10px] text-slate-400">CSAT appears after users rate resolved conversations.</p>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Coverage strip --}}
    <section class="grid gap-2 rounded-3xl border border-slate-200 bg-white p-2 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-2xl bg-primary-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-primary-600 shadow-sm"><i class="fas fa-robot text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-700/70">AI coverage</p>
                <p class="text-sm font-semibold text-slate-900"><span data-support-coverage="ai">{{ number_format($insights['coverage']['ai_pct'], 1) }}%</span> <span class="text-xs font-normal text-slate-400" data-support-coverage-detail="ai">&middot; {{ number_format($stats['ai_handled']) }} conversations</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm"><i class="fas fa-users text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/70">Team coverage</p>
                <p class="text-sm font-semibold text-slate-900"><span data-support-coverage="assigned">{{ number_format($insights['coverage']['assigned_pct'], 1) }}%</span> <span class="text-xs font-normal text-slate-400" data-support-coverage-detail="assigned">&middot; {{ number_format($stats['assigned_active']) }} of {{ number_format($stats['active']) }} active</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-indigo-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm"><i class="fas fa-circle-check text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700/70">Resolution rate</p>
                <p class="text-sm font-semibold text-slate-900"><span data-support-coverage="resolution">{{ number_format($insights['coverage']['resolution_pct'], 1) }}%</span> <span class="text-xs font-normal text-slate-400" data-support-coverage-detail="resolution">&middot; {{ number_format($stats['resolved'] + $stats['closed']) }} closed</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-rose-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-rose-600 shadow-sm"><i class="fas fa-triangle-exclamation text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-700/70">Priority pressure</p>
                <p class="text-sm font-semibold text-slate-900"><span data-support-coverage="pressure">{{ number_format($insights['coverage']['pressure']) }}</span> <span class="text-xs font-normal text-slate-400">&middot; urgent + high active</span></p>
            </div>
        </div>
    </section>

    {{-- Conversation queue --}}
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div id="supportQueue">
            <div class="border-b border-slate-100 p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900">Conversation queue</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600"><span data-support-result-count>{{ number_format($conversations->total()) }}</span> results</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $sortLabels[$sort] }}
                            @if($conversations->hasPages())
                                &middot; page {{ $conversations->currentPage() }} of {{ $conversations->lastPage() }}
                            @endif
                            @if($hasFilters)
                                &middot; filtered
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $refreshUrl }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                            <i class="fas fa-rotate-right"></i> Refresh
                        </a>
                        <a href="{{ $statusTabUrl('all') }}" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                            <i class="fas fa-inbox"></i> View all
                        </a>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-1.5 rounded-2xl bg-slate-100/80 p-1">
                    @foreach($statusTabs as $statusTab)
                        @php $isActiveTab = $currentStatusTab === $statusTab['key']; @endphp
                        <a href="{{ $statusTabUrl($statusTab['key']) }}"
                           aria-current="{{ $isActiveTab ? 'page' : 'false' }}"
                           class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition {{ $isActiveTab ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:bg-white/60 hover:text-slate-800' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $statusTab['dot'] }}"></span>
                            {{ $statusTab['label'] }}
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $isActiveTab ? 'bg-slate-100 text-slate-600' : 'bg-white/70 text-slate-400' }}" data-support-tab-count="{{ $statusTab['key'] }}">{{ number_format($statusTab['count']) }}</span>
                        </a>
                    @endforeach
                </div>

                <form id="supportFilters" method="GET" action="{{ route('admin.support') }}" class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-12">
                    <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label class="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportSearch"><i class="fas fa-search text-[9px]"></i> Search</label>
                        <input id="supportSearch" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Subject, requester name or email..." class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportCategory">Category</label>
                        <select id="supportCategory" name="category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            <option value="">All categories</option>
                            @foreach(\App\Models\SupportConversation::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" {{ $filters['category'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportPriority">Priority</label>
                        <select id="supportPriority" name="priority" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            <option value="">All priorities</option>
                            @foreach(\App\Models\SupportConversation::PRIORITIES as $key => $label)
                                <option value="{{ $key }}" {{ $filters['priority'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportAssignee">Assigned to</label>
                        <select id="supportAssignee" name="assigned_to" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            <option value="">Anyone</option>
                            <option value="unassigned" {{ $filters['assigned_to'] === 0 ? 'selected' : '' }}>Unassigned</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ $filters['assigned_to'] === $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="xl:col-span-2">
                        <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportSort">Sort</label>
                        <select id="supportSort" name="sort" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            @foreach($sortLabels as $key => $label)
                                <option value="{{ $key }}" {{ $sort === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 xl:col-span-12">
                        <div>
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="supportPerPage">Per page</label>
                            <select id="supportPerPage" name="per_page" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" {{ $perPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl bg-primary-700 px-4 text-xs font-semibold text-white transition hover:bg-primary-800">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="{{ $resetUrl }}" class="inline-flex h-9 items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-500 transition hover:border-slate-300 hover:bg-slate-50" title="Reset filters">
                            <i class="fas fa-rotate-left"></i> Reset
                        </a>
                        <span class="ml-auto hidden text-[11px] text-slate-400 sm:inline">Live queue refreshes every 8 seconds</span>
                    </div>
                </form>

                @if($hasFilters)
                    <div class="mt-3 flex flex-wrap items-center gap-1.5">
                        <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Filters</span>
                        @foreach($activeFilters as $filter)
                            <a href="{{ $filterUrl([$filter['remove']]) }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                                {{ $filter['label'] }} <i class="fas fa-xmark text-[9px]"></i>
                            </a>
                        @endforeach
                        <a href="{{ $resetUrl }}" class="ml-1 text-[11px] font-semibold text-primary-700 hover:underline">Clear all</a>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">Conversation</th>
                            <th class="px-4 py-3 text-left font-semibold">Requester</th>
                            <th class="px-4 py-3 text-left font-semibold">Priority</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Category</th>
                            <th class="px-4 py-3 text-left font-semibold">Assignee</th>
                            <th class="px-4 py-3 text-left font-semibold">Activity</th>
                            <th class="px-5 py-3 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody id="supportQueueBody" class="divide-y divide-slate-100">
                        @include('admin.partials.support_queue_rows', ['conversations' => $conversations])
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                <p class="text-[11px] text-slate-400">
                    @if($conversations->total() > 0)
                        Showing {{ number_format($conversations->firstItem()) }}–{{ number_format($conversations->lastItem()) }} of {{ number_format($conversations->total()) }}
                    @else
                        No results
                    @endif
                </p>
                <div id="supportPagination">{{ $conversations->withQueryString()->links() }}</div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var trend = @json($insights['trend']);
    var pollUrl = @json(route('admin.support.poll-inbox'));

    function showChartFallback() {
        var el = document.getElementById('supportTrendFallback');
        if (el) {
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
    }

    function destroySupportCharts() {
        if (!window.__supportCharts) {
            window.__supportCharts = {};
            return;
        }
        Object.keys(window.__supportCharts).forEach(function (key) {
            try { window.__supportCharts[key].destroy(); } catch (e) {}
        });
        window.__supportCharts = {};
    }

    function initSupportChart() {
        if (!window.__supportCharts) window.__supportCharts = {};
        if (!window.Chart) {
            showChartFallback();
            return;
        }

        var canvas = document.getElementById('supportTrendChart');
        if (!canvas || window.__supportCharts.trend) return;

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';

        window.__supportCharts.trend = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: trend.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'New conversations',
                        data: trend.conversations,
                        backgroundColor: 'rgba(15, 118, 110, 0.72)',
                        hoverBackgroundColor: 'rgba(15, 118, 110, 0.95)',
                        borderRadius: 4,
                        maxBarThickness: 14,
                        order: 2
                    },
                    {
                        type: 'line',
                        label: 'Staff replies',
                        data: trend.replies,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        borderWidth: 2,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        padding: 10,
                        cornerRadius: 10,
                        titleFont: { size: 11, weight: '600' },
                        bodyFont: { size: 11 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { maxTicksLimit: 8, font: { size: 10 }, color: '#94a3b8' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.15)' },
                        border: { display: false },
                        ticks: { precision: 0, font: { size: 10 }, color: '#94a3b8' }
                    }
                }
            }
        });
    }

    function ensureChartLibrary(callback) {
        if (window.Chart) {
            callback();
            return;
        }
        var existing = document.querySelector('script[data-support-chartjs]');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            existing.addEventListener('error', showChartFallback, { once: true });
            return;
        }
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.async = true;
        script.setAttribute('data-support-chartjs', '1');
        script.addEventListener('load', callback, { once: true });
        script.addEventListener('error', showChartFallback, { once: true });
        document.head.appendChild(script);
    }

    destroySupportCharts();
    ensureChartLibrary(initSupportChart);

    var breakdownButtons = Array.prototype.slice.call(document.querySelectorAll('[data-support-breakdown-tab]'));
    breakdownButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-support-breakdown-tab');
            breakdownButtons.forEach(function (other) {
                var active = other === button;
                other.classList.toggle('bg-white', active);
                other.classList.toggle('shadow-sm', active);
                other.classList.toggle('text-slate-900', active);
                other.classList.toggle('text-slate-500', !active);
            });
            document.querySelectorAll('[data-support-breakdown-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-support-breakdown-panel') !== target);
                panel.classList.toggle('flex', panel.getAttribute('data-support-breakdown-panel') === target);
            });
            if (window.__supportCharts && window.__supportCharts.trend) {
                window.__supportCharts.trend.resize();
            }
        });
    });

    var POLL_MS = 8000;
    var pollTimer = null;
    var inFlight = false;

    function formatNumber(value) {
        return Number(value || 0).toLocaleString();
    }

    function updateStats(stats) {
        var values = {
            active: formatNumber(stats.active),
            awaiting_human: formatNumber(stats.awaiting_human),
            unassigned: formatNumber(stats.unassigned),
            satisfaction: stats.avg_rating !== null && stats.avg_rating !== undefined
                ? Number(stats.avg_rating).toFixed(1) + '/5'
                : '—'
        };
        Object.keys(values).forEach(function (key) {
            var node = document.querySelector('[data-support-stat-value="' + key + '"]');
            if (node) node.textContent = values[key];
        });

        var hints = {
            active: formatNumber(stats.open) + ' open · ' + formatNumber(stats.in_progress) + ' in progress',
            awaiting_human: Number(stats.awaiting_human) > 0 && stats.oldest_waiting
                ? 'Oldest waiting ' + stats.oldest_waiting
                : 'No one waiting for a human',
            unassigned: (Number(stats.active) > 0 ? (Number(stats.unassigned) / Number(stats.active) * 100).toFixed(1) : '0.0') + '% of the active queue',
            satisfaction: Number(stats.reviewed) > 0
                ? 'Based on ' + formatNumber(stats.reviewed) + ' review' + (Number(stats.reviewed) === 1 ? '' : 's')
                : 'No CSAT reviews yet'
        };
        Object.keys(hints).forEach(function (key) {
            var node = document.querySelector('[data-support-stat-hint="' + key + '"]');
            if (node) node.textContent = hints[key];
        });

        ['active', 'all', 'open', 'ai_handling', 'awaiting_human', 'in_progress', 'resolved', 'closed'].forEach(function (key) {
            var node = document.querySelector('[data-support-tab-count="' + key + '"]');
            if (node) node.textContent = formatNumber(stats[key]);
        });

        var badge = document.querySelector('[data-support-queue-badge]');
        if (badge) {
            var dot = badge.querySelector('span');
            var awaiting = Number(stats.awaiting_human) > 0;
            badge.className = 'inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-semibold ' + (awaiting ? 'bg-amber-400/15 text-amber-100' : 'bg-emerald-400/15 text-emerald-100');
            if (dot) dot.className = 'h-2 w-2 rounded-full ' + (awaiting ? 'bg-amber-300' : 'bg-emerald-300');
            badge.lastChild.textContent = ' ' + (awaiting ? formatNumber(stats.awaiting_human) + ' awaiting human' : (Number(stats.active) > 0 ? 'Human queue clear' : 'Inbox clear'));
        }

        var aiPct = Number(stats.total) > 0 ? Number(stats.ai_handled) / Number(stats.total) * 100 : 0;
        var assignedPct = Number(stats.active) > 0 ? Number(stats.assigned_active) / Number(stats.active) * 100 : 0;
        var resolutionPct = Number(stats.total) > 0 ? (Number(stats.resolved) + Number(stats.closed)) / Number(stats.total) * 100 : 0;
        var pressure = Number(stats.urgent_active) + Number(stats.high_active);
        var coverage = {
            ai: aiPct.toFixed(1) + '%',
            assigned: assignedPct.toFixed(1) + '%',
            resolution: resolutionPct.toFixed(1) + '%',
            pressure: formatNumber(pressure)
        };
        var details = {
            ai: '· ' + formatNumber(stats.ai_handled) + ' conversations',
            assigned: '· ' + formatNumber(stats.assigned_active) + ' of ' + formatNumber(stats.active) + ' active',
            resolution: '· ' + formatNumber(Number(stats.resolved) + Number(stats.closed)) + ' closed',
            pressure: '· urgent + high active'
        };
        Object.keys(coverage).forEach(function (key) {
            var valueNode = document.querySelector('[data-support-coverage="' + key + '"]');
            if (valueNode) valueNode.textContent = coverage[key];
            var detailNode = document.querySelector('[data-support-coverage-detail="' + key + '"]');
            if (detailNode) detailNode.textContent = details[key];
        });
    }

    function pollInbox() {
        if (inFlight || document.visibilityState === 'hidden') return;
        var filters = document.getElementById('supportFilters');
        if (filters && filters.contains(document.activeElement)) return;

        inFlight = true;
        var params = new URLSearchParams(window.location.search);
        var url = pollUrl + (params.toString() ? '?' + params.toString() : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (!data || !data.success) { inFlight = false; return; }
                updateStats(data.stats);
                var body = document.getElementById('supportQueueBody');
                if (body && typeof data.rows_html === 'string') body.innerHTML = data.rows_html;
                var pagination = document.getElementById('supportPagination');
                if (pagination && typeof data.pagination_html === 'string') pagination.innerHTML = data.pagination_html;
                var resultCount = document.querySelector('[data-support-result-count]');
                if (resultCount) resultCount.textContent = formatNumber(data.results_total);
                var snapshot = document.querySelector('[data-support-snapshot]');
                if (snapshot && data.generated_at) snapshot.textContent = data.generated_at;
                inFlight = false;
            })
            .catch(function () { inFlight = false; });
    }

    function startPolling() {
        clearInterval(pollTimer);
        pollTimer = setInterval(pollInbox, POLL_MS);
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            pollInbox();
            startPolling();
        }
    });

    startPolling();
})();
</script>
@endsection
