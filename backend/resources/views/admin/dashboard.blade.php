@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
@php
    $finance = $data['permissions']['finance'];
    $queueTotal = collect($data['queues'])->sum('count');
    $activityTotals = [
        'users' => array_sum($data['charts']['activity']['users']),
        'reports' => array_sum($data['charts']['activity']['reports']),
        'alerts' => array_sum($data['charts']['activity']['alerts']),
    ];
@endphp

<div id="adminDashboard" class="space-y-5 p-4 sm:p-5 lg:p-6" data-range-key="{{ $data['range']['key'] }}">
    <script type="application/json" id="dashboardBootstrap">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-primary-200">Admin command center</p>
                <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Namaste, {{ Auth::user()->name }}.</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-primary-100">
                    <span class="inline-flex items-center gap-1.5"><i class="fas fa-calendar-day text-xs"></i> <span id="dashRangeLabel">{{ $data['range']['label'] }}</span></span>
                    <span class="text-primary-300">|</span>
                    <span id="dashRangeHuman">{{ $data['range']['human'] }}</span>
                    <span class="text-primary-300">|</span>
                    <span>Nepal time</span>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <div id="rangePills" class="flex flex-wrap items-center gap-1 rounded-2xl bg-white/10 p-1">
                        @foreach([['key' => 'today', 'label' => 'Today'], ['key' => '7d', 'label' => '7D'], ['key' => '30d', 'label' => '30D'], ['key' => '90d', 'label' => '90D'], ['key' => '12m', 'label' => '12M']] as $rangeOption)
                            <button type="button" data-range="{{ $rangeOption['key'] }}" class="range-pill rounded-xl px-3 py-2 text-xs font-semibold transition {{ $data['range']['key'] === $rangeOption['key'] ? 'bg-white text-primary-900 shadow' : 'text-primary-100 hover:bg-white/10' }}">{{ $rangeOption['label'] }}</button>
                        @endforeach
                        <button type="button" data-range="custom" class="range-pill rounded-xl px-3 py-2 text-xs font-semibold transition {{ $data['range']['key'] === 'custom' ? 'bg-white text-primary-900 shadow' : 'text-primary-100 hover:bg-white/10' }}"><i class="fas fa-calendar-alt mr-1"></i>Custom</button>
                    </div>
                    <button type="button" id="dashRefresh" title="Refresh now" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-white/10 text-white transition hover:bg-white/20">
                        <i id="dashRefreshIcon" class="fas fa-rotate-right"></i>
                    </button>
                    <a id="dashExport" href="{{ route('admin.dashboard.export', ['range' => $data['range']['key'], 'from' => $data['range']['start'], 'to' => $data['range']['end']]) }}" title="Export this range as CSV" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                        <i class="fas fa-file-csv"></i> Export
                    </a>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5">
                        <span id="liveDot" class="h-2 w-2 rounded-full bg-emerald-300"></span>
                        <span id="liveLabel">Live updates</span>
                    </span>
                    <label class="inline-flex items-center gap-2">
                        <span class="text-primary-200">Refresh</span>
                        <select id="refreshInterval" class="rounded-lg border-0 bg-white/10 px-2 py-1 text-xs font-semibold text-white outline-none">
                            <option value="10">10s</option>
                            <option value="30" selected>30s</option>
                            <option value="60">60s</option>
                            <option value="0">Off</option>
                        </select>
                    </label>
                    <span>Updated <span id="lastUpdated">just now</span></span>
                    <span id="dashStatus" class="hidden items-center gap-1 rounded-full bg-white/10 px-2 py-1 text-[11px]"></span>
                </div>
            </div>
        </div>

        <form id="customRangeForm" class="relative mt-4 hidden flex-wrap items-end gap-3 rounded-2xl border border-white/15 bg-white/10 p-3">
            <label class="text-xs font-medium text-primary-100">From
                <input type="date" id="customFrom" value="{{ $data['range']['start'] }}" class="mt-1 block rounded-xl border-0 bg-white/90 px-3 py-2 text-sm text-slate-900 outline-none">
            </label>
            <label class="text-xs font-medium text-primary-100">To
                <input type="date" id="customTo" value="{{ $data['range']['end'] }}" class="mt-1 block rounded-xl border-0 bg-white/90 px-3 py-2 text-sm text-slate-900 outline-none">
            </label>
            <button type="submit" class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary-900 transition hover:bg-primary-50">Apply range</button>
            <span id="customRangeError" class="hidden text-xs font-medium text-amber-200"></span>
        </form>
    </section>

    {{-- Attention strip --}}
    <div id="attentionHost">
        @if(count($data['attention_items']) > 0)
            <section class="rounded-3xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-amber-500 text-white"><i class="fas fa-bolt text-sm"></i></span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Needs your attention</p>
                            <p class="text-xs text-amber-700">Actionable queues across the platform</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700">{{ count($data['attention_items']) }} items</span>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($data['attention_items'] as $item)
                        <a href="{{ $item['route'] }}" class="flex items-center gap-2.5 rounded-2xl border border-amber-100 bg-white/80 px-3 py-2.5 transition hover:border-amber-300 hover:bg-white">
                            <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-{{ $item['color'] }}-100 text-{{ $item['color'] }}-600"><i class="fas fa-{{ $item['icon'] }} text-xs"></i></span>
                            <span class="min-w-0 flex-1 truncate text-xs font-medium text-slate-700">{{ $item['label'] }}</span>
                            <span class="rounded-full bg-{{ $item['color'] }}-100 px-2 py-0.5 text-xs font-bold text-{{ $item['color'] }}-700">{{ number_format($item['count']) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            <section class="flex items-center gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 p-4">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-500 text-white"><i class="fas fa-check text-sm"></i></span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">All caught up</p>
                    <p class="text-xs text-emerald-700">No pending queues need your attention right now.</p>
                </div>
            </section>
        @endif
    </div>

    {{-- KPI cards --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($data['kpis'] as $kpi)
            @php
                $delta = $kpi['delta'];
                if ($delta === null) {
                    $deltaClasses = '';
                } elseif ((int) $delta === 0) {
                    $deltaClasses = 'bg-slate-100 text-slate-500';
                } else {
                    $good = $kpi['delta_invert'] ? $delta < 0 : $delta > 0;
                    $deltaClasses = $good ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700';
                }
                $deltaIcon = $delta > 0 ? 'arrow-up' : ($delta < 0 ? 'arrow-down' : 'minus');
                if ($delta === null) {
                    $deltaText = '';
                } elseif ($delta > 999) {
                    $deltaText = '&gt;999%';
                } elseif ($delta < -999) {
                    $deltaText = '&lt;-999%';
                } else {
                    $deltaText = ($delta > 0 ? '+' : '').$delta.'%';
                }
            @endphp
            <a href="{{ $kpi['route'] }}" class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-{{ $kpi['color'] }}-50 text-{{ $kpi['color'] }}-600"><i class="fas fa-{{ $kpi['icon'] }}"></i></span>
                    @if($delta !== null)
                        <span data-kpi-delta="{{ $kpi['key'] }}" class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ $deltaClasses }}"><i class="fas fa-{{ $deltaIcon }} mr-1"></i>{!! $deltaText !!}</span>
                    @endif
                </div>
                <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $kpi['label'] }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight text-slate-900" data-kpi-value="{{ $kpi['key'] }}">{{ $kpi['display'] }}</p>
                <div class="mt-2 flex items-end justify-between gap-2">
                    <p class="text-[11px] leading-snug text-slate-500" data-kpi-sub="{{ $kpi['key'] }}">{{ $kpi['sub'] }}</p>
                    @if($kpi['spark_points'] !== '')
                        <svg class="h-8 w-24 flex-shrink-0 text-{{ $kpi['color'] }}-500" viewBox="0 0 104 30" preserveAspectRatio="none" aria-hidden="true">
                            <polyline data-spark="{{ $kpi['key'] }}" points="{{ $kpi['spark_points'] }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        </svg>
                    @endif
                </div>
            </a>
        @endforeach
    </section>

    {{-- Activity trend + system health --}}
    <section class="grid gap-5 xl:grid-cols-[1.7fr_1fr]">
        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500">Activity trend</p>
                    <h2 class="text-lg font-semibold text-slate-900">Users, reports &amp; alerts</h2>
                </div>
                <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700" id="activityWindowLabel">{{ $data['range']['label'] }}</span>
            </div>
            <div class="relative mt-4 h-72 overflow-hidden">
                <canvas id="activityChart"></canvas>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-2xl bg-slate-50 p-2.5">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Users</p>
                    <p class="mt-1 text-lg font-semibold text-primary-700" data-series-total="users">{{ number_format($activityTotals['users']) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-2.5">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Reports</p>
                    <p class="mt-1 text-lg font-semibold text-sky-700" data-series-total="reports">{{ number_format($activityTotals['reports']) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-2.5">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Alerts</p>
                    <p class="mt-1 text-lg font-semibold text-rose-700" data-series-total="alerts">{{ number_format($activityTotals['alerts']) }}</p>
                </div>
            </div>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500">System health</p>
                    <h2 class="text-lg font-semibold text-slate-900">Operational status</h2>
                </div>
                <span id="healthStatusBadge" class="rounded-full px-2.5 py-1 text-xs font-bold" style="background: {{ $data['system']['health_color'] }}1a; color: {{ $data['system']['health_color'] }}">{{ $data['system']['health_status'] }}</span>
            </div>
            <div class="mt-5 flex items-center gap-4">
                <div id="healthRing" class="relative h-24 w-24 flex-shrink-0 rounded-full" style="background: conic-gradient({{ $data['system']['health_color'] }} {{ $data['system']['health_score'] * 3.6 }}deg, #e2e8f0 0deg)">
                    <div class="absolute inset-[7px] grid place-items-center rounded-full bg-white">
                        <div class="text-center">
                            <p class="text-xl font-bold text-slate-900" id="healthScore">{{ $data['system']['health_score'] }}%</p>
                            <p class="text-[9px] uppercase tracking-wider text-slate-400">score</p>
                        </div>
                    </div>
                </div>
                <div class="min-w-0 flex-1 space-y-2 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Database</span>
                        <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-600"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Connected</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Cache</span>
                        <span id="cacheState" class="inline-flex items-center gap-1.5 font-semibold {{ $data['system']['cache_ok'] ? 'text-emerald-600' : 'text-rose-600' }}"><span class="h-1.5 w-1.5 rounded-full {{ $data['system']['cache_ok'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>{{ $data['system']['cache_ok'] ? 'Healthy' : 'Degraded' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Queue backlog</span>
                        <span id="queueBacklog" class="font-semibold text-slate-900">{{ number_format($data['system']['queue_backlog']) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Failed jobs</span>
                        <span id="failedJobs" class="font-semibold {{ $data['system']['failed_jobs'] > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($data['system']['failed_jobs']) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Scheduler</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span id="automationSchedulerDot" class="h-1.5 w-1.5 rounded-full bg-{{ $data['automation']['scheduler_tone'] }}-500"></span>
                            <span id="automationSchedulerText" class="font-semibold text-{{ $data['automation']['scheduler_tone'] }}-600">{{ $data['automation']['scheduler_label'] }}</span>
                            <span id="automationSchedulerAge" class="text-[10px] text-slate-400">{{ $data['automation']['scheduler_human'] }}</span>
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Queue worker</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span id="automationWorkerDot" class="h-1.5 w-1.5 rounded-full bg-{{ $data['automation']['worker_tone'] }}-500"></span>
                            <span id="automationWorkerText" class="font-semibold text-{{ $data['automation']['worker_tone'] }}-600">{{ $data['automation']['worker_label'] }}</span>
                            <span id="automationWorkerAge" class="text-[10px] text-slate-400">{{ $data['automation']['worker_human'] }}</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 text-[11px]">
                <div class="rounded-2xl bg-slate-50 p-2.5">
                    <p class="text-slate-400">Last activity</p>
                    <p id="lastActivity" class="mt-1 font-semibold text-slate-800">{{ $data['system']['last_activity_human'] }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-2.5">
                    <p class="text-slate-400">Last report</p>
                    <p id="lastReport" class="mt-1 font-semibold text-slate-800">{{ $data['system']['last_report_human'] }}</p>
                </div>
            </div>
            @if($data['automation']['can_manage'])
                <a href="{{ $data['automation']['url'] }}" class="mt-3 flex items-center justify-between gap-3 rounded-2xl bg-primary-50 px-3 py-2.5 transition hover:bg-primary-100">
                    <div class="min-w-0">
                        <p class="text-[9px] font-semibold uppercase tracking-wider text-primary-500">Automation &amp; queues</p>
                        <p class="truncate text-[11px] font-semibold text-primary-800">
                            <span id="automationNextTask">{{ $data['automation']['next_task'] ?: 'No enabled task' }}</span>
                            <span id="automationNextRun" class="font-normal text-primary-600">{{ $data['automation']['next_run_human'] }}</span>
                        </p>
                    </div>
                    <span class="flex-shrink-0 text-[11px] font-semibold text-primary-700">Manage <i class="fas fa-arrow-right ml-0.5"></i></span>
                </a>
            @endif
        </div>
    </section>

    {{-- Revenue + report status --}}
    @if($finance)
        <section class="grid gap-5 xl:grid-cols-[1.7fr_1fr]">
            <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Revenue stream</p>
                        <h2 class="text-lg font-semibold text-slate-900">Income for the selected range</h2>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-semibold text-slate-900" id="revenueTotal">{{ $data['revenue']['display'] }}</p>
                        <span id="revenueDelta" class="mt-1 inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ $data['revenue']['delta'] > 0 ? 'bg-emerald-50 text-emerald-700' : ($data['revenue']['delta'] < 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500') }}">{{ $data['revenue']['delta'] > 0 ? '+' : '' }}{{ $data['revenue']['delta'] }}% vs previous</span>
                    </div>
                </div>
                <div class="relative mt-4 h-64 overflow-hidden">
                    <canvas id="revenueChart"></canvas>
                </div>
                <div id="revenueBreakdown" class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach($data['revenue']['breakdown'] as $stream)
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-{{ $stream['color'] }}-500"></span>
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{{ $stream['label'] }}</p>
                            </div>
                            <p class="mt-1.5 text-base font-semibold text-slate-900" data-revenue-key="{{ $stream['key'] }}">{{ $stream['display'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div>
                    <p class="text-sm font-medium text-slate-500">Moderation mix</p>
                    <h2 class="text-lg font-semibold text-slate-900">Reports by status</h2>
                </div>
                <div class="relative mt-3 h-56 overflow-hidden">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-amber-50 p-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-amber-600">Pending</p>
                        <p class="mt-1 text-lg font-semibold text-amber-700" data-status-count="pending">{{ number_format($data['charts']['report_status']['values'][0]) }}</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 p-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-emerald-600">Approved</p>
                        <p class="mt-1 text-lg font-semibold text-emerald-700" data-status-count="approved">{{ number_format($data['charts']['report_status']['values'][1]) }}</p>
                    </div>
                    <div class="rounded-2xl bg-rose-50 p-2.5">
                        <p class="text-[10px] uppercase tracking-wider text-rose-600">Rejected</p>
                        <p class="mt-1 text-lg font-semibold text-rose-700" data-status-count="rejected">{{ number_format($data['charts']['report_status']['values'][2]) }}</p>
                    </div>
                </div>
                <p class="mt-4 rounded-2xl bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-500">
                    Pending reports include every submission in the selected range. The review queue card above also counts moderation-queue items outside reports.
                </p>
            </div>
        </section>
    @endif

    {{-- Queues + breakdowns --}}
    <section class="grid gap-5 lg:grid-cols-3">
        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500">Operations</p>
                    <h2 class="text-lg font-semibold text-slate-900">Queue health</h2>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600"><span id="queueTotal">{{ number_format($queueTotal) }}</span> open</span>
            </div>
            <div id="queueList" class="mt-4 max-h-80 space-y-3 overflow-y-auto pr-1 scrollbar-thin">
                @foreach($data['queues'] as $queue)
                    <a href="{{ $queue['route'] }}" data-queue-row="{{ $queue['key'] }}" class="block rounded-2xl border border-slate-100 bg-slate-50/70 p-3 transition hover:border-primary-200 hover:bg-white">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-{{ $queue['color'] }}-100 text-{{ $queue['color'] }}-600"><i class="fas fa-{{ $queue['icon'] }} text-xs"></i></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-xs font-medium text-slate-700">{{ $queue['label'] }}</p>
                                    <span data-queue-count="{{ $queue['key'] }}" class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $queue['count'] > 0 ? 'bg-'.$queue['color'].'-100 text-'.$queue['color'].'-700' : 'bg-emerald-50 text-emerald-600' }}">{{ number_format($queue['count']) }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white">
                                    <div data-queue-bar="{{ $queue['key'] }}" class="h-full rounded-full bg-{{ $queue['color'] }}-500" style="width: {{ $queue['percent'] }}%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500">Reports</p>
                    <h2 class="text-lg font-semibold text-slate-900">Top categories</h2>
                </div>
                <a href="{{ route('admin.reports') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">View all</a>
            </div>
            <div id="categoryBreakdown" class="mt-4 space-y-3">
                @forelse($data['breakdowns']['categories'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="truncate text-slate-600">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($row['value']) }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-primary-500" style="width: {{ max(2, $row['percent']) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-slate-400">No reports in this period.</p>
                @endforelse
            </div>
        </div>

        <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500">Reports</p>
                    <h2 class="text-lg font-semibold text-slate-900">Top districts</h2>
                </div>
                <a href="{{ route('admin.reports') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">View all</a>
            </div>
            <div id="districtBreakdown" class="mt-4 space-y-3">
                @forelse($data['breakdowns']['districts'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <span class="truncate text-slate-600">{{ $row['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($row['value']) }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-sky-500" style="width: {{ max(2, $row['percent']) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-slate-400">No district data in this period.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Recent reports + activity --}}
    <section class="grid gap-5 xl:grid-cols-[1.7fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">Moderation feed</p>
                    <h2 class="text-lg font-semibold text-slate-900">Recent reports</h2>
                </div>
                <a href="{{ route('admin.reports') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">View all</a>
            </div>
            <div id="recentReportsList" class="mt-4 space-y-2.5">
                @forelse($data['recent_reports'] as $report)
                    @php
                        $statusClasses = ['approved' => 'bg-emerald-50 text-emerald-700', 'pending' => 'bg-amber-50 text-amber-700', 'rejected' => 'bg-rose-50 text-rose-700'][$report['status']] ?? 'bg-slate-100 text-slate-500';
                        $priorityClasses = ['critical' => 'bg-rose-500', 'high' => 'bg-orange-500', 'medium' => 'bg-sky-500', 'low' => 'bg-slate-300'][$report['priority']] ?? 'bg-slate-300';
                    @endphp
                    <a href="{{ $report['url'] }}" class="block rounded-2xl border border-slate-100 bg-slate-50/70 p-3.5 transition hover:border-primary-200 hover:bg-white">
                        <div class="flex items-start gap-3">
                            <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full {{ $priorityClasses }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $report['title'] }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $report['reporter'] }} | {{ $report['category'] }}</p>
                            </div>
                            <div class="flex flex-shrink-0 flex-col items-end gap-1">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $statusClasses }}">{{ $report['status'] }}</span>
                                <span class="text-[10px] text-slate-400">{{ $report['time'] }}</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <p class="py-10 text-center text-sm text-slate-400">No reports yet.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-5">
            @if($data['permissions']['audit'])
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Audit log</p>
                            <h2 class="text-lg font-semibold text-slate-900">Live activity</h2>
                        </div>
                        <a href="{{ route('admin.audit-logs') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">View all</a>
                    </div>
                    <div id="recentActivityList" class="mt-4 max-h-80 space-y-3 overflow-y-auto pr-1 scrollbar-thin">
                        @forelse($data['recent_activity'] as $activity)
                            @php
                                $activityClasses = ['rose' => 'bg-rose-100 text-rose-600', 'emerald' => 'bg-emerald-100 text-emerald-600', 'amber' => 'bg-amber-100 text-amber-600', 'slate' => 'bg-slate-200 text-slate-600'][$activity['color']] ?? 'bg-slate-200 text-slate-600';
                            @endphp
                            <div class="flex items-start gap-2.5">
                                <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl text-[11px] font-bold {{ $activityClasses }}">{{ $activity['initial'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-xs font-semibold text-slate-900">{{ $activity['user'] }}</p>
                                        <span class="flex-shrink-0 text-[10px] text-slate-400">{{ $activity['time'] }}</span>
                                    </div>
                                    <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">{{ $activity['description'] }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-8 text-center text-xs text-slate-400">No recent activity.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if($data['permissions']['partners'])
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Business portal</p>
                            <h2 class="text-lg font-semibold text-slate-900">Partner verification</h2>
                        </div>
                        <a href="{{ route('admin.travel-partners') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">Review</a>
                    </div>
                    <div id="recentPartnersList" class="mt-4 space-y-2.5">
                        @forelse($data['recent_partners'] as $partner)
                            <a href="{{ $partner['url'] }}" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-3 py-2.5 transition hover:bg-primary-50">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-900">{{ $partner['name'] }}</p>
                                    <p class="truncate text-[10px] text-slate-500">{{ $partner['type'] }} | {{ $partner['time'] }}</p>
                                </div>
                                <span class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700">Pending</span>
                            </a>
                        @empty
                            <p class="py-8 text-center text-xs text-slate-400">No pending partner requests.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- Contributors + new users + snapshot --}}
    <section class="grid gap-5 lg:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm font-medium text-slate-500">Community</p>
                <h2 class="text-lg font-semibold text-slate-900">Top contributors</h2>
            </div>
            <div id="contributorsList" class="mt-4 space-y-3">
                @forelse($data['top_contributors'] as $index => $contributor)
                    @php
                        $rankClasses = $index === 0 ? 'bg-amber-100 text-amber-700' : ($index === 1 ? 'bg-slate-200 text-slate-600' : ($index === 2 ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-500'));
                    @endphp
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-xl text-[11px] font-bold {{ $rankClasses }}">#{{ $index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-slate-900">{{ $contributor['name'] }}</p>
                            <p class="text-[10px] text-slate-400">{{ number_format($contributor['xp']) }} XP</p>
                        </div>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">{{ number_format($contributor['reports']) }} approved</span>
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-slate-400">No approvals in this period.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm font-medium text-slate-500">People</p>
                <h2 class="text-lg font-semibold text-slate-900">Newest travelers</h2>
            </div>
            <div id="recentUsersList" class="mt-4 space-y-3">
                @forelse($data['recent_users'] as $member)
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-primary-600 text-[11px] font-bold text-white">{{ $member['initial'] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-slate-900">{{ $member['name'] }}</p>
                            <p class="truncate text-[10px] text-slate-400">{{ $member['email'] }}</p>
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $member['role'] }}</span>
                            <p class="mt-0.5 text-[10px] text-slate-400">{{ $member['time'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-slate-400">No recent users.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-500">Platform</p>
                    <h2 class="text-lg font-semibold text-slate-900">Snapshot</h2>
                </div>
                @if($data['snapshot']['oldest_pending_human'])
                    <span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700">Oldest pending {{ $data['snapshot']['oldest_pending_human'] }}</span>
                @endif
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <div class="rounded-2xl bg-slate-50 p-3">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Active subs</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900" data-snapshot="active_subscriptions">{{ number_format($data['snapshot']['active_subscriptions']) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Reviews</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900" data-snapshot="reviews">{{ number_format($data['snapshot']['reviews']) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Active places</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900" data-snapshot="active_places">{{ number_format($data['snapshot']['active_places']) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Verified partners</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900" data-snapshot="verified_partners">{{ number_format($data['snapshot']['verified_partners']) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">XP awarded</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900" data-snapshot="xp_awarded">{{ number_format($data['snapshot']['xp_awarded']) }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-3">
                    <p class="text-[10px] uppercase tracking-wider text-slate-400">Total XP</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900" data-snapshot="total_xp">{{ number_format($data['snapshot']['total_xp']) }}</p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-[10px] text-slate-500">
                <span class="rounded-full bg-slate-100 px-2 py-1"><span data-snapshot="osm_places">{{ number_format($data['snapshot']['osm_places']) }}</span> OSM places</span>
                <span class="rounded-full bg-slate-100 px-2 py-1"><span data-snapshot="new_alerts">{{ number_format($data['snapshot']['new_alerts']) }}</span> new alerts</span>
                <span class="rounded-full bg-slate-100 px-2 py-1"><span data-snapshot="high_priority_reports">{{ number_format($data['snapshot']['high_priority_reports']) }}</span> high priority</span>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    'use strict';

    var root = document.getElementById('adminDashboard');
    var bootEl = document.getElementById('dashboardBootstrap');
    if (!root || !bootEl) return;

    if (window.__adminDashboardCleanup) {
        try { window.__adminDashboardCleanup(); } catch (e) {}
    }

    var boot;
    try { boot = JSON.parse(bootEl.textContent); } catch (e) { return; }

    var endpoints = {
        data: @json(route('admin.dashboard.data')),
        export: @json(route('admin.dashboard.export'))
    };

    var state = {
        data: boot,
        range: boot.range.key,
        from: boot.range.start,
        to: boot.range.end,
        interval: parseInt(localStorage.getItem('admin_dash_refresh') || '30', 10),
        timer: null,
        uiTimer: null,
        charts: {},
        observer: null,
        loading: false,
        updatedAt: Date.now()
    };

    if ([10, 30, 60, 0].indexOf(state.interval) === -1) {
        state.interval = 30;
    }

    function esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function fmt(value) {
        return Number(value || 0).toLocaleString('en-US');
    }

    function isTyping() {
        var active = document.activeElement;
        return !!(active && (active.tagName === 'INPUT' || active.tagName === 'SELECT' || active.tagName === 'TEXTAREA'));
    }

    function setStatus(text, iconClass) {
        var el = document.getElementById('dashStatus');
        if (!el) return;
        if (!text) {
            el.classList.add('hidden');
            el.classList.remove('inline-flex');
            el.textContent = '';
            return;
        }
        el.classList.remove('hidden');
        el.classList.add('inline-flex');
        el.innerHTML = '<i class="fas ' + (iconClass || 'fa-circle-info') + '"></i> ' + esc(text);
    }

    // -----------------------------------------------------------------
    // KPI cards
    // -----------------------------------------------------------------
    function updateKpis(kpis) {
        kpis.forEach(function (kpi) {
            var value = document.querySelector('[data-kpi-value="' + kpi.key + '"]');
            var sub = document.querySelector('[data-kpi-sub="' + kpi.key + '"]');
            var spark = document.querySelector('[data-spark="' + kpi.key + '"]');
            var delta = document.querySelector('[data-kpi-delta="' + kpi.key + '"]');

            if (value) value.textContent = kpi.display;
            if (sub) sub.textContent = kpi.sub;
            if (spark && kpi.spark_points) spark.setAttribute('points', kpi.spark_points);

            if (delta) {
                if (kpi.delta === null) {
                    delta.remove();
                    return;
                }
                var d = Number(kpi.delta);
                var good = kpi.delta_invert ? d < 0 : d > 0;
                delta.className = 'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold ' +
                    (d === 0 ? 'bg-slate-100 text-slate-500' : (good ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'));
                var label = d > 999 ? '&gt;999%' : (d < -999 ? '&lt;-999%' : ((d > 0 ? '+' : '') + d + '%'));
                delta.innerHTML = '<i class="fas fa-' + (d > 0 ? 'arrow-up' : (d < 0 ? 'arrow-down' : 'minus')) + ' mr-1"></i>' + label;
            }
        });
    }

    // -----------------------------------------------------------------
    // Attention + queue health
    // -----------------------------------------------------------------
    function renderAttention(items) {
        var host = document.getElementById('attentionHost');
        if (!host) return;

        if (!items || !items.length) {
            host.innerHTML = '<section class="flex items-center gap-3 rounded-3xl border border-emerald-200 bg-emerald-50 p-4">' +
                '<span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-emerald-500 text-white"><i class="fas fa-check text-sm"></i></span>' +
                '<div><p class="text-sm font-semibold text-slate-900">All caught up</p>' +
                '<p class="text-xs text-emerald-700">No pending queues need your attention right now.</p></div></section>';
            return;
        }

        host.innerHTML = '<section class="rounded-3xl border border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50 p-4">' +
            '<div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2.5">' +
            '<span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-amber-500 text-white"><i class="fas fa-bolt text-sm"></i></span>' +
            '<div><p class="text-sm font-semibold text-slate-900">Needs your attention</p>' +
            '<p class="text-xs text-amber-700">Actionable queues across the platform</p></div></div>' +
            '<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700">' + items.length + ' items</span></div>' +
            '<div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">' +
            items.map(function (item) {
                return '<a href="' + esc(item.route) + '" class="flex items-center gap-2.5 rounded-2xl border border-amber-100 bg-white/80 px-3 py-2.5 transition hover:border-amber-300 hover:bg-white">' +
                    '<span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-' + item.color + '-100 text-' + item.color + '-600"><i class="fas fa-' + item.icon + ' text-xs"></i></span>' +
                    '<span class="min-w-0 flex-1 truncate text-xs font-medium text-slate-700">' + esc(item.label) + '</span>' +
                    '<span class="rounded-full bg-' + item.color + '-100 px-2 py-0.5 text-xs font-bold text-' + item.color + '-700">' + fmt(item.count) + '</span></a>';
            }).join('') + '</div></section>';
    }

    function renderQueues(queues) {
        var host = document.getElementById('queueList');
        if (!host) return;

        var total = queues.reduce(function (sum, queue) { return sum + Number(queue.count || 0); }, 0);
        var totalEl = document.getElementById('queueTotal');
        if (totalEl) totalEl.textContent = fmt(total);

        host.innerHTML = queues.map(function (queue) {
            var badge = queue.count > 0
                ? 'bg-' + queue.color + '-100 text-' + queue.color + '-700'
                : 'bg-emerald-50 text-emerald-600';
            return '<a href="' + esc(queue.route) + '" data-queue-row="' + esc(queue.key) + '" class="block rounded-2xl border border-slate-100 bg-slate-50/70 p-3 transition hover:border-primary-200 hover:bg-white">' +
                '<div class="flex items-center gap-2.5">' +
                '<span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-' + queue.color + '-100 text-' + queue.color + '-600"><i class="fas fa-' + queue.icon + ' text-xs"></i></span>' +
                '<div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2">' +
                '<p class="truncate text-xs font-medium text-slate-700">' + esc(queue.label) + '</p>' +
                '<span data-queue-count="' + esc(queue.key) + '" class="rounded-full px-2 py-0.5 text-[11px] font-bold ' + badge + '">' + fmt(queue.count) + '</span></div>' +
                '<div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white"><div data-queue-bar="' + esc(queue.key) + '" class="h-full rounded-full bg-' + queue.color + '-500" style="width:' + queue.percent + '%"></div></div>' +
                '</div></div></a>';
        }).join('');
    }

    // -----------------------------------------------------------------
    // Breakdown lists
    // -----------------------------------------------------------------
    function renderBreakdown(hostId, rows, barClass) {
        var host = document.getElementById(hostId);
        if (!host) return;

        if (!rows || !rows.length) {
            host.innerHTML = '<p class="py-8 text-center text-xs text-slate-400">No data for this period.</p>';
            return;
        }

        host.innerHTML = rows.map(function (row) {
            return '<div><div class="flex items-center justify-between gap-2 text-xs">' +
                '<span class="truncate text-slate-600">' + esc(row.label) + '</span>' +
                '<span class="font-semibold text-slate-900">' + fmt(row.value) + '</span></div>' +
                '<div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full ' + barClass + '" style="width:' + Math.max(2, row.percent) + '%"></div></div></div>';
        }).join('');
    }

    // -----------------------------------------------------------------
    // Recent lists
    // -----------------------------------------------------------------
    function statusBadge(status) {
        var classes = {
            approved: 'bg-emerald-50 text-emerald-700',
            pending: 'bg-amber-50 text-amber-700',
            rejected: 'bg-rose-50 text-rose-700'
        }[status] || 'bg-slate-100 text-slate-500';

        return '<span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ' + classes + '">' + esc(status) + '</span>';
    }

    function priorityDot(priority) {
        return {
            critical: 'bg-rose-500',
            high: 'bg-orange-500',
            medium: 'bg-sky-500',
            low: 'bg-slate-300'
        }[priority] || 'bg-slate-300';
    }

    function renderRecentReports(reports) {
        var host = document.getElementById('recentReportsList');
        if (!host) return;

        if (!reports || !reports.length) {
            host.innerHTML = '<p class="py-10 text-center text-sm text-slate-400">No reports yet.</p>';
            return;
        }

        host.innerHTML = reports.map(function (report) {
            return '<a href="' + esc(report.url) + '" class="block rounded-2xl border border-slate-100 bg-slate-50/70 p-3.5 transition hover:border-primary-200 hover:bg-white">' +
                '<div class="flex items-start gap-3"><span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full ' + priorityDot(report.priority) + '"></span>' +
                '<div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">' + esc(report.title) + '</p>' +
                '<p class="mt-0.5 truncate text-xs text-slate-500">' + esc(report.reporter) + ' | ' + esc(report.category) + '</p></div>' +
                '<div class="flex flex-shrink-0 flex-col items-end gap-1">' + statusBadge(report.status) +
                '<span class="text-[10px] text-slate-400">' + esc(report.time) + '</span></div></div></a>';
        }).join('');
    }

    function renderRecentActivity(items) {
        var host = document.getElementById('recentActivityList');
        if (!host) return;

        if (!items || !items.length) {
            host.innerHTML = '<p class="py-8 text-center text-xs text-slate-400">No recent activity.</p>';
            return;
        }

        var colors = {
            rose: 'bg-rose-100 text-rose-600',
            emerald: 'bg-emerald-100 text-emerald-600',
            amber: 'bg-amber-100 text-amber-600',
            slate: 'bg-slate-200 text-slate-600'
        };

        host.innerHTML = items.map(function (activity) {
            return '<div class="flex items-start gap-2.5">' +
                '<span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl text-[11px] font-bold ' + (colors[activity.color] || colors.slate) + '">' + esc(activity.initial) + '</span>' +
                '<div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2">' +
                '<p class="truncate text-xs font-semibold text-slate-900">' + esc(activity.user) + '</p>' +
                '<span class="flex-shrink-0 text-[10px] text-slate-400">' + esc(activity.time) + '</span></div>' +
                '<p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">' + esc(activity.description) + '</p></div></div>';
        }).join('');
    }

    function renderRecentUsers(users) {
        var host = document.getElementById('recentUsersList');
        if (!host) return;

        if (!users || !users.length) {
            host.innerHTML = '<p class="py-8 text-center text-xs text-slate-400">No recent users.</p>';
            return;
        }

        host.innerHTML = users.map(function (member) {
            return '<div class="flex items-center gap-2.5">' +
                '<span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-primary-600 text-[11px] font-bold text-white">' + esc(member.initial) + '</span>' +
                '<div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-900">' + esc(member.name) + '</p>' +
                '<p class="truncate text-[10px] text-slate-400">' + esc(member.email) + '</p></div>' +
                '<div class="flex-shrink-0 text-right"><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">' + esc(member.role) + '</span>' +
                '<p class="mt-0.5 text-[10px] text-slate-400">' + esc(member.time) + '</p></div></div>';
        }).join('');
    }

    function renderRecentPartners(partners) {
        var host = document.getElementById('recentPartnersList');
        if (!host) return;

        if (!partners || !partners.length) {
            host.innerHTML = '<p class="py-8 text-center text-xs text-slate-400">No pending partner requests.</p>';
            return;
        }

        host.innerHTML = partners.map(function (partner) {
            return '<a href="' + esc(partner.url) + '" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-3 py-2.5 transition hover:bg-primary-50">' +
                '<div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-900">' + esc(partner.name) + '</p>' +
                '<p class="truncate text-[10px] text-slate-500">' + esc(partner.type) + ' | ' + esc(partner.time) + '</p></div>' +
                '<span class="rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-bold text-violet-700">Pending</span></a>';
        }).join('');
    }

    function renderContributors(contributors) {
        var host = document.getElementById('contributorsList');
        if (!host) return;

        if (!contributors || !contributors.length) {
            host.innerHTML = '<p class="py-8 text-center text-xs text-slate-400">No approvals in this period.</p>';
            return;
        }

        var ranks = ['bg-amber-100 text-amber-700', 'bg-slate-200 text-slate-600', 'bg-orange-100 text-orange-700'];

        host.innerHTML = contributors.map(function (contributor, index) {
            return '<div class="flex items-center gap-2.5">' +
                '<span class="inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-xl text-[11px] font-bold ' + (ranks[index] || 'bg-slate-100 text-slate-500') + '">#' + (index + 1) + '</span>' +
                '<div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-slate-900">' + esc(contributor.name) + '</p>' +
                '<p class="text-[10px] text-slate-400">' + fmt(contributor.xp) + ' XP</p></div>' +
                '<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">' + fmt(contributor.reports) + ' approved</span></div>';
        }).join('');
    }

    // -----------------------------------------------------------------
    // Snapshot + system + revenue
    // -----------------------------------------------------------------
    function updateSnapshot(snapshot) {
        if (!snapshot) return;
        Object.keys(snapshot).forEach(function (key) {
            document.querySelectorAll('[data-snapshot="' + key + '"]').forEach(function (el) {
                var value = snapshot[key];
                el.textContent = typeof value === 'number' ? fmt(value) : (value == null ? '' : value);
            });
        });
    }

    function updateSystem(system) {
        if (!system) return;

        var score = document.getElementById('healthScore');
        var ring = document.getElementById('healthRing');
        var badge = document.getElementById('healthStatusBadge');
        if (score) score.textContent = system.health_score + '%';
        if (ring) ring.style.background = 'conic-gradient(' + system.health_color + ' ' + (system.health_score * 3.6) + 'deg, #e2e8f0 0deg)';
        if (badge) {
            badge.textContent = system.health_status;
            badge.style.background = system.health_color + '1a';
            badge.style.color = system.health_color;
        }

        var cache = document.getElementById('cacheState');
        if (cache) {
            cache.innerHTML = '<span class="h-1.5 w-1.5 rounded-full ' + (system.cache_ok ? 'bg-emerald-500' : 'bg-rose-500') + '"></span>' + (system.cache_ok ? 'Healthy' : 'Degraded');
            cache.className = 'inline-flex items-center gap-1.5 font-semibold ' + (system.cache_ok ? 'text-emerald-600' : 'text-rose-600');
        }

        var backlog = document.getElementById('queueBacklog');
        if (backlog) backlog.textContent = fmt(system.queue_backlog);

        var failed = document.getElementById('failedJobs');
        if (failed) {
            failed.textContent = fmt(system.failed_jobs);
            failed.className = 'font-semibold ' + (system.failed_jobs > 0 ? 'text-rose-600' : 'text-emerald-600');
        }

        var activity = document.getElementById('lastActivity');
        if (activity) activity.textContent = system.last_activity_human;

        var report = document.getElementById('lastReport');
        if (report) report.textContent = system.last_report_human;
    }

    function updateAutomation(automation) {
        if (!automation) return;

        var tones = {
            emerald: { dot: 'bg-emerald-500', text: 'text-emerald-600' },
            amber: { dot: 'bg-amber-500', text: 'text-amber-600' },
            rose: { dot: 'bg-rose-500', text: 'text-rose-600' },
            sky: { dot: 'bg-sky-500', text: 'text-sky-600' },
            slate: { dot: 'bg-slate-400', text: 'text-slate-500' }
        };

        function paint(prefix, tone, label, age) {
            var t = tones[tone] || tones.slate;
            var dot = document.getElementById(prefix + 'Dot');
            var text = document.getElementById(prefix + 'Text');
            var ageEl = document.getElementById(prefix + 'Age');
            if (dot) dot.className = 'h-1.5 w-1.5 rounded-full ' + t.dot;
            if (text) {
                text.textContent = label;
                text.className = 'font-semibold ' + t.text;
            }
            if (ageEl) ageEl.textContent = age;
        }

        paint('automationScheduler', automation.scheduler_tone, automation.scheduler_label, automation.scheduler_human);
        paint('automationWorker', automation.worker_tone, automation.worker_label, automation.worker_human);

        var nextTask = document.getElementById('automationNextTask');
        var nextRun = document.getElementById('automationNextRun');
        if (nextTask) nextTask.textContent = automation.next_task || 'No enabled task';
        if (nextRun) nextRun.textContent = automation.next_run_human;
    }

    function deltaChip(delta, invert) {
        var value = Number(delta || 0);
        if (value === 0) return { classes: 'bg-slate-100 text-slate-500', html: '<i class="fas fa-minus mr-1"></i>0% vs previous' };
        var good = invert ? value < 0 : value > 0;
        var label = value > 999 ? '&gt;999%' : (value < -999 ? '&lt;-999%' : ((value > 0 ? '+' : '') + value + '%'));
        return {
            classes: good ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700',
            html: '<i class="fas fa-' + (value > 0 ? 'arrow-up' : 'arrow-down') + ' mr-1"></i>' + label + ' vs previous'
        };
    }

    function updateRevenue(revenue) {
        if (!revenue) return;

        var total = document.getElementById('revenueTotal');
        if (total) total.textContent = revenue.display;

        var delta = document.getElementById('revenueDelta');
        if (delta) {
            var chip = deltaChip(revenue.delta, revenue.delta_invert);
            delta.className = 'mt-1 inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold ' + chip.classes;
            delta.innerHTML = chip.html;
        }

        (revenue.breakdown || []).forEach(function (stream) {
            var el = document.querySelector('[data-revenue-key="' + stream.key + '"]');
            if (el) el.textContent = stream.display;
        });
    }

    function updateRangeMeta(range) {
        var label = document.getElementById('dashRangeLabel');
        var human = document.getElementById('dashRangeHuman');
        var windowLabel = document.getElementById('activityWindowLabel');
        if (label) label.textContent = range.label;
        if (human) human.textContent = range.human;
        if (windowLabel) windowLabel.textContent = range.label;

        var from = document.getElementById('customFrom');
        var to = document.getElementById('customTo');
        if (from) from.value = range.start;
        if (to) to.value = range.end;

        setRangeButtons(range.key);
        updateExportLink();
    }

    function updateExportLink() {
        var link = document.getElementById('dashExport');
        if (!link) return;

        var params = new URLSearchParams();
        params.set('range', state.range);
        if (state.range === 'custom') {
            params.set('from', state.from || '');
            params.set('to', state.to || '');
        }
        link.setAttribute('href', endpoints.export + '?' + params.toString());
    }

    function setRangeButtons(range) {
        document.querySelectorAll('.range-pill').forEach(function (button) {
            var active = button.getAttribute('data-range') === range;
            button.classList.toggle('bg-white', active);
            button.classList.toggle('text-primary-900', active);
            button.classList.toggle('shadow', active);
            button.classList.toggle('text-primary-100', !active);
            button.classList.toggle('hover:bg-white/10', !active);
        });

        var form = document.getElementById('customRangeForm');
        if (form) {
            form.classList.toggle('hidden', range !== 'custom');
            form.classList.toggle('flex', range === 'custom');
        }
    }

    // -----------------------------------------------------------------
    // Charts
    // -----------------------------------------------------------------
    function destroyCharts() {
        if (!window.Chart) return;
        Object.keys(state.charts).forEach(function (key) {
            if (state.charts[key]) {
                try { state.charts[key].destroy(); } catch (e) {}
            }
        });
        state.charts = {};
    }

    function centerTextPlugin() {
        return {
            id: 'dashCenterText',
            afterDraw: function (chart) {
                if (chart.config.type !== 'doughnut') return;
                var meta = chart.getDatasetMeta(0);
                if (!meta || !meta.data || !meta.data.length) return;
                var total = (chart.data.datasets[0].data || []).reduce(function (sum, value) {
                    return sum + (Number(value) || 0);
                }, 0);
                if (total === 0) return;

                var x = meta.data[0].x;
                var y = meta.data[0].y;
                var ctx = chart.ctx;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#0f172a';
                ctx.font = '700 22px ui-sans-serif, system-ui, sans-serif';
                ctx.fillText(fmt(total), x, y - 7);
                ctx.fillStyle = '#64748b';
                ctx.font = '600 10px ui-sans-serif, system-ui, sans-serif';
                ctx.fillText('REPORTS', x, y + 13);
                ctx.restore();
            }
        };
    }

    function chartBaseOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 16, font: { size: 11, weight: '600' } }
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#d1d5db',
                    borderColor: '#00897B',
                    borderWidth: 1,
                    padding: 10
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#94a3b8', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.16)', borderDash: [4, 4] }, ticks: { color: '#94a3b8', precision: 0 } }
            }
        };
    }

    function createCharts() {
        if (!window.Chart) return;

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';
        if (!Chart.registry.plugins.get('dashCenterText')) {
            Chart.register(centerTextPlugin());
        }

        var activityCanvas = document.getElementById('activityChart');
        if (activityCanvas) {
            var activity = state.data.charts.activity;
            state.charts.activity = new Chart(activityCanvas, {
                type: 'line',
                data: {
                    labels: activity.labels,
                    datasets: [
                        { label: 'Users', data: activity.users, borderColor: '#00897B', backgroundColor: 'rgba(0, 137, 123, 0.12)', fill: true, tension: 0.35, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2.5 },
                        { label: 'Reports', data: activity.reports, borderColor: '#0284c7', backgroundColor: 'rgba(2, 132, 199, 0.08)', fill: true, tension: 0.35, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2.5 },
                        { label: 'Alerts', data: activity.alerts, borderColor: '#e11d48', backgroundColor: 'rgba(225, 29, 72, 0.05)', fill: true, tension: 0.35, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2.5 }
                    ]
                },
                options: chartBaseOptions()
            });
        }

        var revenueCanvas = document.getElementById('revenueChart');
        if (revenueCanvas) {
            var revenue = state.data.charts.revenue;
            var revenueOptions = chartBaseOptions();
            revenueOptions.scales.x.stacked = true;
            revenueOptions.scales.y.stacked = true;
            state.charts.revenue = new Chart(revenueCanvas, {
                type: 'bar',
                data: {
                    labels: revenue.labels,
                    datasets: [
                        { label: 'Ads', data: revenue.ads, backgroundColor: '#059669', borderRadius: 4, maxBarThickness: 22 },
                        { label: 'Bookings', data: revenue.bookings, backgroundColor: '#0284c7', borderRadius: 4, maxBarThickness: 22 },
                        { label: 'Offers', data: revenue.offers, backgroundColor: '#7c3aed', borderRadius: 4, maxBarThickness: 22 },
                        { label: 'Subscriptions', data: revenue.subscriptions, backgroundColor: '#d97706', borderRadius: 4, maxBarThickness: 22 }
                    ]
                },
                options: revenueOptions
            });
        }

        var statusCanvas = document.getElementById('statusChart');
        if (statusCanvas) {
            var status = state.data.charts.report_status;
            state.charts.status = new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: status.labels,
                    datasets: [{
                        data: status.values,
                        backgroundColor: status.colors,
                        borderColor: '#ffffff',
                        borderWidth: 4,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    layout: { padding: 6 },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 14, font: { size: 11, weight: '600' } }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#ffffff',
                            bodyColor: '#d1d5db',
                            padding: 10
                        }
                    }
                }
            });
        }
    }

    function updateCharts(data) {
        if (!window.Chart) return;

        var activity = state.charts.activity;
        if (activity) {
            activity.data.labels = data.charts.activity.labels;
            activity.data.datasets[0].data = data.charts.activity.users;
            activity.data.datasets[1].data = data.charts.activity.reports;
            activity.data.datasets[2].data = data.charts.activity.alerts;
            activity.update('none');
        }

        var revenue = state.charts.revenue;
        if (revenue) {
            revenue.data.labels = data.charts.revenue.labels;
            revenue.data.datasets[0].data = data.charts.revenue.ads;
            revenue.data.datasets[1].data = data.charts.revenue.bookings;
            revenue.data.datasets[2].data = data.charts.revenue.offers;
            revenue.data.datasets[3].data = data.charts.revenue.subscriptions;
            revenue.update('none');
        }

        var status = state.charts.status;
        if (status) {
            status.data.labels = data.charts.report_status.labels;
            status.data.datasets[0].data = data.charts.report_status.values;
            status.update('none');
        }

        document.querySelectorAll('[data-series-total]').forEach(function (el) {
            var key = el.getAttribute('data-series-total');
            var values = data.charts.activity[key] || [];
            el.textContent = fmt(values.reduce(function (sum, value) { return sum + Number(value || 0); }, 0));
        });

        var statusCounts = { pending: 0, approved: 1, rejected: 2 };
        Object.keys(statusCounts).forEach(function (key) {
            var el = document.querySelector('[data-status-count="' + key + '"]');
            if (el) el.textContent = fmt(data.charts.report_status.values[statusCounts[key]]);
        });
    }

    function whenChartReady(callback) {
        if (window.Chart) { callback(); return; }
        var tries = 0;
        var timer = setInterval(function () {
            if (window.Chart) {
                clearInterval(timer);
                callback();
            } else if (++tries > 100) {
                clearInterval(timer);
            }
        }, 100);
        state.chartWaitTimer = timer;
    }

    // -----------------------------------------------------------------
    // Refresh lifecycle
    // -----------------------------------------------------------------
    function buildUrl() {
        var params = new URLSearchParams();
        params.set('range', state.range);
        if (state.range === 'custom' && state.from && state.to) {
            params.set('from', state.from);
            params.set('to', state.to);
        }

        return endpoints.data + '?' + params.toString();
    }

    function syncBrowserUrl() {
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('range', state.range);
            if (state.range === 'custom' && state.from && state.to) {
                url.searchParams.set('from', state.from);
                url.searchParams.set('to', state.to);
            } else {
                url.searchParams.delete('from');
                url.searchParams.delete('to');
            }
            window.history.replaceState(null, '', url.toString());
        } catch (e) {}
    }

    function apply(data) {
        state.data = data;
        state.range = data.range.key;
        if (state.range !== 'custom') {
            state.from = data.range.start;
            state.to = data.range.end;
        }
        state.updatedAt = Date.now();

        updateKpis(data.kpis);
        renderAttention(data.attention_items);
        renderQueues(data.queues);
        renderBreakdown('categoryBreakdown', data.breakdowns.categories, 'bg-primary-500');
        renderBreakdown('districtBreakdown', data.breakdowns.districts, 'bg-sky-500');
        renderRecentReports(data.recent_reports);
        renderRecentActivity(data.recent_activity);
        renderRecentUsers(data.recent_users);
        renderRecentPartners(data.recent_partners);
        renderContributors(data.top_contributors);
        updateSnapshot(data.snapshot);
        updateSystem(data.system);
        updateAutomation(data.automation);
        updateRevenue(data.revenue);
        updateRangeMeta(data.range);
        updateCharts(data);
        syncBrowserUrl();
    }

    function refresh(force) {
        if (state.loading) return;
        if (!document.getElementById('adminDashboard')) {
            cleanup();
            return;
        }
        if (!force && isTyping()) return;

        state.loading = true;
        var icon = document.getElementById('dashRefreshIcon');
        if (icon) icon.classList.add('animate-spin');
        setStatus('Refreshing...', 'fa-rotate-right animate-spin');

        fetch(buildUrl(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success) throw new Error('Invalid payload');
                apply(payload.data);
                setStatus('', '');
                var updated = document.getElementById('lastUpdated');
                if (updated) updated.textContent = 'just now';
            })
            .catch(function () {
                setStatus('Refresh failed - retrying', 'fa-triangle-exclamation');
            })
            .finally(function () {
                state.loading = false;
                if (icon) icon.classList.remove('animate-spin');
            });
    }

    function updateLiveBadge() {
        var dot = document.getElementById('liveDot');
        var label = document.getElementById('liveLabel');
        if (!dot || !label) return;

        if (state.interval > 0) {
            dot.className = 'h-2 w-2 rounded-full bg-emerald-300 animate-pulse';
            label.textContent = 'Live - ' + state.interval + 's';
        } else {
            dot.className = 'h-2 w-2 rounded-full bg-slate-400';
            label.textContent = 'Auto-refresh off';
        }
    }

    function schedule() {
        if (state.timer) clearInterval(state.timer);
        state.timer = null;

        if (state.interval > 0) {
            state.timer = setInterval(function () {
                if (document.visibilityState === 'visible') refresh(false);
            }, state.interval * 1000);
        }

        updateLiveBadge();
    }

    function watchChartResize() {
        if (state.observer || typeof ResizeObserver === 'undefined') return;

        var lastWidth = root.clientWidth;
        state.observer = new ResizeObserver(function () {
            var width = root.clientWidth;
            if (width === lastWidth) return;
            lastWidth = width;

            Object.keys(state.charts).forEach(function (key) {
                try { state.charts[key].resize(); } catch (e) {}
            });
        });

        state.observer.observe(root);
    }

    function cleanup() {
        if (state.timer) clearInterval(state.timer);
        if (state.uiTimer) clearInterval(state.uiTimer);
        if (state.chartWaitTimer) clearInterval(state.chartWaitTimer);
        if (state.observer) {
            try { state.observer.disconnect(); } catch (e) {}
        }
        state.timer = null;
        state.uiTimer = null;
        state.observer = null;
        destroyCharts();
    }

    document.querySelectorAll('.range-pill').forEach(function (button) {
        button.addEventListener('click', function () {
            var range = button.getAttribute('data-range');
            if (range === 'custom') {
                state.range = 'custom';
                setRangeButtons('custom');
                updateExportLink();
                return;
            }

            state.range = range;
            setRangeButtons(range);
            updateExportLink();
            refresh(true);
        });
    });

    var customForm = document.getElementById('customRangeForm');
    if (customForm) {
        customForm.addEventListener('submit', function (event) {
            event.preventDefault();
            var from = document.getElementById('customFrom');
            var to = document.getElementById('customTo');
            var error = document.getElementById('customRangeError');

            if (!from || !to || !from.value || !to.value) {
                if (error) { error.textContent = 'Choose both dates.'; error.classList.remove('hidden'); }
                return;
            }
            if (from.value > to.value) {
                if (error) { error.textContent = 'From date must be before To date.'; error.classList.remove('hidden'); }
                return;
            }
            if (error) error.classList.add('hidden');

            state.range = 'custom';
            state.from = from.value;
            state.to = to.value;
            updateExportLink();
            refresh(true);
        });
    }

    var refreshButton = document.getElementById('dashRefresh');
    if (refreshButton) {
        refreshButton.addEventListener('click', function () { refresh(true); });
    }

    var intervalSelect = document.getElementById('refreshInterval');
    if (intervalSelect) {
        intervalSelect.value = String(state.interval);
        intervalSelect.addEventListener('change', function () {
            state.interval = parseInt(intervalSelect.value, 10) || 0;
            try { localStorage.setItem('admin_dash_refresh', String(state.interval)); } catch (e) {}
            schedule();
            if (state.interval > 0) refresh(true);
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible' && state.interval > 0 && (Date.now() - state.updatedAt) > state.interval * 1000) {
            refresh(false);
        }
    });

    state.uiTimer = setInterval(function () {
        var el = document.getElementById('lastUpdated');
        if (!el) return;
        var seconds = Math.round((Date.now() - state.updatedAt) / 1000);
        el.textContent = seconds < 5 ? 'just now' : (seconds < 60 ? seconds + 's ago' : Math.floor(seconds / 60) + 'm ago');
    }, 5000);

    window.__adminDashboardCleanup = cleanup;

    setRangeButtons(state.range);
    updateLiveBadge();
    updateExportLink();
    updateSnapshot(boot.snapshot);
    updateSystem(boot.system);
    updateRevenue(boot.revenue);
    updateRangeMeta(boot.range);

    whenChartReady(function () {
        createCharts();
        watchChartResize();
    });

    schedule();
})();
</script>
@endsection
