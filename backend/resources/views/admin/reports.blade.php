@extends('admin.layout')
@section('title', 'Reports Management')

@section('content')
@php
    $counts = $insights['counts'];
    $tabs = [
        ['key' => 'all', 'label' => 'All reports', 'count' => $counts['all'], 'dot' => 'bg-slate-400'],
        ['key' => 'pending', 'label' => 'Pending', 'count' => $counts['pending'], 'dot' => 'bg-amber-500'],
        ['key' => 'approved', 'label' => 'Approved', 'count' => $counts['approved'], 'dot' => 'bg-emerald-500'],
        ['key' => 'rejected', 'label' => 'Rejected', 'count' => $counts['rejected'], 'dot' => 'bg-rose-500'],
    ];
    $activeTab = $status;
    $tabUrl = fn (string $key) => route('admin.reports', array_merge(
        request()->except(['status', 'page', 'fragment']),
        ['status' => $key]
    ));
    $chipUrl = function (array $except) use ($status) {
        return route('admin.reports', array_merge(
            request()->except(array_merge($except, ['page', 'fragment'])),
            ['status' => $status]
        ));
    };
    $activeFilters = [];
    if (request()->filled('search')) {
        $activeFilters[] = ['label' => 'Search: “'.request('search').'”', 'url' => $chipUrl(['search'])];
    }
    if (request()->filled('priority')) {
        $activeFilters[] = ['label' => 'Priority: '.ucfirst(request('priority')), 'url' => $chipUrl(['priority'])];
    }
    if (request()->filled('category_id')) {
        $categoryFilter = $categories->firstWhere('id', (int) request('category_id'));
        $activeFilters[] = ['label' => 'Category: '.($categoryFilter?->name ?? '#'.request('category_id')), 'url' => $chipUrl(['category_id'])];
    }
    if (request()->filled('gps')) {
        $gpsLabels = ['verified' => 'GPS verified', 'mismatched' => 'GPS mismatch', 'no_gps_data' => 'No GPS'];
        $activeFilters[] = ['label' => $gpsLabels[request('gps')] ?? 'GPS: '.request('gps'), 'url' => $chipUrl(['gps'])];
    }
    if (request()->filled('date_range')) {
        $rangeLabels = ['today' => 'Today', '7days' => 'Last 7 days', '30days' => 'Last 30 days'];
        $activeFilters[] = ['label' => $rangeLabels[request('date_range')] ?? 'Date: '.request('date_range'), 'url' => $chipUrl(['date_range'])];
    }
    $hasFilters = count($activeFilters) > 0;
    $perPageOptions = [15, 25, 50, 100];
    $canApproveReports = auth()->user()?->hasPermission('approve_reports') ?? false;

    $priorityStyles = [
        'critical' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'high' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'medium' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'low' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $statusStyles = [
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'rejected' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
    $gpsMeta = [
        'verified' => ['label' => 'GPS verified', 'class' => 'text-emerald-600', 'icon' => 'fa-location-dot'],
        'mismatched' => ['label' => 'GPS mismatch', 'class' => 'text-rose-600', 'icon' => 'fa-triangle-exclamation'],
        'no_gps_data' => ['label' => 'No photo GPS', 'class' => 'text-amber-600', 'icon' => 'fa-location-crosshairs'],
        'none' => ['label' => 'No photo GPS', 'class' => 'text-slate-400', 'icon' => 'fa-location-dot'],
    ];
    $initials = function (?string $name): string {
        $name = trim($name ?: 'Anonymous');
        $parts = preg_split('/\s+/', $name) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_substr($part, 0, 1);
        }
        return mb_strtoupper($letters ?: 'A');
    };
@endphp

<div id="adminReports" class="space-y-5 p-4 sm:p-5 lg:p-6">
    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-primary-200">Reports &amp; moderation</p>
                <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Reports command center</h1>
                <p class="mt-1 max-w-xl text-sm leading-relaxed text-primary-100">Review submissions, verify evidence and publish alerts before they reach the public map.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-layer-group text-[10px]"></i>{{ number_format($counts['all']) }} reports
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-clock text-[10px]"></i>
                        @if($insights['latest_report'])
                            Last report {{ $insights['latest_report'] }}
                        @else
                            No reports yet
                        @endif
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-location-dot text-[10px]"></i>Nepal time
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-semibold {{ $counts['pending'] > 0 ? 'bg-amber-400/15 text-amber-100' : 'bg-emerald-400/15 text-emerald-100' }}">
                        <span class="h-2 w-2 rounded-full {{ $counts['pending'] > 0 ? 'bg-amber-300' : 'bg-emerald-300' }}"></span>
                        {{ $counts['pending'] > 0 ? number_format($counts['pending']).' awaiting review' : 'Review queue clear' }}
                    </span>
                    <a href="{{ route('admin.reports', request()->except('fragment')) }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20">
                        <i class="fas fa-rotate-right"></i> Refresh
                    </a>
                    <a href="{{ route('admin.moderation') }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                        <i class="fas fa-shield-halved"></i> Moderation
                    </a>
                </div>
                <p class="text-xs text-primary-200">Snapshot generated at {{ $insights['generated_at'] }}</p>
            </div>
        </div>
    </section>

    {{-- KPI cards --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($insights['kpis'] as $kpi)
            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $kpi['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $kpi['value'] }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-2xl {{ $kpi['icon_bg'] }}">
                        <i class="fas fa-{{ $kpi['icon'] }} text-sm"></i>
                    </span>
                </div>
                <p class="mt-2 text-xs leading-relaxed {{ $kpi['hint_class'] }}">{{ $kpi['hint'] }}</p>
            </div>
        @endforeach
    </section>

    {{-- Analytics --}}
    <section class="grid gap-3 xl:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Submission flow</h2>
                    <p class="mt-1 text-xs text-slate-500">Reports received and approved over the last 30 days.</p>
                </div>
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 font-semibold text-primary-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                        {{ number_format($insights['trend']['submitted_total']) }} submitted
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        {{ number_format($insights['trend']['approved_total']) }} approved
                    </span>
                </div>
            </div>
            <div class="relative mt-4 h-64">
                <canvas id="reportsTrendChart" aria-label="Report submissions and approvals over the last 30 days" role="img"></canvas>
                <div id="reportsTrendFallback" class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                    Chart library unavailable — totals are shown above.
                </div>
            </div>
            <div class="mt-3 flex items-center gap-4 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-700/70"></span> Submitted</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-emerald-500"></span> Approved</span>
                @if($counts['today'] === 0)
                    <span class="ml-auto text-slate-400">No submissions today yet</span>
                @else
                    <span class="ml-auto font-semibold text-slate-600">{{ number_format($counts['today']) }} today</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Breakdown</h2>
                    <p class="mt-1 text-xs text-slate-500">Across all {{ number_format($counts['all']) }} reports.</p>
                </div>
                <div class="flex rounded-xl bg-slate-100 p-0.5 text-[11px] font-semibold">
                    <button type="button" data-breakdown-tab="priority" class="rounded-lg bg-white px-2.5 py-1.5 text-slate-900 shadow-sm">Priority</button>
                    <button type="button" data-breakdown-tab="category" class="rounded-lg px-2.5 py-1.5 text-slate-500 transition hover:text-slate-800">Category</button>
                </div>
            </div>

            <div data-breakdown-panel="priority" class="mt-4 flex flex-1 flex-col">
                <div class="relative mx-auto h-40 w-40 flex-shrink-0">
                    <canvas id="reportsPriorityChart" aria-label="Report priority distribution" role="img"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xl font-semibold text-slate-900">{{ number_format($counts['all']) }}</span>
                        <span class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">reports</span>
                    </div>
                    <div id="reportsPriorityFallback" class="absolute inset-0 hidden items-center justify-center rounded-full bg-slate-50 text-[10px] text-slate-400">Chart unavailable</div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-3 gap-y-2">
                    @foreach($insights['priority'] as $row)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                            <span class="min-w-0 flex-1 truncate text-slate-500">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Priority is chosen by the reporter and can be changed during review.</p>
            </div>

            <div data-breakdown-panel="category" class="mt-4 hidden flex-1 space-y-3">
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
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Top {{ count($insights['categories']) }} categories by report volume.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- Moderation queue strip --}}
    <section class="grid gap-2 rounded-3xl border border-slate-200 bg-white p-2 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-2xl bg-amber-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-amber-600 shadow-sm"><i class="fas fa-hourglass-half text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700/70">Queue pending</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['queues']['pending']) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm"><i class="fas fa-check text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/70">Queue approved</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['queues']['approved']) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-rose-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-rose-600 shadow-sm"><i class="fas fa-xmark text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-700/70">Queue rejected</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['queues']['rejected']) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-primary-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-primary-600 shadow-sm"><i class="fas fa-location-dot text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-700/70">GPS verified</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['gps']['verified']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($insights['gps']['verified_pct'], 1) }}%</span></p>
            </div>
        </div>
    </section>

    {{-- Report queue --}}
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        {{-- #liveTable is intentionally the whole card body: the admin layout's
             surgical AJAX navigation replaces only this element, keeping the
             filter form, status tabs and pagination in sync. --}}
        <div id="liveTable">
            <div class="border-b border-slate-100 p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900">Report queue</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ number_format($reports->total()) }} results</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Newest first
                            @if($reports->hasPages())
                                &middot; page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}
                            @endif
                            @if($hasFilters)
                                &middot; filtered
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.moderation') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                            <i class="fas fa-shield-halved"></i> Open moderation
                        </a>
                        <a href="{{ $tabUrl('pending') }}" class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-amber-600">
                            <i class="fas fa-hourglass-half"></i> Review pending
                            <span class="rounded-full bg-white/20 px-1.5 py-0.5 text-[10px]">{{ number_format($counts['pending']) }}</span>
                        </a>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-1.5 rounded-2xl bg-slate-100/80 p-1">
                    @foreach($tabs as $tab)
                        @php $isActive = $activeTab === $tab['key']; @endphp
                        <a href="{{ $tabUrl($tab['key']) }}"
                           aria-current="{{ $isActive ? 'page' : 'false' }}"
                           class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition {{ $isActive ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:bg-white/60 hover:text-slate-800' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $tab['dot'] }}"></span>
                            {{ $tab['label'] }}
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $isActive ? 'bg-slate-100 text-slate-600' : 'bg-white/70 text-slate-400' }}">{{ number_format($tab['count']) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-5">
                <form method="GET" action="{{ route('admin.reports') }}" class="space-y-3">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
                        <label class="relative block flex-1">
                            <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by report title or reporter..." aria-label="Search reports" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="priority" aria-label="Filter by priority" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All priorities</option>
                                @foreach(['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'] as $value => $label)
                                    <option value="{{ $value }}" {{ request('priority') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="category_id" aria-label="Filter by category" class="max-w-[180px] rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All categories</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string) request('category_id') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                            <select name="gps" aria-label="Filter by GPS verification" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All GPS</option>
                                <option value="verified" {{ request('gps') === 'verified' ? 'selected' : '' }}>Verified</option>
                                <option value="mismatched" {{ request('gps') === 'mismatched' ? 'selected' : '' }}>Mismatch</option>
                                <option value="no_gps_data" {{ request('gps') === 'no_gps_data' ? 'selected' : '' }}>No GPS</option>
                            </select>
                            <select name="date_range" aria-label="Filter by date range" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All time</option>
                                <option value="today" {{ request('date_range') === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="7days" {{ request('date_range') === '7days' ? 'selected' : '' }}>Last 7 days</option>
                                <option value="30days" {{ request('date_range') === '30days' ? 'selected' : '' }}>Last 30 days</option>
                            </select>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            @if($hasFilters)
                                <a href="{{ $tabUrl($status) }}" class="inline-flex items-center gap-1.5 rounded-xl px-2.5 py-2.5 text-xs font-semibold text-slate-500 transition hover:text-slate-800">
                                    <i class="fas fa-xmark"></i> Clear
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            @if($hasFilters)
                                @foreach($activeFilters as $filter)
                                    <a href="{{ $filter['url'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">
                                        {{ $filter['label'] }}
                                        <i class="fas fa-xmark text-[9px]"></i>
                                    </a>
                                @endforeach
                            @else
                                <span class="text-[11px] text-slate-400">No filters applied</span>
                            @endif
                        </div>
                        <label class="flex items-center gap-2 text-[11px] text-slate-500">
                            Rows per page
                            <select name="per_page" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600 outline-none focus:border-primary-400">
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" {{ (int) request('per_page', 15) === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <form id="reportsBulkForm" method="POST" action="{{ route('admin.reports.bulk-delete') }}" data-native>
                    @csrf
                </form>
                <table class="w-full min-w-[560px] lg:min-w-[860px]">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="w-10 px-4 py-3 text-left">
                                <input type="checkbox" id="selectAllReports" onchange="toggleAllReports(this)" class="rounded border-slate-300 text-primary-700 focus:ring-primary-500" title="Select all reports on this page" aria-label="Select all reports on this page">
                            </th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Report</th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 lg:table-cell">Reporter</th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 md:table-cell">Priority</th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 xl:table-cell">Trust &amp; GPS</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Status</th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 sm:table-cell">Age</th>
                            <th scope="col" class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($reports as $report)
                            @php
                                $trust = $report->authenticity_score !== null ? (int) round((float) $report->authenticity_score * 100) : null;
                                $trustBar = $trust === null ? 'bg-slate-200' : ($trust >= 75 ? 'bg-emerald-500' : ($trust >= 50 ? 'bg-amber-500' : 'bg-rose-500'));
                                $trustText = $trust === null ? 'text-slate-400' : ($trust >= 75 ? 'text-emerald-700' : ($trust >= 50 ? 'text-amber-700' : 'text-rose-700'));
                                $gps = $gpsMeta[$report->gps_verification_status] ?? $gpsMeta['none'];
                                $priorityStyle = $priorityStyles[$report->priority] ?? 'bg-slate-100 text-slate-500 ring-slate-200';
                                $statusStyle = $statusStyles[$report->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                                $reportUrl = route('admin.reports.view', array_merge(['id' => $report->id], $listParams ?? []));
                                $age = $report->created_at->diffForHumans(short: true);
                            @endphp
                            <tr class="group transition hover:bg-slate-50/80">
                                <td class="px-4 py-3.5 align-top">
                                    <input type="checkbox" name="ids[]" value="{{ $report->id }}" form="reportsBulkForm" class="report-checkbox rounded border-slate-300 text-primary-700 focus:ring-primary-500" onchange="updateBulkBar()" aria-label="Select report #{{ $report->id }}">
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="min-w-0">
                                        <a href="{{ $reportUrl }}" class="line-clamp-1 text-sm font-semibold text-slate-900 transition hover:text-primary-700">{{ $report->title }}</a>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-400">
                                            <button type="button" title="Copy report ID" onclick="navigator.clipboard.writeText('#{{ $report->id }}').then(function(){showReportCopyToast('Report ID copied');})" class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500 transition hover:bg-slate-200 hover:text-slate-700">
                                                #{{ $report->id }} <i class="fas fa-copy text-[9px]"></i>
                                            </button>
                                            <span class="text-slate-300">&middot;</span>
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-600">{{ $report->category?->name ?? 'Uncategorised' }}</span>
                                            @if($report->moderation_message)
                                                <span title="{{ $report->moderation_message }}" class="inline-flex items-center gap-1 text-primary-600"><i class="fas fa-robot text-[9px]"></i> AI note</span>
                                            @endif
                                            @if($report->is_live_capture)
                                                <span class="inline-flex items-center gap-1 text-slate-500" title="Captured with the in-app camera"><i class="fas fa-camera text-[9px]"></i> Live</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-3.5 lg:table-cell">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary-50 text-[10px] font-bold text-primary-700">{{ $initials($report->user?->name) }}</span>
                                        <span class="max-w-[130px] truncate text-xs font-medium text-slate-700">{{ $report->user?->name ?? 'Anonymous' }}</span>
                                    </div>
                                </td>
                                <td class="hidden px-4 py-3.5 md:table-cell">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $priorityStyle }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ ucfirst($report->priority ?? 'unknown') }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3.5 xl:table-cell">
                                    <div class="w-28">
                                        <div class="flex items-center justify-between text-[10px]">
                                            <span class="text-slate-400">Trust</span>
                                            <span class="font-semibold {{ $trustText }}">{{ $trust === null ? '—' : $trust.'%' }}</span>
                                        </div>
                                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div class="h-full rounded-full {{ $trustBar }}" style="width: {{ $trust ?? 0 }}%"></div>
                                        </div>
                                        <p class="mt-1 flex items-center gap-1 text-[10px] {{ $gps['class'] }}" title="{{ $report->gps_verification_status === 'verified' ? 'Photo GPS matched device location'.($report->gps_distance_km !== null ? ' ('.number_format((float) $report->gps_distance_km, 2).' km)' : '') : ($report->gps_verification_status === 'mismatched' ? 'Photo GPS did not match device location' : 'No verified photo GPS') }}">
                                            <i class="fas {{ $gps['icon'] }}"></i>{{ $gps['label'] }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $statusStyle }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ ucfirst($report->status) }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3.5 text-xs text-slate-500 sm:table-cell" title="{{ $report->created_at->format('M j, Y g:i A') }}">{{ $age }}</td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ $reportUrl }}" title="Open report #{{ $report->id }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                                            <i class="fas fa-arrow-up-right-from-square text-[11px]"></i>
                                        </a>
                                        @if($report->status === 'pending' && $canApproveReports)
                                            <form method="POST" action="{{ route('admin.reports.approve', $report->id) }}" data-native class="inline" onsubmit="return confirm('Approve this report?\n\nThis will publish an alert and award XP to the reporter.')">
                                                @csrf
                                                <button type="submit" title="Approve report #{{ $report->id }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100">
                                                    <i class="fas fa-check text-[11px]"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.reports.reject', $report->id) }}" data-native class="inline" onsubmit="return confirm('Reject report #{{ $report->id }}?')">
                                                @csrf
                                                <button type="submit" title="Reject report #{{ $report->id }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition hover:bg-rose-100">
                                                    <i class="fas fa-xmark text-[11px]"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.reports.delete', $report->id) }}" data-native class="inline" onsubmit="return confirm('Delete report #{{ $report->id }}? This cannot be undone.')">
                                            @csrf
                                            <button type="submit" title="Delete report #{{ $report->id }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                                <i class="fas fa-trash text-[11px]"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl {{ $status === 'pending' ? 'bg-emerald-50 text-emerald-500' : 'bg-slate-100 text-slate-300' }}">
                                            <i class="fas {{ $status === 'pending' ? 'fa-check-double' : 'fa-inbox' }} text-xl"></i>
                                        </span>
                                        @if($status === 'pending' && !$hasFilters)
                                            <p class="mt-3 text-sm font-semibold text-slate-800">Review queue is clear</p>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-400">Every submitted report has been reviewed. Nice work.</p>
                                            @if($counts['all'] > 0)
                                                <a href="{{ $tabUrl('all') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                                    <i class="fas fa-layer-group"></i> Browse all {{ number_format($counts['all']) }} reports
                                                </a>
                                            @endif
                                        @else
                                            <p class="mt-3 text-sm font-semibold text-slate-800">No reports match this view</p>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-400">Try a different status, or clear one of the active filters.</p>
                                            @if($hasFilters)
                                                <a href="{{ $tabUrl($status) }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                                    <i class="fas fa-rotate-left"></i> Clear filters
                                                </a>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="bulkBar" class="fixed inset-x-4 bottom-4 z-40 mx-auto hidden max-w-md items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50/95 px-4 py-3 shadow-xl backdrop-blur">
                <span class="text-xs font-medium text-rose-700"><span id="selectedCount">0</span> reports selected</span>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="clearReportSelection()" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-500 transition hover:text-slate-800">Clear</button>
                    <button type="button" onclick="openBulkDeleteModal()" class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700">
                        <i class="fas fa-trash text-[10px]"></i> Delete selected (<span id="selectedCountBtn">0</span>)
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing
                    <span class="font-semibold text-slate-700">{{ $reports->firstItem() ?? 0 }}–{{ $reports->lastItem() ?? 0 }}</span>
                    of <span class="font-semibold text-slate-700">{{ number_format($reports->total()) }}</span> reports
                </p>
                @if($reports->hasPages())
                    <nav class="flex items-center gap-1" role="navigation" aria-label="Report pagination">
                        @if($reports->onFirstPage())
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true"><i class="fas fa-chevron-left text-[10px]"></i></span>
                        @else
                            <a href="{{ $reports->previousPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" aria-label="Previous page"><i class="fas fa-chevron-left text-[10px]"></i></a>
                        @endif

                        @php
                            $currentPage = $reports->currentPage();
                            $lastPage = $reports->lastPage();
                            $windowStart = max(1, $currentPage - 2);
                            $windowEnd = min($lastPage, $currentPage + 2);
                        @endphp

                        @if($windowStart > 1)
                            <a href="{{ $reports->url(1) }}" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">1</a>
                            @if($windowStart > 2)
                                <span class="px-1 text-xs text-slate-400">&hellip;</span>
                            @endif
                        @endif

                        @foreach($reports->getUrlRange($windowStart, $windowEnd) as $pageNumber => $pageUrl)
                            @if($pageNumber === $currentPage)
                                <span class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg bg-primary-700 px-2 text-xs font-bold text-white" aria-current="page">{{ $pageNumber }}</span>
                            @else
                                <a href="{{ $pageUrl }}" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">{{ $pageNumber }}</a>
                            @endif
                        @endforeach

                        @if($windowEnd < $lastPage)
                            @if($windowEnd < $lastPage - 1)
                                <span class="px-1 text-xs text-slate-400">&hellip;</span>
                            @endif
                            <a href="{{ $reports->url($lastPage) }}" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">{{ $lastPage }}</a>
                        @endif

                        @if($reports->hasMorePages())
                            <a href="{{ $reports->nextPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" aria-label="Next page"><i class="fas fa-chevron-right text-[10px]"></i></a>
                        @else
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true"><i class="fas fa-chevron-right text-[10px]"></i></span>
                        @endif
                    </nav>
                @endif
            </div>
        </div>

        {{-- Bulk delete confirmation (kept outside #liveTable so an AJAX
             table refresh cannot close an open dialog). --}}
        <div id="bulkDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="bulkDeleteModalTitle">
            <div class="flex min-h-screen items-center justify-center px-4 pb-20 pt-4 text-center sm:p-0">
                <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeBulkDeleteModal()"></div>
                <div class="relative inline-block w-full transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:max-w-md">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <h3 id="bulkDeleteModalTitle" class="font-semibold text-slate-900"><i class="fas fa-triangle-exclamation mr-2 text-rose-500"></i>Delete reports</h3>
                        <button type="button" onclick="closeBulkDeleteModal()" class="text-slate-400 transition hover:text-slate-600" aria-label="Cancel"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="p-5">
                        <p class="text-sm leading-relaxed text-slate-600">Delete <span id="modalSelectedCount" class="font-semibold text-slate-900">0</span> selected reports? This permanently removes the reports, media, comments and confirmations.</p>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" onclick="closeBulkDeleteModal()" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-200">Cancel</button>
                            <button type="button" onclick="confirmBulkDelete()" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700"><i class="fas fa-trash mr-1"></i>Delete</button>
                        </div>
                    </div>
                </div>
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
    var priority = @json($insights['priority']);

    // ---------------------------------------------------------------
    // Charts
    // ---------------------------------------------------------------
    function destroyReportCharts() {
        if (!window.__reportsCharts) {
            window.__reportsCharts = {};
            return;
        }
        Object.keys(window.__reportsCharts).forEach(function (key) {
            try { window.__reportsCharts[key].destroy(); } catch (e) {}
        });
        window.__reportsCharts = {};
    }

    function showChartFallback(id) {
        var el = document.getElementById(id);
        if (el) {
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
    }

    function initReportCharts() {
        if (!window.__reportsCharts) window.__reportsCharts = {};

        if (!window.Chart) {
            showChartFallback('reportsTrendFallback');
            showChartFallback('reportsPriorityFallback');
            return;
        }

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';

        var trendCanvas = document.getElementById('reportsTrendChart');
        if (trendCanvas && !window.__reportsCharts.trend) {
            window.__reportsCharts.trend = new Chart(trendCanvas, {
                type: 'bar',
                data: {
                    labels: trend.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Submitted',
                            data: trend.submitted,
                            backgroundColor: 'rgba(15, 118, 110, 0.72)',
                            hoverBackgroundColor: 'rgba(15, 118, 110, 0.95)',
                            borderRadius: 4,
                            maxBarThickness: 14,
                            order: 2
                        },
                        {
                            type: 'line',
                            label: 'Approved',
                            data: trend.approved,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
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
                            bodyFont: { size: 11 },
                            displayColors: true
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

        var priorityCanvas = document.getElementById('reportsPriorityChart');
        if (priorityCanvas && !window.__reportsCharts.priority) {
            var hasPriorityData = priority.some(function (row) { return Number(row.value) > 0; });
            if (!hasPriorityData) {
                showChartFallback('reportsPriorityFallback');
            } else {
                window.__reportsCharts.priority = new Chart(priorityCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: priority.map(function (row) { return row.label; }),
                        datasets: [{
                            data: priority.map(function (row) { return Number(row.value); }),
                            backgroundColor: priority.map(function (row) { return row.color; }),
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0f172a',
                                padding: 10,
                                cornerRadius: 10,
                                bodyFont: { size: 11 },
                                callbacks: {
                                    label: function (context) {
                                        var value = Number(context.parsed) || 0;
                                        return ' ' + context.label + ': ' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    }

    function ensureChartLibrary(callback) {
        if (window.Chart) {
            callback();
            return;
        }

        var existing = document.querySelector('script[data-report-chartjs]');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            existing.addEventListener('error', function () {
                showChartFallback('reportsTrendFallback');
                showChartFallback('reportsPriorityFallback');
            }, { once: true });
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.async = true;
        script.setAttribute('data-report-chartjs', '1');
        script.addEventListener('load', callback, { once: true });
        script.addEventListener('error', function () {
            showChartFallback('reportsTrendFallback');
            showChartFallback('reportsPriorityFallback');
        }, { once: true });
        document.head.appendChild(script);
    }

    destroyReportCharts();
    ensureChartLibrary(initReportCharts);

    // Breakdown tabs (outside #liveTable, so listeners survive table refreshes).
    var breakdownButtons = document.querySelectorAll('[data-breakdown-tab]');
    breakdownButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-breakdown-tab');
            breakdownButtons.forEach(function (other) {
                var active = other === button;
                other.classList.toggle('bg-white', active);
                other.classList.toggle('shadow-sm', active);
                other.classList.toggle('text-slate-900', active);
                other.classList.toggle('text-slate-500', !active);
            });
            document.querySelectorAll('[data-breakdown-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-breakdown-panel') !== target);
            });
            if (target === 'priority' && window.__reportsCharts && window.__reportsCharts.priority) {
                window.__reportsCharts.priority.resize();
            }
        });
    });
})();

// -------------------------------------------------------------------
// Bulk selection (global: the table is replaced by the layout's AJAX
// list navigation, but these functions are referenced by inline HTML).
// -------------------------------------------------------------------
window.selectedReportIds = function () {
    return Array.from(document.querySelectorAll('.report-checkbox:checked')).map(function (cb) { return cb.value; });
};

window.updateBulkBar = function () {
    var boxes = Array.from(document.querySelectorAll('.report-checkbox'));
    var checked = boxes.filter(function (cb) { return cb.checked; });
    var count = checked.length;
    var bar = document.getElementById('bulkBar');
    var selected = document.getElementById('selectedCount');
    var selectedBtn = document.getElementById('selectedCountBtn');

    if (selected) selected.textContent = count;
    if (selectedBtn) selectedBtn.textContent = count;

    if (bar) {
        bar.classList.toggle('hidden', count === 0);
        bar.classList.toggle('flex', count > 0);
    }

    var all = document.getElementById('selectAllReports');
    if (all) {
        all.checked = count > 0 && count === boxes.length;
        all.indeterminate = count > 0 && count < boxes.length;
    }
};

window.toggleAllReports = function (source) {
    document.querySelectorAll('.report-checkbox').forEach(function (cb) { cb.checked = source.checked; });
    window.updateBulkBar();
};

window.clearReportSelection = function () {
    document.querySelectorAll('.report-checkbox').forEach(function (cb) { cb.checked = false; });
    var all = document.getElementById('selectAllReports');
    if (all) {
        all.checked = false;
        all.indeterminate = false;
    }
    window.updateBulkBar();
};

window.openBulkDeleteModal = function () {
    var count = window.selectedReportIds().length;
    if (count === 0) return;
    var modalCount = document.getElementById('modalSelectedCount');
    if (modalCount) modalCount.textContent = count;
    document.getElementById('bulkDeleteModal').classList.remove('hidden');
};

window.closeBulkDeleteModal = function () {
    var modal = document.getElementById('bulkDeleteModal');
    if (modal) modal.classList.add('hidden');
};

window.confirmBulkDelete = function () {
    var ids = window.selectedReportIds();
    if (ids.length === 0) {
        window.closeBulkDeleteModal();
        return;
    }
    var form = document.getElementById('reportsBulkForm');
    if (!form) return;
    window.closeBulkDeleteModal();
    if (form.requestSubmit) {
        form.requestSubmit();
    } else {
        form.submit();
    }
};

window.showReportCopyToast = function (message) {
    var toast = document.getElementById('reportCopyToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'reportCopyToast';
        toast.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:9999;display:flex;align-items:center;gap:8px;background:#0f172a;color:#fff;padding:10px 16px;border-radius:9999px;box-shadow:0 10px 30px rgba(15,23,42,.25);font-size:13px;';
        document.body.appendChild(toast);
    }
    toast.innerHTML = '<i class="fas fa-check-circle" style="color:#34d399"></i> ' + message;
    toast.style.display = 'flex';
    clearTimeout(window.__reportCopyTimer);
    window.__reportCopyTimer = setTimeout(function () {
        toast.style.display = 'none';
    }, 1800);
};

document.addEventListener('DOMContentLoaded', function () {
    window.updateBulkBar();
});
</script>
@endsection
