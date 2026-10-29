@extends('admin.layout')
@section('title', 'Alerts Management')

@section('content')
@php
    $counts = $insights['counts'];
    $tabs = [
        ['key' => 'all', 'label' => 'All alerts', 'count' => $counts['all'], 'dot' => 'bg-slate-400'],
        ['key' => 'active', 'label' => 'Active', 'count' => $counts['active'], 'dot' => 'bg-emerald-500'],
        ['key' => 'expired', 'label' => 'Expired', 'count' => $counts['expired'], 'dot' => 'bg-rose-400'],
    ];
    $activeTab = $status;
    $tabUrl = fn (string $key) => route('admin.alerts', array_merge(
        request()->except(['status', 'page', 'fragment']),
        ['status' => $key]
    ));
    $severityUrl = fn (string $key) => route('admin.alerts', array_merge(
        request()->except(['severity', 'page', 'fragment']),
        ['severity' => $key]
    ));
    $chipUrl = function (array $except) use ($status) {
        return route('admin.alerts', array_merge(
            request()->except(array_merge($except, ['page', 'fragment'])),
            ['status' => $status]
        ));
    };
    $clearUrl = route('admin.alerts', ['status' => $status]);

    $activeFilters = [];
    if (request()->filled('search')) {
        $activeFilters[] = ['label' => 'Search: “'.request('search').'”', 'url' => $chipUrl(['search'])];
    }
    if ($severity !== 'all') {
        $severityLabel = collect($insights['severities'])->firstWhere('key', $severity)['label'] ?? ucfirst($severity);
        $activeFilters[] = ['label' => 'Severity: '.$severityLabel, 'url' => $chipUrl(['severity'])];
    }
    if (request()->filled('alert_type')) {
        $typeFilter = collect($types)->firstWhere('key', request('alert_type'));
        $activeFilters[] = [
            'label' => 'Type: '.($typeFilter['label'] ?? ucfirst(str_replace('_', ' ', request('alert_type')))),
            'url' => $chipUrl(['alert_type']),
        ];
    }
    if ($dateRange !== '') {
        $rangeLabels = ['today' => 'Today', '7days' => 'Last 7 days', '30days' => 'Last 30 days'];
        $activeFilters[] = [
            'label' => 'Date: '.($rangeLabels[$dateRange] ?? $dateRange),
            'url' => $chipUrl(['date_range']),
        ];
    }
    $hasFilters = count($activeFilters) > 0;
    $perPageOptions = [15, 25, 50, 100];

    $severityStyles = [
        'critical' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'high' => 'bg-orange-50 text-orange-700 ring-orange-200',
        'medium' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'low' => 'bg-lime-50 text-lime-700 ring-lime-200',
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
    ];
    $severityDots = collect($insights['severities'])->pluck('color', 'key');
    $typeLabels = \App\Services\AdminAlertsService::TYPE_LABELS;
@endphp

<div id="adminAlerts" class="space-y-5 p-4 sm:p-5 lg:p-6">
    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-accent-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-primary-200">Public safety &middot; Alerts</p>
                <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Alerts command center</h1>
                <p class="mt-1 max-w-xl text-sm leading-relaxed text-primary-100">Publish hazard warnings, target districts or reach every user, and keep the public feed current.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-bell text-[10px]"></i>{{ number_format($counts['all']) }} alerts
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-clock text-[10px]"></i>
                        @if($insights['latest_alert'])
                            Latest {{ $insights['latest_alert'] }}
                        @else
                            No alerts published yet
                        @endif
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-location-dot text-[10px]"></i>Nepal time
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-semibold {{ $counts['expiring_soon'] > 0 ? 'bg-amber-400/15 text-amber-100' : 'bg-emerald-400/15 text-emerald-100' }}">
                        <span class="h-2 w-2 rounded-full {{ $counts['expiring_soon'] > 0 ? 'bg-amber-300' : 'bg-emerald-300' }}"></span>
                        {{ $counts['expiring_soon'] > 0 ? number_format($counts['expiring_soon']).' expiring within 24h' : number_format($counts['active']).' active alerts' }}
                    </span>
                    <a href="{{ route('admin.alerts', request()->except('fragment')) }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20">
                        <i class="fas fa-rotate-right"></i> Refresh
                    </a>
                    @if($canManageAlerts)
                        <a href="#createAlert" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                            <i class="fas fa-plus"></i> Compose alert
                        </a>
                    @endif
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
                    <h2 class="text-sm font-semibold text-slate-900">Alert activity</h2>
                    <p class="mt-1 text-xs text-slate-500">Alerts published and urgent (critical/high) alerts over the last 30 days.</p>
                </div>
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 font-semibold text-primary-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                        {{ number_format($insights['trend']['created_total']) }} published
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 font-semibold text-rose-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        {{ number_format($insights['trend']['urgent_total']) }} urgent
                    </span>
                </div>
            </div>
            <div class="relative mt-4 h-64">
                <canvas id="alertsTrendChart" aria-label="Alerts published and urgent alerts over the last 30 days" role="img"></canvas>
                <div id="alertsTrendFallback" class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                    Chart library unavailable — totals are shown above.
                </div>
            </div>
            <div class="mt-3 flex items-center gap-4 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-700/70"></span> Published</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-rose-500"></span> Critical / high</span>
                @if($counts['today'] === 0)
                    <span class="ml-auto text-slate-400">No alerts today yet</span>
                @else
                    <span class="ml-auto font-semibold text-slate-600">{{ number_format($counts['today']) }} today</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Breakdown</h2>
                    <p class="mt-1 text-xs text-slate-500">Across all {{ number_format($counts['all']) }} alerts.</p>
                </div>
                <div class="flex rounded-xl bg-slate-100 p-0.5 text-[10px] font-semibold">
                    <button type="button" data-alert-breakdown-tab="severity" class="rounded-lg bg-white px-2 py-1.5 text-slate-900 shadow-sm">Severity</button>
                    <button type="button" data-alert-breakdown-tab="type" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Type</button>
                    <button type="button" data-alert-breakdown-tab="district" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Area</button>
                </div>
            </div>

            <div data-alert-breakdown-panel="severity" class="mt-4 flex flex-1 flex-col">
                <div class="relative mx-auto h-40 w-40 flex-shrink-0">
                    <canvas id="alertsSeverityChart" aria-label="Alert severity distribution" role="img"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xl font-semibold text-slate-900">{{ number_format($counts['all']) }}</span>
                        <span class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">alerts</span>
                    </div>
                    <div id="alertsSeverityFallback" class="absolute inset-0 hidden items-center justify-center rounded-full bg-slate-50 text-[10px] text-slate-400">Chart unavailable</div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-3 gap-y-2">
                    @foreach($insights['severities'] as $row)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span>
                            <span class="min-w-0 flex-1 truncate text-slate-500">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Severity drives the colour and urgency of the public alert.</p>
            </div>

            <div data-alert-breakdown-panel="type" class="mt-4 hidden flex-1 space-y-3">
                @forelse($insights['types'] as $row)
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
                    <p class="py-6 text-center text-xs text-slate-400">No alert types yet.</p>
                @endforelse
                @if(count($insights['types']) > 0)
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Top {{ count($insights['types']) }} alert types by volume.</p>
                @endif
            </div>

            <div data-alert-breakdown-panel="district" class="mt-4 hidden flex-1 space-y-3">
                @forelse($insights['districts'] as $row)
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
                    <p class="py-6 text-center text-xs text-slate-400">No district data yet.</p>
                @endforelse
                @if(count($insights['districts']) > 0)
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Top {{ count($insights['districts']) }} affected districts by alert volume.</p>
                @endif
            </div>
        </div>
    </section>

    {{-- Coverage strip --}}
    <section class="grid gap-2 rounded-3xl border border-slate-200 bg-white p-2 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm"><i class="fas fa-signal text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/70">Live now</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['active']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($insights['coverage']['active_pct'], 1) }}%</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-amber-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-amber-600 shadow-sm"><i class="fas fa-hourglass-half text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700/70">Expiring soon</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['expiring_soon']) }} <span class="text-xs font-normal text-slate-400">within 24h</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-indigo-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm"><i class="fas fa-bullhorn text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700/70">Broadcast reach</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['active_broadcast']) }} <span class="text-xs font-normal text-slate-400">active broadcasts</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-primary-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-primary-600 shadow-sm"><i class="fas fa-language text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-700/70">Nepali coverage</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['bilingual']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($insights['coverage']['bilingual_pct'], 1) }}%</span></p>
            </div>
        </div>
    </section>

    {{-- Alert queue --}}
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div id="liveTable">
            <div class="border-b border-slate-100 p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900">Alert queue</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ number_format($alerts->total()) }} results</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Newest first
                            @if($alerts->hasPages())
                                &middot; page {{ $alerts->currentPage() }} of {{ $alerts->lastPage() }}
                            @endif
                            @if($hasFilters)
                                &middot; filtered
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($canManageAlerts)
                            <a href="#createAlert" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-plus"></i> Compose alert
                            </a>
                        @endif
                        <a href="{{ route('admin.alerts', request()->except('fragment')) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                            <i class="fas fa-rotate-right"></i> Refresh
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

                <div class="mt-3 flex flex-wrap items-center gap-1.5">
                    <span class="mr-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Severity</span>
                    <a href="{{ $severityUrl('all') }}" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition {{ $severity === 'all' ? 'bg-slate-900 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-700' }}">
                        All
                        <span class="rounded-full px-1.5 text-[10px] {{ $severity === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($counts['all']) }}</span>
                    </a>
                    @foreach($insights['severities'] as $row)
                        <a href="{{ $severityUrl($row['key']) }}" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition {{ $severity === $row['key'] ? 'bg-slate-900 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-700' }}">
                            <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $row['color'] }}"></span>
                            {{ $row['label'] }}
                            <span class="rounded-full px-1.5 text-[10px] {{ $severity === $row['key'] ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($row['value']) }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-5">
                <form method="GET" action="{{ route('admin.alerts') }}" class="space-y-3">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="severity" value="{{ $severity }}">
                    <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
                        <label class="relative block flex-1">
                            <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search title, district or creator..." aria-label="Search alerts" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="alert_type" aria-label="Filter by alert type" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All types</option>
                                @foreach($types as $type)
                                    <option value="{{ $type['key'] }}" {{ request('alert_type') === $type['key'] ? 'selected' : '' }}>{{ $type['label'] }} ({{ $type['count'] }})</option>
                                @endforeach
                            </select>
                            <select name="date_range" aria-label="Filter by date range" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All time</option>
                                <option value="today" {{ $dateRange === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="7days" {{ $dateRange === '7days' ? 'selected' : '' }}>Last 7 days</option>
                                <option value="30days" {{ $dateRange === '30days' ? 'selected' : '' }}>Last 30 days</option>
                            </select>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            @if($hasFilters)
                                <a href="{{ $clearUrl }}" class="inline-flex items-center gap-1.5 rounded-xl px-2.5 py-2.5 text-xs font-semibold text-slate-500 transition hover:text-slate-800">
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
                <table class="w-full min-w-[640px] lg:min-w-[900px]">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Alert</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Severity</th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 md:table-cell">Type</th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 xl:table-cell">Coverage</th>
                            <th scope="col" class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Status</th>
                            <th scope="col" class="hidden px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 lg:table-cell">Created</th>
                            <th scope="col" class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($alerts as $alert)
                            @php
                                $isExpired = $alert->expires_at !== null && $alert->expires_at->isPast();
                                $expiringSoon = !$isExpired && $alert->expires_at !== null && $alert->expires_at->lte(now()->addHours(24));
                                $typeLabel = $typeLabels[$alert->alert_type] ?? \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) $alert->alert_type));
                                $severityClass = $severityStyles[$alert->severity] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                                $districtLabel = $alert->affected_district ?: 'Platform-wide';
                                $hasNepali = filled($alert->title_ne) || filled($alert->description_ne);
                                $creatorName = $alert->creator?->name ?? 'System';
                                $sourceLabel = $alert->bipad_id || $alert->source_type === 'bipad'
                                    ? 'BIPAD'
                                    : ($alert->source_type === 'report' ? 'Report' : 'Manual');
                                if ($isExpired) {
                                    $statusLabel = 'Expired';
                                    $statusClass = 'bg-slate-100 text-slate-500 ring-slate-200';
                                    $statusDot = 'bg-slate-400';
                                    $statusHint = 'Expired '.$alert->expires_at->diffForHumans();
                                } elseif ($expiringSoon) {
                                    $statusLabel = 'Expiring soon';
                                    $statusClass = 'bg-amber-50 text-amber-700 ring-amber-200';
                                    $statusDot = 'bg-amber-500';
                                    $statusHint = 'Expires '.$alert->expires_at->diffForHumans();
                                } else {
                                    $statusLabel = 'Active';
                                    $statusClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                                    $statusDot = 'bg-emerald-500';
                                    $statusHint = $alert->expires_at
                                        ? 'Until '.$alert->expires_at->format('M j, H:i')
                                        : 'No expiry set';
                                }
                                $detailPayload = [
                                    'id' => (int) $alert->id,
                                    'title' => (string) $alert->title,
                                    'title_ne' => (string) ($alert->title_ne ?? ''),
                                    'description' => (string) ($alert->description ?? ''),
                                    'description_ne' => (string) ($alert->description_ne ?? ''),
                                    'type' => $typeLabel,
                                    'severity' => ucfirst((string) $alert->severity),
                                    'status' => $statusLabel,
                                    'district' => $districtLabel,
                                    'created_at' => $alert->created_at?->format('M j, Y g:i A'),
                                    'expires_at' => $alert->expires_at?->format('M j, Y g:i A'),
                                    'creator' => $creatorName,
                                    'source' => $sourceLabel,
                                    'broadcast' => (bool) $alert->is_broadcast,
                                    'bilingual' => $hasNepali,
                                    'latitude' => $alert->latitude !== null ? (float) $alert->latitude : null,
                                    'longitude' => $alert->longitude !== null ? (float) $alert->longitude : null,
                                    'report_id' => $alert->source_type === 'report' ? (int) $alert->source_id : null,
                                ];
                            @endphp
                            <tr data-alert='@json($detailPayload)' class="group transition hover:bg-slate-50/80 {{ $isExpired ? 'bg-slate-50/40' : '' }}">
                                <td class="px-4 py-3.5">
                                    <div class="min-w-0 max-w-[360px]">
                                        <button type="button" onclick="openAlertDetails(this.closest('tr'))" class="block max-w-full text-left">
                                            <span class="line-clamp-1 text-sm font-semibold text-slate-900 transition group-hover:text-primary-700">{{ $alert->title }}</span>
                                        </button>
                                        @if($hasNepali && $alert->title_ne)
                                            <p class="mt-0.5 line-clamp-1 text-xs text-primary-700/80">{{ $alert->title_ne }}</p>
                                        @endif
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-400">
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500">#{{ $alert->id }}</span>
                                            <span class="text-slate-300">&middot;</span>
                                            <span>{{ $sourceLabel }}</span>
                                            @if($alert->is_broadcast)
                                                <span class="inline-flex items-center gap-1 rounded bg-indigo-50 px-1.5 py-0.5 font-medium text-indigo-600"><i class="fas fa-bullhorn text-[9px]"></i> Broadcast</span>
                                            @endif
                                            @if($alert->latitude !== null && $alert->longitude !== null)
                                                <span class="inline-flex items-center gap-1 text-primary-600" title="Location pinned on the map"><i class="fas fa-location-dot text-[9px]"></i> Pinned</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $severityClass }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ ucfirst((string) $alert->severity) }}
                                    </span>
                                </td>
                                <td class="hidden px-4 py-3.5 md:table-cell">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600">{{ $typeLabel }}</span>
                                </td>
                                <td class="hidden px-4 py-3.5 xl:table-cell">
                                    <div class="min-w-0">
                                        @if($alert->is_broadcast)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                                <i class="fas fa-bullhorn text-[9px]"></i> All users
                                            </span>
                                        @else
                                            <span class="inline-flex max-w-[170px] items-center gap-1.5 truncate text-xs font-medium text-slate-700">
                                                <i class="fas fa-location-dot text-[10px] text-slate-400"></i> {{ $districtLabel }}
                                            </span>
                                        @endif
                                        <div class="mt-1 flex items-center gap-1">
                                            <span class="rounded bg-sky-50 px-1.5 py-0.5 text-[9px] font-bold text-sky-700 ring-1 ring-inset ring-sky-100">EN</span>
                                            @if($hasNepali)
                                                <span class="rounded bg-primary-50 px-1.5 py-0.5 text-[9px] font-bold text-primary-700 ring-1 ring-inset ring-primary-100">नेपाली</span>
                                            @else
                                                <span class="text-[10px] text-slate-400">No Nepali copy</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $statusClass }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>{{ $statusLabel }}
                                    </span>
                                    <p class="mt-1 text-[10px] text-slate-400">{{ $statusHint }}</p>
                                </td>
                                <td class="hidden px-4 py-3.5 lg:table-cell">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary-50 text-[10px] font-bold text-primary-700">{{ mb_strtoupper(mb_substr($creatorName, 0, 1)) }}</span>
                                        <div class="min-w-0">
                                            <p class="max-w-[120px] truncate text-xs font-medium text-slate-700">{{ $creatorName }}</p>
                                            <p class="text-[10px] text-slate-400" title="{{ $alert->created_at?->format('M j, Y g:i A') }}">{{ $alert->created_at?->diffForHumans(short: true) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button type="button" onclick="openAlertDetails(this.closest('tr'))" title="View alert #{{ $alert->id }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                                            <i class="fas fa-eye text-[11px]"></i>
                                        </button>
                                        @if($canManageAlerts)
                                            <form method="POST" action="{{ route('admin.alerts.delete', $alert->id) }}" data-native class="inline" onsubmit="return confirm('Delete alert #{{ $alert->id }}?\n\nThis removes it from the public alert feed and cannot be undone.')">
                                                @csrf
                                                <button type="submit" title="Delete alert #{{ $alert->id }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                                    <i class="fas fa-trash text-[11px]"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-14 text-center">
                                    <div class="mx-auto flex max-w-sm flex-col items-center">
                                        @if($status === 'active' && !$hasFilters)
                                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-500">
                                                <i class="fas fa-bell-slash text-xl"></i>
                                            </span>
                                            <p class="mt-3 text-sm font-semibold text-slate-800">No active alerts</p>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-400">Nothing is live on the public feed right now. Compose an alert when a hazard needs attention.</p>
                                        @elseif($status === 'expired' && !$hasFilters)
                                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                                                <i class="fas fa-clock-rotate-left text-xl"></i>
                                            </span>
                                            <p class="mt-3 text-sm font-semibold text-slate-800">No expired alerts</p>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-400">Every alert still has an active window or no expiry set.</p>
                                        @elseif($counts['all'] === 0)
                                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                                                <i class="fas fa-bell text-xl"></i>
                                            </span>
                                            <p class="mt-3 text-sm font-semibold text-slate-800">No alerts yet</p>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-400">Publish the first alert to warn users about a hazard or emergency.</p>
                                        @else
                                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                                                <i class="fas fa-inbox text-xl"></i>
                                            </span>
                                            <p class="mt-3 text-sm font-semibold text-slate-800">No alerts match this view</p>
                                            <p class="mt-1 text-xs leading-relaxed text-slate-400">Try a different status or clear one of the active filters.</p>
                                        @endif
                                        @if($canManageAlerts)
                                            <a href="#createAlert" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
                                                <i class="fas fa-plus"></i> Compose alert
                                            </a>
                                        @endif
                                        @if($hasFilters)
                                            <a href="{{ $clearUrl }}" class="mt-2 inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs font-semibold text-slate-500 transition hover:text-slate-800">
                                                <i class="fas fa-rotate-left"></i> Clear filters
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">
                    Showing
                    <span class="font-semibold text-slate-700">{{ $alerts->firstItem() ?? 0 }}–{{ $alerts->lastItem() ?? 0 }}</span>
                    of <span class="font-semibold text-slate-700">{{ number_format($alerts->total()) }}</span> alerts
                </p>
                @if($alerts->hasPages())
                    <nav class="flex items-center gap-1" role="navigation" aria-label="Alert pagination">
                        @if($alerts->onFirstPage())
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true"><i class="fas fa-chevron-left text-[10px]"></i></span>
                        @else
                            <a href="{{ $alerts->previousPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" aria-label="Previous page"><i class="fas fa-chevron-left text-[10px]"></i></a>
                        @endif

                        @php
                            $currentPage = $alerts->currentPage();
                            $lastPage = $alerts->lastPage();
                            $windowStart = max(1, $currentPage - 2);
                            $windowEnd = min($lastPage, $currentPage + 2);
                        @endphp

                        @if($windowStart > 1)
                            <a href="{{ $alerts->url(1) }}" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">1</a>
                            @if($windowStart > 2)
                                <span class="px-1 text-xs text-slate-400">&hellip;</span>
                            @endif
                        @endif

                        @foreach($alerts->getUrlRange($windowStart, $windowEnd) as $pageNumber => $pageUrl)
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
                            <a href="{{ $alerts->url($lastPage) }}" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">{{ $lastPage }}</a>
                        @endif

                        @if($alerts->hasMorePages())
                            <a href="{{ $alerts->nextPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" aria-label="Next page"><i class="fas fa-chevron-right text-[10px]"></i></a>
                        @else
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true"><i class="fas fa-chevron-right text-[10px]"></i></span>
                        @endif
                    </nav>
                @endif
            </div>
        </div>
    </section>

    {{-- Alert details (kept outside #liveTable so an AJAX list refresh cannot remove it) --}}
    <div id="alertDetailsModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="alertDetailTitle">
        <div class="flex min-h-full items-center justify-center px-4 py-8 text-center sm:p-6">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeAlertDetails()"></div>
            <div class="relative w-full max-w-2xl overflow-hidden rounded-3xl bg-white text-left shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
                    <div class="min-w-0">
                        <p id="alertDetailId" class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">Alert</p>
                        <h3 id="alertDetailTitle" class="mt-1 text-lg font-semibold text-slate-900"></h3>
                        <p id="alertDetailTitleNe" class="mt-0.5 hidden text-sm text-primary-700"></p>
                    </div>
                    <button type="button" onclick="closeAlertDetails()" class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close alert details">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-4">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span id="alertDetailSeverity" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"></span>
                        <span id="alertDetailType" class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600"></span>
                        <span id="alertDetailStatus" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"></span>
                    </div>

                    <div id="alertDetailBroadcast" class="hidden items-start gap-2 rounded-2xl border border-indigo-100 bg-indigo-50 px-3 py-2.5 text-xs text-indigo-700">
                        <i class="fas fa-bullhorn mt-0.5"></i>
                        <span>This is a broadcast alert. It reached every subscribed user regardless of location.</span>
                    </div>

                    <div class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-3 text-xs sm:grid-cols-2">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Coverage area</p>
                            <p id="alertDetailDistrict" class="mt-0.5 font-medium text-slate-700"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Created by</p>
                            <p id="alertDetailCreator" class="mt-0.5 font-medium text-slate-700"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Published</p>
                            <p id="alertDetailCreated" class="mt-0.5 font-medium text-slate-700"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Expiry</p>
                            <p id="alertDetailExpires" class="mt-0.5 font-medium text-slate-700"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Source</p>
                            <p id="alertDetailSource" class="mt-0.5 font-medium text-slate-700"></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Languages</p>
                            <p id="alertDetailLanguages" class="mt-0.5 font-medium text-slate-700"></p>
                        </div>
                    </div>

                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">English message</p>
                        <p id="alertDetailDescription" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-slate-600"></p>
                    </div>

                    <div id="alertDetailNepaliWrap" class="hidden rounded-2xl border border-primary-100 bg-primary-50/50 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-600">नेपाली message</p>
                        <p id="alertDetailDescriptionNe" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-slate-700"></p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <a id="alertDetailMapLink" href="#" target="_blank" rel="noopener" class="hidden inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-700">
                            <i class="fas fa-map-location-dot"></i> Open map
                        </a>
                        <a id="alertDetailReportLink" href="#" class="hidden inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-700">
                            <i class="fas fa-arrow-up-right-from-square"></i> Source report
                        </a>
                    </div>
                    <button type="button" onclick="closeAlertDetails()" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Composer --}}
    <section id="createAlert" class="scroll-mt-24 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="relative overflow-hidden bg-slate-900 px-5 py-4 text-white sm:px-6">
            <div class="pointer-events-none absolute -right-10 -top-16 h-40 w-40 rounded-full bg-primary-500/20 blur-2xl"></div>
            <div class="relative flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.24em] text-slate-400">Composer</p>
                    <h2 class="mt-1 text-lg font-semibold">Compose an alert</h2>
                    <p class="mt-1 max-w-xl text-xs leading-relaxed text-slate-300">Write once in English, add a Roman Nepali draft, then generate and review the Nepali version before publishing.</p>
                </div>
                <span class="inline-flex items-center gap-2 self-start rounded-2xl bg-white/10 px-3 py-2 text-[11px] font-semibold text-slate-200 sm:self-auto">
                    <i class="fas fa-shield-halved text-primary-300"></i> Requires manage_alerts
                </span>
            </div>
        </div>

        @if($canManageAlerts)
            <form method="POST" action="{{ route('admin.alerts.create') }}" id="alertForm" data-native class="p-4 sm:p-5">
                @csrf
                <input type="hidden" name="title" id="submit_title">
                <input type="hidden" name="description" id="submit_description">
                <input type="hidden" name="title_ne" id="submit_title_ne">
                <input type="hidden" name="description_ne" id="submit_description_ne">

                @if($errors->any())
                    <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        <p class="flex items-center gap-2 font-semibold"><i class="fas fa-triangle-exclamation"></i> Could not publish this alert</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="grid gap-5 xl:grid-cols-3">
                    <div class="space-y-5 xl:col-span-2">
                        {{-- Step 1: details --}}
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/40 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">1</span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Alert details</h3>
                                        <p class="text-[11px] text-slate-500">Type, severity and where the warning applies.</p>
                                    </div>
                                </div>
                                <span class="hidden rounded-full bg-white px-2 py-1 text-[10px] font-semibold text-slate-400 ring-1 ring-inset ring-slate-200 sm:inline-flex">Required</span>
                            </div>

                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="alertType" class="mb-1 block text-xs font-semibold text-slate-600">Alert type <span class="text-rose-500">*</span></label>
                                    <select name="alert_type" id="alertType" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                        @foreach($typeLabels as $value => $label)
                                            <option value="{{ $value }}" {{ old('alert_type', 'weather') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="alertSeverity" class="mb-1 block text-xs font-semibold text-slate-600">Severity <span class="text-rose-500">*</span></label>
                                    <select name="severity" id="alertSeverity" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                        @foreach(['info' => 'Info', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                                            <option value="{{ $value }}" {{ old('severity', 'medium') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="affectedDistrict" class="mb-1 block text-xs font-semibold text-slate-600">Affected district / area <span class="text-rose-500">*</span></label>
                                    <input type="text" name="affected_district" id="affectedDistrict" value="{{ old('affected_district') }}" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100" placeholder="e.g. Kathmandu, Sindhupalchok or All Nepal">
                                </div>
                                <div>
                                    <label for="expiresAt" class="mb-1 block text-xs font-semibold text-slate-600">Expiry time <span class="font-normal text-slate-400">(optional)</span></label>
                                    <input type="datetime-local" name="expires_at" id="expiresAt" value="{{ old('expires_at') }}" min="{{ now()->format('Y-m-d\TH:i') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                    <p class="mt-1 text-[10px] text-slate-400">Leave empty for no expiry. Expired alerts drop off the public feed automatically.</p>
                                </div>

                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 sm:col-span-2">
                                    <input type="checkbox" name="is_broadcast" value="1" id="broadcastToggle" onchange="toggleBroadcast(this.checked)" class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500" {{ old('is_broadcast') ? 'checked' : '' }}>
                                    <span>
                                        <span class="block text-xs font-semibold text-slate-700">Broadcast to all users</span>
                                        <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-500">Reach every subscribed user platform-wide. Location is not required and no map pin is sent.</span>
                                    </span>
                                </label>

                                <div id="locationBox" class="sm:col-span-2 {{ old('is_broadcast') ? 'hidden' : '' }}">
                                    <div class="mb-1 flex items-center justify-between gap-2">
                                        <label class="block text-xs font-semibold text-slate-600">Location <span class="font-normal text-slate-400">(optional)</span></label>
                                        <span class="inline-flex items-center gap-1 text-[10px] text-slate-400"><i class="fas fa-crosshairs"></i> Click the map to pin</span>
                                    </div>
                                    <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                                        <div id="alertMap" class="z-0 h-56 w-full" style="cursor:crosshair;"></div>
                                        <div id="alertMapFallback" class="absolute inset-0 hidden items-center justify-center bg-slate-50 px-4 text-center text-xs text-slate-400">
                                            <span><i class="fas fa-map-location-dot mb-1 block text-lg text-slate-300"></i>Map could not load. You can still publish without a pin.</span>
                                        </div>
                                    </div>
                                    <input type="hidden" name="latitude" id="alertLat" value="{{ old('latitude') }}">
                                    <input type="hidden" name="longitude" id="alertLng" value="{{ old('longitude') }}">
                                    <p id="locReadout" class="mt-1 text-[11px] text-slate-500">
                                        @if(old('latitude') && old('longitude'))
                                            Selected: {{ old('latitude') }}, {{ old('longitude') }}
                                        @else
                                            No location selected — click the map to add a pin.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Step 2: message and languages --}}
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">2</span>
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Message &amp; languages</h3>
                                        <p class="text-[11px] text-slate-500">English is the canonical copy. Nepali is optional but recommended.</p>
                                    </div>
                                </div>
                                <span class="hidden rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500 sm:inline-flex">EN + नेपाली</span>
                            </div>

                            <div class="mt-4 rounded-2xl bg-slate-100 p-1">
                                <nav class="flex gap-1" aria-label="Alert content language">
                                    <button type="button" class="lang-tab flex-1 rounded-xl px-3 py-2 text-xs font-semibold transition" data-lang="en" onclick="switchLangTab('en')">
                                        <i class="fas fa-globe-americas mr-1"></i> English
                                    </button>
                                    <button type="button" class="lang-tab flex-1 rounded-xl px-3 py-2 text-xs font-semibold transition" data-lang="roman" onclick="switchLangTab('roman')">
                                        <i class="fas fa-font mr-1"></i> Roman Nepali
                                    </button>
                                    <button type="button" class="lang-tab flex-1 rounded-xl px-3 py-2 text-xs font-semibold transition" data-lang="ne" onclick="switchLangTab('ne')">
                                        <i class="fas fa-language mr-1"></i> नेपाली
                                    </button>
                                </nav>
                            </div>

                            <div id="lang-en" class="lang-panel mt-3">
                                <div class="space-y-3">
                                    <div>
                                        <label for="title_en" class="mb-1 block text-xs font-semibold text-slate-600">Title (English)</label>
                                        <input type="text" id="title_en" value="{{ old('title') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100" placeholder="e.g. Heavy rainfall warning for central Nepal">
                                    </div>
                                    <div>
                                        <label for="description_en" class="mb-1 block text-xs font-semibold text-slate-600">Description (English)</label>
                                        <textarea id="description_en" rows="5" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100" placeholder="What happened, who is affected, and what should people do?">{{ old('description') }}</textarea>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" onclick="switchLangTab('roman')" id="translateEnBtn" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                                            <i class="fas fa-arrow-right"></i> Continue in Roman Nepali
                                        </button>
                                        <button type="button" onclick="previewAlert('en')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-700">
                                            <i class="fas fa-eye"></i> Preview English
                                        </button>
                                    </div>
                                    <p class="text-[10px] leading-relaxed text-slate-400">Tip: keep it short. Push notifications only show the first 100 characters of the description.</p>
                                </div>
                            </div>

                            <div id="lang-roman" class="lang-panel mt-3 hidden">
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 text-[11px] leading-relaxed text-amber-700">
                                        <i class="fas fa-pen-ruler mr-1"></i> Write naturally in Roman Nepali, then generate the Devanagari version below. The generated text is a draft — review it before publishing.
                                    </div>
                                    <div>
                                        <label for="title_roman" class="mb-1 block text-xs font-semibold text-slate-600">Title (Roman Nepali)</label>
                                        <input type="text" id="title_roman" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100" placeholder="e.g. Nepal ma bhari barsa ko karan...">
                                    </div>
                                    <div>
                                        <label for="description_roman" class="mb-1 block text-xs font-semibold text-slate-600">Description (Roman Nepali)</label>
                                        <textarea id="description_roman" rows="5" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100" placeholder="e.g. Aabasyak kaam bahek bahira nabiskinu hola."></textarea>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" onclick="generateNepali()" id="generateNepaliBtn" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                                            <i class="fas fa-wand-magic-sparkles"></i> Generate नेपाली
                                        </button>
                                        <button type="button" onclick="previewAlert('roman')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-700">
                                            <i class="fas fa-eye"></i> Preview Roman
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="lang-ne" class="lang-panel mt-3 hidden">
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-primary-100 bg-primary-50 px-3 py-2 text-[11px] leading-relaxed text-primary-700">
                                        <i class="fas fa-language mr-1"></i> Review and edit the generated Nepali text, or write directly in Devanagari.
                                    </div>
                                    <div>
                                        <label for="title_ne" class="mb-1 block text-xs font-semibold text-slate-600">Title (नेपाली)</label>
                                        <input type="text" id="title_ne" value="{{ old('title_ne') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100" placeholder="e.g. नेपालमा भारी वर्षाका कारण...">
                                    </div>
                                    <div>
                                        <label for="description_ne" class="mb-1 block text-xs font-semibold text-slate-600">Description (नेपाली)</label>
                                        <textarea id="description_ne" rows="5" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100" placeholder="e.g. नेपालमा भारी वर्षाका कारण आवश्यक काम बाहेक बाहिर ननिस्किनु होला।">{{ old('description_ne') }}</textarea>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" onclick="previewAlert('ne')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-700">
                                            <i class="fas fa-eye"></i> Preview नेपाली
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="nepaliPreviewPanel" class="mt-4 hidden rounded-2xl border border-primary-200 bg-primary-50 p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold text-primary-800">
                                        <i class="fas fa-wand-magic-sparkles"></i> Generated नेपाली draft
                                    </h4>
                                    <button type="button" onclick="closeNepaliPreview()" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-primary-600 transition hover:bg-primary-100" aria-label="Close generated Nepali draft">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </div>
                                <div class="mt-3 space-y-3">
                                    <div>
                                        <label for="preview_title_ne" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-primary-700">Title (नेपाली)</label>
                                        <textarea id="preview_title_ne" rows="2" readonly class="w-full rounded-xl border border-primary-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100"></textarea>
                                    </div>
                                    <div>
                                        <label for="preview_description_ne" class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-primary-700">Description (नेपाली)</label>
                                        <textarea id="preview_description_ne" rows="5" readonly class="w-full rounded-xl border border-primary-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100"></textarea>
                                    </div>
                                    <div class="flex flex-wrap gap-2 border-t border-primary-100 pt-3">
                                        <button type="button" onclick="regenerateNepali()" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-200 transition hover:bg-primary-100">
                                            <i class="fas fa-rotate"></i> Regenerate
                                        </button>
                                        <button type="button" onclick="editNepali()" class="inline-flex items-center gap-1.5 rounded-xl bg-primary-100 px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-200">
                                            <i class="fas fa-pen"></i> Edit draft
                                        </button>
                                        <button type="button" onclick="useNepaliVersion()" class="inline-flex items-center gap-1.5 rounded-xl bg-primary-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-800">
                                            <i class="fas fa-check"></i> Use नेपाली version
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="translationStatus" class="hidden mt-3 rounded-xl border px-3 py-2.5 text-xs"></div>
                        </div>

                        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-[11px] leading-relaxed text-slate-400">
                                <i class="fas fa-circle-info mr-1"></i>
                                Publishing sends a push notification to nearby users, or to everyone for a broadcast.
                            </p>
                            <div class="flex flex-shrink-0 items-center gap-2">
                                <button type="button" onclick="resetAlertComposer()" class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-200">Reset</button>
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                                    <i class="fas fa-paper-plane"></i> Publish alert
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 xl:sticky xl:top-4 xl:self-start">
                        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">3</span>
                                    <h3 class="text-sm font-semibold text-slate-900">Live preview</h3>
                                </div>
                                <span id="previewLangIndicator" class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500">Previewing: English</span>
                            </div>
                            <div class="bg-slate-100 p-3">
                                <div id="alertPreview" class="min-h-[240px]">
                                    <div class="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center">
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-500">
                                            <i class="fas fa-mobile-screen-button text-lg"></i>
                                        </span>
                                        <p class="mt-3 text-sm font-semibold text-slate-700">Your alert preview appears here</p>
                                        <p class="mt-1 text-[11px] leading-relaxed text-slate-400">Fill in the message, then click Update preview to see how it looks on a phone.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="border-t border-slate-100 p-3">
                                <button type="button" onclick="previewAlert()" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-slate-800">
                                    <i class="fas fa-rotate"></i> Update preview
                                </button>
                                <p class="mt-2 text-center text-[10px] text-slate-400">Preview reflects the last update, not live typing.</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <h3 class="flex items-center gap-2 text-xs font-semibold text-slate-800">
                                <i class="fas fa-list-check text-primary-600"></i> Delivery checklist
                            </h3>
                            <ul class="mt-3 space-y-2.5 text-[11px] leading-relaxed text-slate-500">
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><i class="fas fa-check text-[8px]"></i></span>
                                    <span>English title and description are the canonical copy used in the feed.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600"><i class="fas fa-language text-[8px]"></i></span>
                                    <span>Nepali is optional, but bilingual alerts reach more people.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600"><i class="fas fa-bullhorn text-[8px]"></i></span>
                                    <span>Broadcast reaches every user and ignores the map pin.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-amber-50 text-amber-600"><i class="fas fa-clock text-[8px]"></i></span>
                                    <span>Expired alerts drop off the public feed automatically.</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 inline-flex h-4 w-4 flex-shrink-0 items-center justify-center rounded-full bg-sky-50 text-sky-600"><i class="fas fa-location-dot text-[8px]"></i></span>
                                    <span>Without a pin or broadcast, the alert is saved but no push is sent.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </form>
        @else
            <div class="p-5">
                <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <i class="fas fa-lock mt-0.5"></i>
                    <div>
                        <p class="font-semibold">You can review alerts but not publish them</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-amber-700">Creating and deleting alerts requires the <code class="rounded bg-amber-100 px-1 py-0.5">manage_alerts</code> permission. Ask an administrator to update your role if you need composer access.</p>
                    </div>
                </div>
            </div>
        @endif
    </section>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var trend = @json($insights['trend']);
    var severities = @json($insights['severities']);

    function showChartFallback(id) {
        var el = document.getElementById(id);
        if (el) {
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
    }

    function showAllFallbacks() {
        showChartFallback('alertsTrendFallback');
        showChartFallback('alertsSeverityFallback');
    }

    function destroyAlertCharts() {
        if (!window.__alertsCharts) {
            window.__alertsCharts = {};
            return;
        }
        Object.keys(window.__alertsCharts).forEach(function (key) {
            try { window.__alertsCharts[key].destroy(); } catch (e) {}
        });
        window.__alertsCharts = {};
    }

    function initAlertCharts() {
        if (!window.__alertsCharts) window.__alertsCharts = {};

        if (!window.Chart) {
            showAllFallbacks();
            return;
        }

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';

        var trendCanvas = document.getElementById('alertsTrendChart');
        if (trendCanvas && !window.__alertsCharts.trend) {
            window.__alertsCharts.trend = new Chart(trendCanvas, {
                type: 'bar',
                data: {
                    labels: trend.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Published',
                            data: trend.created,
                            backgroundColor: 'rgba(15, 118, 110, 0.72)',
                            hoverBackgroundColor: 'rgba(15, 118, 110, 0.95)',
                            borderRadius: 4,
                            maxBarThickness: 14,
                            order: 2
                        },
                        {
                            type: 'line',
                            label: 'Critical / high',
                            data: trend.urgent,
                            borderColor: '#f43f5e',
                            backgroundColor: 'rgba(244, 63, 94, 0.10)',
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

        var severityCanvas = document.getElementById('alertsSeverityChart');
        if (severityCanvas && !window.__alertsCharts.severity) {
            var hasSeverityData = severities.some(function (row) { return Number(row.value) > 0; });
            if (!hasSeverityData) {
                showChartFallback('alertsSeverityFallback');
            } else {
                window.__alertsCharts.severity = new Chart(severityCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: severities.map(function (row) { return row.label; }),
                        datasets: [{
                            data: severities.map(function (row) { return Number(row.value); }),
                            backgroundColor: severities.map(function (row) { return row.color; }),
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

        var existing = document.querySelector('script[data-report-chartjs], script[data-alert-chartjs]');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            existing.addEventListener('error', showAllFallbacks, { once: true });
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.async = true;
        script.setAttribute('data-alert-chartjs', '1');
        script.addEventListener('load', callback, { once: true });
        script.addEventListener('error', showAllFallbacks, { once: true });
        document.head.appendChild(script);
    }

    destroyAlertCharts();
    ensureChartLibrary(initAlertCharts);

    // Breakdown tabs (outside #liveTable, so listeners survive list refreshes).
    var breakdownButtons = Array.prototype.slice.call(document.querySelectorAll('[data-alert-breakdown-tab]'));
    breakdownButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-alert-breakdown-tab');
            breakdownButtons.forEach(function (other) {
                var active = other === button;
                other.classList.toggle('bg-white', active);
                other.classList.toggle('shadow-sm', active);
                other.classList.toggle('text-slate-900', active);
                other.classList.toggle('text-slate-500', !active);
            });
            document.querySelectorAll('[data-alert-breakdown-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-alert-breakdown-panel') !== target);
            });
            if (target === 'severity' && window.__alertsCharts && window.__alertsCharts.severity) {
                window.__alertsCharts.severity.resize();
            }
        });
    });
})();
</script>
<script>
(function () {
    'use strict';

    var currentLangTab = 'en';
    var nepaliPreviewData = { title: '', description: '' };
    var alertMap = null;
    var alertMarker = null;
    var statusTimer = null;

    function el(id) {
        return document.getElementById(id);
    }

    function setText(id, value) {
        var node = el(id);
        if (node) node.textContent = value == null || value === '' ? '' : String(value);
    }

    function toggleHidden(id, hide) {
        var node = el(id);
        if (!node) return;
        node.classList.toggle('hidden', hide);
        if (!hide && id === 'alertDetailBroadcast') node.classList.add('flex');
    }

    // ---------------------------------------------------------------
    // Alert details modal
    // ---------------------------------------------------------------
    var detailSeverityClasses = {
        Critical: 'bg-rose-50 text-rose-700 ring-rose-200',
        High: 'bg-orange-50 text-orange-700 ring-orange-200',
        Medium: 'bg-amber-50 text-amber-700 ring-amber-200',
        Low: 'bg-lime-50 text-lime-700 ring-lime-200',
        Info: 'bg-sky-50 text-sky-700 ring-sky-200'
    };
    var detailStatusClasses = {
        Active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'Expiring soon': 'bg-amber-50 text-amber-700 ring-amber-200',
        Expired: 'bg-slate-100 text-slate-500 ring-slate-200'
    };

    window.openAlertDetails = function (source) {
        var raw = source && source.getAttribute ? source.getAttribute('data-alert') : null;
        if (!raw) return;

        var data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            return;
        }

        setText('alertDetailId', 'Alert #' + data.id);
        setText('alertDetailTitle', data.title);
        setText('alertDetailTitleNe', data.title_ne);
        toggleHidden('alertDetailTitleNe', !data.title_ne);
        setText('alertDetailType', data.type);
        setText('alertDetailDistrict', data.district);
        setText('alertDetailCreator', data.creator);
        setText('alertDetailCreated', data.created_at);
        setText('alertDetailExpires', data.expires_at || 'No expiry');
        setText('alertDetailSource', data.source);
        setText('alertDetailLanguages', data.bilingual ? 'English + नेपाली' : 'English only');
        setText('alertDetailDescription', data.description || 'No description provided.');
        setText('alertDetailDescriptionNe', data.description_ne);
        toggleHidden('alertDetailNepaliWrap', !data.description_ne);
        toggleHidden('alertDetailBroadcast', !data.broadcast);

        var severity = el('alertDetailSeverity');
        if (severity) {
            severity.className = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset ' + (detailSeverityClasses[data.severity] || 'bg-slate-100 text-slate-600 ring-slate-200');
            severity.textContent = data.severity;
        }

        var status = el('alertDetailStatus');
        if (status) {
            status.className = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset ' + (detailStatusClasses[data.status] || 'bg-slate-100 text-slate-600 ring-slate-200');
            status.textContent = data.status;
        }

        var mapLink = el('alertDetailMapLink');
        if (mapLink) {
            if (data.latitude !== null && data.longitude !== null && data.latitude !== undefined && data.longitude !== undefined) {
                var lat = Number(data.latitude);
                var lng = Number(data.longitude);
                mapLink.href = 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lng + '#map=13/' + lat + '/' + lng;
                mapLink.classList.remove('hidden');
            } else {
                mapLink.classList.add('hidden');
            }
        }

        var reportLink = el('alertDetailReportLink');
        if (reportLink) {
            if (data.report_id) {
                reportLink.href = '{{ url('/admin/reports') }}/' + data.report_id;
                reportLink.classList.remove('hidden');
            } else {
                reportLink.classList.add('hidden');
            }
        }

        var modal = el('alertDetailsModal');
        if (modal) modal.classList.remove('hidden');
    };

    window.closeAlertDetails = function () {
        var modal = el('alertDetailsModal');
        if (modal) modal.classList.add('hidden');
    };

    if (!window.__alertDetailsKeyBound) {
        window.__alertDetailsKeyBound = true;
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') window.closeAlertDetails();
        });
    }

    // ---------------------------------------------------------------
    // Composer status messages
    // ---------------------------------------------------------------
    function showStatus(message, type) {
        var container = el('translationStatus');
        if (!container) return;

        var styles = {
            success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
            error: 'border-rose-200 bg-rose-50 text-rose-700',
            warning: 'border-amber-200 bg-amber-50 text-amber-700',
            info: 'border-sky-200 bg-sky-50 text-sky-700'
        };
        var icons = {
            success: 'fa-check-circle',
            error: 'fa-circle-exclamation',
            warning: 'fa-triangle-exclamation',
            info: 'fa-circle-info'
        };

        container.className = 'mt-3 rounded-xl border px-3 py-2.5 text-xs ' + (styles[type] || styles.info);
        container.textContent = '';

        var icon = document.createElement('i');
        icon.className = 'fas ' + (icons[type] || icons.info) + ' mr-2';
        container.appendChild(icon);

        var span = document.createElement('span');
        span.textContent = message;
        container.appendChild(span);

        container.classList.remove('hidden');
        clearTimeout(statusTimer);
        if (type !== 'error') {
            statusTimer = setTimeout(function () {
                container.classList.add('hidden');
            }, 6500);
        }
    }

    // ---------------------------------------------------------------
    // Location map (Leaflet loaded on demand, SPA-safe)
    // ---------------------------------------------------------------
    function ensureLeafletCss() {
        if (document.querySelector('link[href*="leaflet@1.9.4/dist/leaflet.css"]')) return;
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        link.setAttribute('data-alert-leaflet', '1');
        document.head.appendChild(link);
    }

    function ensureLeaflet() {
        ensureLeafletCss();

        if (window.L) return Promise.resolve();

        if (window.__alertLeafletPromise) return window.__alertLeafletPromise;

        window.__alertLeafletPromise = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.async = true;
            script.setAttribute('data-alert-leaflet', '1');
            script.addEventListener('load', function () {
                if (window.L) {
                    resolve();
                } else {
                    window.__alertLeafletPromise = null;
                    reject(new Error('Leaflet unavailable'));
                }
            }, { once: true });
            script.addEventListener('error', function () {
                window.__alertLeafletPromise = null;
                reject(new Error('Leaflet failed to load'));
            }, { once: true });
            document.head.appendChild(script);
        });

        return window.__alertLeafletPromise;
    }

    function showMapFallback() {
        var fallback = el('alertMapFallback');
        if (fallback) {
            fallback.classList.remove('hidden');
            fallback.classList.add('flex');
        }
    }

    function setMapReadout(text) {
        var readout = el('locReadout');
        if (readout) readout.textContent = text;
    }

    function initAlertMap() {
        var container = el('alertMap');
        if (!container || alertMap) return;
        if (container._leaflet_id) return;

        ensureLeaflet().then(function () {
            if (!el('alertMap') || alertMap) return;

            var start = [28.3949, 84.1240];
            var latInput = el('alertLat');
            var lngInput = el('alertLng');
            var startLat = latInput ? parseFloat(latInput.value) : NaN;
            var startLng = lngInput ? parseFloat(lngInput.value) : NaN;
            var hasStart = Number.isFinite(startLat) && Number.isFinite(startLng);
            if (hasStart) start = [startLat, startLng];

            alertMap = window.L.map(container, { scrollWheelZoom: false }).setView(start, hasStart ? 11 : 7);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(alertMap);

            if (hasStart) {
                alertMarker = window.L.circleMarker(start, {
                    radius: 7,
                    color: '#0f766e',
                    weight: 2,
                    fillColor: '#14b8a6',
                    fillOpacity: 1
                }).addTo(alertMap);
                setMapReadout('Selected: ' + startLat.toFixed(6) + ', ' + startLng.toFixed(6));
            }

            alertMap.on('click', function (event) {
                var lat = event.latlng.lat.toFixed(6);
                var lng = event.latlng.lng.toFixed(6);
                if (latInput) latInput.value = lat;
                if (lngInput) lngInput.value = lng;
                if (alertMarker) {
                    alertMarker.setLatLng(event.latlng);
                } else {
                    alertMarker = window.L.circleMarker(event.latlng, {
                        radius: 7,
                        color: '#0f766e',
                        weight: 2,
                        fillColor: '#14b8a6',
                        fillOpacity: 1
                    }).addTo(alertMap);
                }
                setMapReadout('Selected: ' + lat + ', ' + lng);
            });

            setTimeout(function () {
                if (alertMap) alertMap.invalidateSize();
            }, 150);
        }).catch(showMapFallback);
    }

    window.toggleBroadcast = function (broadcast) {
        var box = el('locationBox');
        if (box) box.classList.toggle('hidden', !!broadcast);

        if (broadcast) {
            var lat = el('alertLat');
            var lng = el('alertLng');
            if (lat) lat.value = '';
            if (lng) lng.value = '';
            if (alertMarker) {
                alertMarker.remove();
                alertMarker = null;
            }
            setMapReadout('Broadcast alert — no location pin will be sent.');
        } else {
            setMapReadout('No location selected — click the map to add a pin.');
            setTimeout(function () {
                if (alertMap) alertMap.invalidateSize();
            }, 120);
        }
    };

    // ---------------------------------------------------------------
    // Language tabs
    // ---------------------------------------------------------------
    window.switchLangTab = function (lang) {
        currentLangTab = lang;

        document.querySelectorAll('.lang-tab').forEach(function (button) {
            button.classList.remove('bg-slate-900', 'text-white', 'shadow-sm');
            button.classList.add('text-slate-500', 'hover:bg-white', 'hover:text-slate-800');
        });
        var activeTab = document.querySelector('.lang-tab[data-lang="' + lang + '"]');
        if (activeTab) {
            activeTab.classList.add('bg-slate-900', 'text-white', 'shadow-sm');
            activeTab.classList.remove('text-slate-500', 'hover:bg-white', 'hover:text-slate-800');
        }

        document.querySelectorAll('.lang-panel').forEach(function (panel) {
            panel.classList.add('hidden');
        });
        var activePanel = el('lang-' + lang);
        if (activePanel) activePanel.classList.remove('hidden');

        var labels = { en: 'English', roman: 'Roman Nepali', ne: 'नेपाली' };
        setText('previewLangIndicator', 'Previewing: ' + (labels[lang] || lang));
    };

    // ---------------------------------------------------------------
    // Roman Nepali -> Devanagari generation
    // ---------------------------------------------------------------
    window.generateNepali = async function () {
        var title = el('title_roman') ? el('title_roman').value.trim() : '';
        var description = el('description_roman') ? el('description_roman').value.trim() : '';

        if (!title && !description) {
            showStatus('Please enter Roman Nepali content first.', 'error');
            window.switchLangTab('roman');
            return;
        }

        var button = el('generateNepaliBtn');
        var originalHtml = button ? button.innerHTML : '';
        if (button) {
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            button.disabled = true;
        }

        try {
            var response = await fetch('{{ route('admin.alerts.translate') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    title: title,
                    description: description,
                    source_language: 'roman_nepali'
                })
            });

            var data = await response.json().catch(function () { return {}; });

            if (response.ok && data.success && (data.title_ne || data.description_ne)) {
                nepaliPreviewData.title = data.title_ne || '';
                nepaliPreviewData.description = data.description_ne || '';
                showNepaliPreview();
                var message = 'नेपाली draft generated. Review it before publishing.';
                if (Array.isArray(data.warnings) && data.warnings.length) {
                    message += ' Notes: ' + data.warnings.join('; ');
                }
                showStatus(message, 'success');
            } else {
                var failure = data.message || 'Translation failed. You can still enter the नेपाली text manually.';
                if (Array.isArray(data.warnings) && data.warnings.length) {
                    failure += ' ' + data.warnings.join('; ');
                }
                showStatus(failure, data.rate_limited ? 'warning' : 'error');
            }
        } catch (e) {
            showStatus('Network error while generating Nepali. Please try again.', 'error');
        } finally {
            if (button) {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        }
    };

    window.regenerateNepali = function () {
        return window.generateNepali();
    };

    function updatePreviewPanelReadonly(readonly) {
        var title = el('preview_title_ne');
        var description = el('preview_description_ne');
        if (title) title.readOnly = readonly;
        if (description) description.readOnly = readonly;
    }

    function showNepaliPreview() {
        var title = el('preview_title_ne');
        var description = el('preview_description_ne');
        if (title) title.value = nepaliPreviewData.title;
        if (description) description.value = nepaliPreviewData.description;

        var panel = el('nepaliPreviewPanel');
        if (panel) panel.classList.remove('hidden');
        updatePreviewPanelReadonly(true);
    }

    window.editNepali = function () {
        updatePreviewPanelReadonly(false);
        var title = el('preview_title_ne');
        if (title) title.focus();
    };

    window.useNepaliVersion = function () {
        var previewTitle = el('preview_title_ne');
        var previewDescription = el('preview_description_ne');
        var title = el('title_ne');
        var description = el('description_ne');

        if (title) title.value = previewTitle ? previewTitle.value : '';
        if (description) description.value = previewDescription ? previewDescription.value : '';

        var panel = el('nepaliPreviewPanel');
        if (panel) panel.classList.add('hidden');
        showStatus('नेपाली version applied to the Nepali tab. You can edit it there before publishing.', 'success');
        window.switchLangTab('ne');
    };

    window.closeNepaliPreview = function () {
        var panel = el('nepaliPreviewPanel');
        if (panel) panel.classList.add('hidden');
    };

    // ---------------------------------------------------------------
    // Preview
    // ---------------------------------------------------------------
    window.previewAlert = async function (lang) {
        lang = lang || currentLangTab || 'en';

        var title = '';
        var description = '';

        if (lang === 'en') {
            title = el('title_en') ? el('title_en').value.trim() : '';
            description = el('description_en') ? el('description_en').value.trim() : '';
        } else if (lang === 'roman') {
            title = el('title_roman') ? el('title_roman').value.trim() : '';
            description = el('description_roman') ? el('description_roman').value.trim() : '';
        } else {
            title = el('title_ne') ? el('title_ne').value.trim() : '';
            description = el('description_ne') ? el('description_ne').value.trim() : '';
            if (!title) title = el('title_roman') ? el('title_roman').value.trim() : (el('title_en') ? el('title_en').value.trim() : '');
            if (!description) description = el('description_roman') ? el('description_roman').value.trim() : (el('description_en') ? el('description_en').value.trim() : '');
        }

        if (!title || !description) {
            showStatus('Add a title and description in this language before previewing.', 'error');
            window.switchLangTab(lang);
            return;
        }

        var alertType = el('alertType') ? el('alertType').value : 'weather';
        var severity = el('alertSeverity') ? el('alertSeverity').value : 'medium';
        var district = el('affectedDistrict') && el('affectedDistrict').value.trim() ? el('affectedDistrict').value.trim() : 'All Areas';
        var titleNe = el('title_ne') ? el('title_ne').value.trim() : '';
        var descriptionNe = el('description_ne') ? el('description_ne').value.trim() : '';
        var preview = el('alertPreview');

        if (preview) {
            preview.innerHTML = '<div class="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-8 text-center"><i class="fas fa-spinner fa-spin text-2xl text-primary-500"></i><p class="mt-3 text-xs font-semibold text-slate-500">Generating preview...</p></div>';
        }

        try {
            var response = await fetch('{{ route('admin.alerts.preview') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    title: title,
                    description: description,
                    alert_type: alertType,
                    severity: severity,
                    affected_district: district,
                    title_ne: titleNe,
                    description_ne: descriptionNe,
                    language: lang === 'ne' ? 'nepali' : 'english'
                })
            });

            var data = await response.json().catch(function () { return {}; });

            if (response.ok && data.success && data.html) {
                if (preview) preview.innerHTML = data.html;
                var labels = { en: 'English', roman: 'Roman Nepali', ne: 'नेपाली' };
                setText('previewLangIndicator', 'Previewing: ' + (labels[lang] || lang));
                showStatus('Preview updated.', 'success');
            } else {
                throw new Error(data.message || 'Preview failed.');
            }
        } catch (e) {
            if (preview) {
                preview.innerHTML = '<div class="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-rose-200 bg-white px-5 py-8 text-center"><i class="fas fa-triangle-exclamation text-2xl text-rose-500"></i><p class="mt-3 text-xs font-semibold text-rose-600">Preview failed</p><p class="mt-1 text-[11px] text-slate-400">Check the title, description and required fields, then try again.</p></div>';
            }
            showStatus('Could not generate the preview. Check the fields and try again.', 'error');
        }
    };

    // ---------------------------------------------------------------
    // Reset + submit
    // ---------------------------------------------------------------
    window.resetAlertComposer = function () {
        var form = el('alertForm');
        if (form) form.reset();

        ['title_en', 'description_en', 'title_roman', 'description_roman', 'title_ne', 'description_ne', 'affectedDistrict', 'expiresAt', 'alertLat', 'alertLng', 'submit_title', 'submit_description', 'submit_title_ne', 'submit_description_ne', 'preview_title_ne', 'preview_description_ne'].forEach(function (id) {
            var node = el(id);
            if (node) node.value = '';
        });

        var alertType = el('alertType');
        if (alertType) alertType.value = 'weather';
        var severity = el('alertSeverity');
        if (severity) severity.value = 'medium';
        var broadcast = el('broadcastToggle');
        if (broadcast) broadcast.checked = false;

        nepaliPreviewData = { title: '', description: '' };
        var panel = el('nepaliPreviewPanel');
        if (panel) panel.classList.add('hidden');
        var status = el('translationStatus');
        if (status) status.classList.add('hidden');

        var preview = el('alertPreview');
        if (preview) {
            preview.innerHTML = '<div class="flex min-h-[240px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center"><span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-500"><i class="fas fa-mobile-screen-button text-lg"></i></span><p class="mt-3 text-sm font-semibold text-slate-700">Your alert preview appears here</p><p class="mt-1 text-[11px] leading-relaxed text-slate-400">Fill in the message, then click Update preview to see how it looks on a phone.</p></div>';
        }

        if (alertMarker) {
            alertMarker.remove();
            alertMarker = null;
        }
        if (alertMap) alertMap.setView([28.3949, 84.1240], 7);
        setMapReadout('No location selected — click the map to add a pin.');
        window.toggleBroadcast(false);
        window.switchLangTab('en');
    };

    var alertForm = el('alertForm');
    if (alertForm) {
        alertForm.addEventListener('submit', function (event) {
            var titleEn = el('title_en') ? el('title_en').value.trim() : '';
            var descriptionEn = el('description_en') ? el('description_en').value.trim() : '';
            var titleRoman = el('title_roman') ? el('title_roman').value.trim() : '';
            var descriptionRoman = el('description_roman') ? el('description_roman').value.trim() : '';
            var titleNe = el('title_ne') ? el('title_ne').value.trim() : '';
            var descriptionNe = el('description_ne') ? el('description_ne').value.trim() : '';

            var title = titleEn || titleRoman || titleNe;
            var description = descriptionEn || descriptionRoman || descriptionNe;

            if (!title || !description) {
                event.preventDefault();
                showStatus('Add a title and description in at least one language before publishing.', 'error');
                if (!titleEn && !descriptionEn) {
                    window.switchLangTab(titleRoman || descriptionRoman ? 'roman' : 'ne');
                }
                return false;
            }

            var nepaliPanel = el('nepaliPreviewPanel');
            var generatedVisible = nepaliPanel && !nepaliPanel.classList.contains('hidden');
            if (!titleNe && generatedVisible && el('preview_title_ne')) {
                titleNe = el('preview_title_ne').value.trim();
            }
            if (!descriptionNe && generatedVisible && el('preview_description_ne')) {
                descriptionNe = el('preview_description_ne').value.trim();
            }

            var submitTitle = el('submit_title');
            var submitDescription = el('submit_description');
            var submitTitleNe = el('submit_title_ne');
            var submitDescriptionNe = el('submit_description_ne');

            if (submitTitle) submitTitle.value = title;
            if (submitDescription) submitDescription.value = description;
            if (submitTitleNe) submitTitleNe.value = titleNe;
            if (submitDescriptionNe) submitDescriptionNe.value = descriptionNe;

            return true;
        });
    }

    if (el('alertForm')) {
        window.switchLangTab('en');
        initAlertMap();
    }
})();
</script>
@endsection
