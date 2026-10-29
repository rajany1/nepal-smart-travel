@extends('admin.layout')

@section('title', 'Content Safety')

@section('content')
@php
    $counts = $insights['counts'];
    $thresholds = $insights['thresholds'];
    $entityLabels = \App\Services\AdminSafetyService::ENTITY_LABELS;
    $sourceLabels = \App\Services\AdminSafetyService::SOURCE_LABELS;

    $sectionTabs = [
        ['key' => 'violations', 'label' => 'Violations', 'count' => $counts['violations_total'], 'dot' => 'bg-rose-400'],
        ['key' => 'users', 'label' => 'Accounts', 'count' => $counts['accounts_total'], 'dot' => 'bg-amber-400'],
        ['key' => 'words', 'label' => 'Dictionary', 'count' => $counts['words_active'], 'dot' => 'bg-sky-400'],
    ];

    // Drop duplicate aliases (review/place_review, offer/reward_offer, ...) so
    // the content filter can still reach every entity type even when no
    // violations exist yet.
    $entityOptions = [];
    foreach ($entityLabels as $entityKey => $entityLabel) {
        $entityOptions[$entityLabel] ??= $entityKey;
    }
    $tabUrl = fn (string $key) => route('admin.moderation', ['tab' => $key]);

    $severityStyles = [
        'mild' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'moderate' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'severe' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
    $sourceStyles = [
        'live' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'sweep' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'system' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'ai' => 'bg-sky-50 text-sky-700 ring-sky-200',
    ];
    $statusStyles = [
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'suspended' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'banned' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];

    $perPageOptions = [15, 20, 25, 50, 100];

    // ---- Violations tab filters ----
    $vSort = request('sort', 'newest');
    $vSortLabels = [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'severity' => 'Severity high to low',
    ];
    $vSortLabel = $vSortLabels[$vSort] ?? 'Newest first';
    $vPerPage = $violations?->perPage() ?? 20;
    $chipBase = fn (array $except, string $tab) => route('admin.moderation', array_merge(
        request()->except(array_merge($except, ['page', 'fragment'])),
        ['tab' => $tab]
    ));
    $vChip = fn (array $except) => $chipBase($except, 'violations');

    $vFilters = [];
    if (request()->filled('q')) {
        $vFilters[] = ['label' => 'Search: “'.request('q').'”', 'url' => $vChip(['q'])];
    }
    if (request()->filled('entity')) {
        $vFilters[] = ['label' => 'Content: '.($entityLabels[request('entity')] ?? ucfirst(request('entity'))), 'url' => $vChip(['entity'])];
    }
    if (request()->filled('severity')) {
        $vFilters[] = ['label' => 'Severity: '.ucfirst(request('severity')), 'url' => $vChip(['severity'])];
    }
    if (request()->filled('source')) {
        $vFilters[] = ['label' => 'Source: '.($sourceLabels[request('source')] ?? ucfirst(request('source'))), 'url' => $vChip(['source'])];
    }
    if (request()->filled('date_range')) {
        $rangeLabels = ['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'];
        $vFilters[] = ['label' => 'Date: '.($rangeLabels[request('date_range')] ?? request('date_range')), 'url' => $vChip(['date_range'])];
    }

    // ---- Users tab filters ----
    $uSort = request('sort', 'violations');
    $uSortLabels = [
        'violations' => 'Most violations',
        'strikes' => 'Most strikes',
        'recent' => 'Recently flagged',
        'name' => 'Name A-Z',
    ];
    $uSortLabel = $uSortLabels[$uSort] ?? 'Most violations';
    $uPerPage = $users?->perPage() ?? 20;
    $uChip = fn (array $except) => $chipBase($except, 'users');
    $uFilters = [];
    if (request()->filled('q')) {
        $uFilters[] = ['label' => 'Search: “'.request('q').'”', 'url' => $uChip(['q'])];
    }
    if (request()->filled('status')) {
        $uFilters[] = ['label' => 'Status: '.ucfirst(request('status')), 'url' => $uChip(['status'])];
    }

    // ---- Dictionary tab filters ----
    $wSort = request('sort', 'alpha');
    $wSortLabels = [
        'alpha' => 'Word A-Z',
        'newest' => 'Newest first',
        'severity' => 'Severity high to low',
    ];
    $wSortLabel = $wSortLabels[$wSort] ?? 'Word A-Z';
    $wPerPage = $words?->perPage() ?? 20;
    $wChip = fn (array $except) => $chipBase($except, 'words');
    $wFilters = [];
    if (request()->filled('q')) {
        $wFilters[] = ['label' => 'Search: “'.request('q').'”', 'url' => $wChip(['q'])];
    }
    if (request()->filled('severity')) {
        $wFilters[] = ['label' => 'Severity: '.ucfirst(request('severity')), 'url' => $wChip(['severity'])];
    }
    if (request()->filled('status')) {
        $wFilters[] = ['label' => 'Status: '.ucfirst(request('status')), 'url' => $wChip(['status'])];
    }

    // ---- Per-row modal payload (lives inside #liveTable so AJAX pagination
    // keeps the edit/view buttons working) ----
    $pageViolations = $violations
        ? collect($violations->items())->map(fn ($v) => [
            'id' => $v->id,
            'user_id' => $v->user_id,
            'user_name' => $v->user?->name,
            'user_email' => $v->user?->email,
            'user_status' => $v->user?->status,
            'entity_label' => $entityLabels[$v->entity_type] ?? ucfirst(str_replace('_', ' ', (string) $v->entity_type)),
            'entity_id' => $v->entity_id,
            'field' => $v->field,
            'original' => $v->original_text,
            'censored' => $v->censored_text,
            'severity' => $v->severity,
            'source' => $sourceLabels[$v->source] ?? ucfirst((string) $v->source),
            'found_words' => is_array($v->found_words) ? array_values($v->found_words) : [],
            'created_at' => $v->created_at?->format('M j, Y g:i A'),
            'created_human' => $v->created_at?->diffForHumans(),
        ])->values()->all()
        : [];

    $riskFor = function ($user) use ($thresholds) {
        $strikes = (int) $user->moderation_strikes_count;
        if ($strikes >= $thresholds['ban_at']) {
            return ['label' => 'Critical', 'class' => 'bg-rose-50 text-rose-700 ring-rose-200', 'dot' => 'bg-rose-500'];
        }
        if ($strikes >= $thresholds['suspend_at']) {
            return ['label' => 'High', 'class' => 'bg-orange-50 text-orange-700 ring-orange-200', 'dot' => 'bg-orange-500'];
        }
        if ($strikes >= $thresholds['warn_at']) {
            return ['label' => 'Watch', 'class' => 'bg-amber-50 text-amber-700 ring-amber-200', 'dot' => 'bg-amber-400'];
        }

        return ['label' => 'Clear', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'dot' => 'bg-emerald-500'];
    };
@endphp

<div id="adminSafety" class="space-y-5 p-4 sm:p-5 lg:p-6">
    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-accent-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-primary-200">Trust &amp; safety &middot; Content safety</p>
                <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Content safety command center</h1>
                <p class="mt-1 max-w-xl text-sm leading-relaxed text-primary-100">Censor harmful content, track repeat offenders and enforce warnings, suspensions and blocks with a full accountability record.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-shield-halved text-[10px]"></i>{{ number_format($counts['violations_total']) }} violations
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-clock text-[10px]"></i>
                        @if($insights['latest_violation'])
                            Latest {{ $insights['latest_violation'] }}
                        @else
                            No violations recorded yet
                        @endif
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-spell-check text-[10px]"></i>{{ number_format($counts['words_active']) }} active words
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-location-dot text-[10px]"></i>Nepal time
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-semibold {{ $counts['words_active'] > 0 ? 'bg-emerald-400/15 text-emerald-100' : 'bg-rose-400/15 text-rose-100' }}">
                        <span class="h-2 w-2 rounded-full {{ $counts['words_active'] > 0 ? 'bg-emerald-300' : 'bg-rose-300' }}"></span>
                        {{ $counts['words_active'] > 0 ? 'Review agent armed' : 'Dictionary empty — agent blind' }}
                    </span>
                    <a href="{{ route('admin.moderation', request()->except('fragment')) }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20">
                        <i class="fas fa-rotate-right"></i> Refresh
                    </a>
                    @if($canManageSafety)
                        <a href="{{ $tabUrl('words') }}#wordForm" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                            <i class="fas fa-plus"></i> Add word
                        </a>
                    @endif
                </div>
                <p class="text-xs text-primary-200">Snapshot generated at {{ $insights['generated_at'] }}</p>
            </div>
        </div>

        <div class="relative mt-5 flex flex-wrap items-center gap-1.5 rounded-2xl bg-white/10 p-1">
            @foreach($sectionTabs as $sectionTab)
                @php $isActiveTab = $tab === $sectionTab['key']; @endphp
                <a href="{{ $tabUrl($sectionTab['key']) }}"
                   aria-current="{{ $isActiveTab ? 'page' : 'false' }}"
                   class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition {{ $isActiveTab ? 'bg-white text-slate-900 shadow-sm' : 'text-primary-100 hover:bg-white/10 hover:text-white' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $sectionTab['dot'] }}"></span>
                    {{ $sectionTab['label'] }}
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $isActiveTab ? 'bg-slate-100 text-slate-600' : 'bg-white/10 text-primary-100' }}">{{ number_format($sectionTab['count']) }}</span>
                </a>
            @endforeach
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
                    <h2 class="text-sm font-semibold text-slate-900">Safety activity</h2>
                    <p class="mt-1 text-xs text-slate-500">Violations censored and enforcement actions over the last 30 days.</p>
                </div>
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 font-semibold text-rose-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        {{ number_format($insights['trend']['violations_total']) }} violations
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        {{ number_format($insights['trend']['enforcement_total']) }} actions
                    </span>
                </div>
            </div>
            <div class="relative mt-4 h-64">
                <canvas id="safetyTrendChart" aria-label="Violations and enforcement actions over the last 30 days" role="img"></canvas>
                <div id="safetyTrendFallback" class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                    Chart library unavailable &mdash; totals are shown above.
                </div>
            </div>
            <div class="mt-3 flex items-center gap-4 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-700/70"></span> Violations</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-amber-500"></span> Enforcement</span>
                @if($counts['violations_today'] === 0)
                    <span class="ml-auto text-slate-400">Nothing censored today</span>
                @else
                    <span class="ml-auto font-semibold text-slate-600">{{ number_format($counts['violations_today']) }} today</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Breakdown</h2>
                    <p class="mt-1 text-xs text-slate-500">Across all {{ number_format($counts['violations_total']) }} violations.</p>
                </div>
                <div class="flex flex-wrap rounded-xl bg-slate-100 p-0.5 text-[10px] font-semibold">
                    <button type="button" data-safety-breakdown-tab="severity" class="rounded-lg bg-white px-2 py-1.5 text-slate-900 shadow-sm">Severity</button>
                    <button type="button" data-safety-breakdown-tab="entity" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Content</button>
                    <button type="button" data-safety-breakdown-tab="source" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Source</button>
                    <button type="button" data-safety-breakdown-tab="enforcement" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Actions</button>
                </div>
            </div>

            <div data-safety-breakdown-panel="severity" class="mt-4 flex flex-1 flex-col">
                <div class="relative mx-auto h-40 w-40 flex-shrink-0">
                    <canvas id="safetySeverityChart" aria-label="Violation severity distribution" role="img"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span class="text-xl font-semibold text-slate-900">{{ number_format($counts['violations_total']) }}</span>
                        <span class="text-[9px] font-semibold uppercase tracking-[0.16em] text-slate-400">events</span>
                    </div>
                    <div id="safetySeverityFallback" class="absolute inset-0 hidden items-center justify-center rounded-full bg-slate-50 text-[10px] text-slate-400">No data</div>
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
                <p class="mt-3 border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Severity is set by the harshest word found in the content.</p>
            </div>

            <div data-safety-breakdown-panel="entity" class="mt-4 hidden flex-1 space-y-3">
                @forelse($insights['entities'] as $row)
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
                    <p class="py-6 text-center text-xs text-slate-400">No content types recorded yet.</p>
                @endforelse
                @if(count($insights['entities']) > 0)
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Top {{ count($insights['entities']) }} content types by violation volume.</p>
                @endif
            </div>

            <div data-safety-breakdown-panel="source" class="mt-4 hidden flex-1 space-y-3">
                @forelse($insights['sources'] as $row)
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
                    <p class="py-6 text-center text-xs text-slate-400">No detection sources recorded yet.</p>
                @endforelse
                @if(count($insights['sources']) > 0)
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Realtime guard catches submissions; the sweep agent cleans older content.</p>
                @endif
            </div>

            <div data-safety-breakdown-panel="enforcement" class="mt-4 hidden flex-1 space-y-3">
                @foreach($insights['enforcement'] as $row)
                    @php $pct = $counts['strikes_total'] > 0 ? $row['value'] / $counts['strikes_total'] * 100 : 0; @endphp
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="min-w-0 truncate font-medium text-slate-700">{{ $row['label'] }}</span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span></span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $pct) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
                <div class="mt-1 flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2 text-[11px]">
                    <span class="text-slate-500">Automated</span>
                    <span class="font-semibold text-slate-800">{{ number_format($counts['auto_strikes']) }} <span class="font-normal text-slate-400">&middot; {{ number_format($insights['coverage']['auto_pct'], 1) }}%</span></span>
                </div>
                <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2 text-[11px]">
                    <span class="text-slate-500">Manual</span>
                    <span class="font-semibold text-slate-800">{{ number_format($counts['manual_strikes']) }}</span>
                </div>
            </div>
        </div>
    </section>

    {{-- Coverage strip --}}
    <section class="grid gap-2 rounded-3xl border border-slate-200 bg-white p-2 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-2xl bg-rose-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-rose-600 shadow-sm"><i class="fas fa-bolt text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-700/70">Detected today</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['violations_today']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($counts['violations_yesterday']) }} yesterday</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-amber-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-amber-600 shadow-sm"><i class="fas fa-user-clock text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700/70">Repeat offenders</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['coverage']['repeat_total']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($insights['coverage']['repeat_pct'], 1) }}% of flagged users</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-indigo-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm"><i class="fas fa-robot text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700/70">Automated enforcement</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['coverage']['auto_pct'], 1) }}% <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($counts['auto_strikes']) }} actions</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-sky-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm"><i class="fas fa-spell-check text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-sky-700/70">Dictionary armed</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['words_active']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($counts['words_severe']) }} severe</span></p>
            </div>
        </div>
    </section>

    {{-- Tab panel --}}
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div id="liveTable">
            @if($tab === 'violations')
                <div class="border-b border-slate-100 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900">Violation log</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ number_format($violations->total()) }} results</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $vSortLabel }}
                                @if($violations->hasPages())
                                    &middot; page {{ $violations->currentPage() }} of {{ $violations->lastPage() }}
                                @endif
                                @if(count($vFilters) > 0)
                                    &middot; filtered
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.moderation', ['tab' => 'users']) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                                <i class="fas fa-users"></i> Offender watchlist
                            </a>
                            <a href="{{ route('admin.moderation', ['tab' => 'violations']) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                                <i class="fas fa-rotate-right"></i> Refresh
                            </a>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('admin.moderation') }}" class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-12">
                        <input type="hidden" name="tab" value="violations">
                        <div class="sm:col-span-2 xl:col-span-4">
                            <label class="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="vSearch"><i class="fas fa-search text-[9px]"></i> Search</label>
                            <input id="vSearch" type="search" name="q" value="{{ request('q') }}" placeholder="User, email or content..." class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="vEntity">Content</label>
                            <select id="vEntity" name="entity" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All types</option>
                                @foreach($entityOptions as $entityLabel => $entityKey)
                                    <option value="{{ $entityKey }}" {{ request('entity') === $entityKey ? 'selected' : '' }}>{{ $entityLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="vSeverity">Severity</label>
                            <select id="vSeverity" name="severity" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All severities</option>
                                @foreach(['mild', 'moderate', 'severe'] as $severity)
                                    <option value="{{ $severity }}" {{ request('severity') === $severity ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="vSource">Source</label>
                            <select id="vSource" name="source" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All sources</option>
                                @foreach($sourceLabels as $key => $label)
                                    <option value="{{ $key }}" {{ request('source') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="vDate">Date</label>
                            <select id="vDate" name="date_range" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All time</option>
                                <option value="today" {{ request('date_range') === 'today' ? 'selected' : '' }}>Today</option>
                                <option value="7d" {{ request('date_range') === '7d' ? 'selected' : '' }}>Last 7 days</option>
                                <option value="30d" {{ request('date_range') === '30d' ? 'selected' : '' }}>Last 30 days</option>
                                <option value="90d" {{ request('date_range') === '90d' ? 'selected' : '' }}>Last 90 days</option>
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="vSort">Sort</label>
                            <select id="vSort" name="sort" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($vSortLabels as $key => $label)
                                    <option value="{{ $key }}" {{ $vSort === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="vPerPage">Per page</label>
                            <select id="vPerPage" name="per_page" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" {{ (int) $vPerPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2 xl:col-span-2">
                            <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary-700 px-3 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <a href="{{ route('admin.moderation', ['tab' => 'violations']) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-500 transition hover:border-slate-300 hover:bg-slate-50" title="Reset filters">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>

                    @if(count($vFilters) > 0)
                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Filters</span>
                            @foreach($vFilters as $filter)
                                <a href="{{ $filter['url'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                                    {{ $filter['label'] }} <i class="fas fa-xmark text-[9px]"></i>
                                </a>
                            @endforeach
                            <a href="{{ route('admin.moderation', ['tab' => 'violations']) }}" class="ml-1 text-[11px] font-semibold text-primary-700 hover:underline">Clear all</a>
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">User</th>
                                <th class="px-4 py-3 text-left font-semibold">Content</th>
                                <th class="px-4 py-3 text-left font-semibold">Original &rarr; censored</th>
                                <th class="px-4 py-3 text-left font-semibold">Severity</th>
                                <th class="px-4 py-3 text-left font-semibold">Source</th>
                                <th class="px-4 py-3 text-left font-semibold">When</th>
                                <th class="px-5 py-3 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($violations as $v)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3.5">
                                        @if($v->user)
                                            <div class="flex items-center gap-2.5">
                                                <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700">{{ strtoupper(mb_substr($v->user->name, 0, 1)) }}</span>
                                                <div class="min-w-0">
                                                    <a href="{{ route('admin.moderation.users', $v->user) }}" class="block truncate font-medium text-slate-800 hover:text-primary-700 hover:underline">{{ $v->user->name }}</a>
                                                    <span class="block truncate text-[11px] text-slate-400">{{ $v->user->email }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-xs italic text-slate-400">Deleted user</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $entityLabels[$v->entity_type] ?? ucfirst(str_replace('_', ' ', $v->entity_type)) }}</span>
                                        <div class="mt-1 text-[10px] text-slate-400">#{{ $v->entity_id ?? '—' }} &middot; {{ $v->field }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="max-w-[260px] truncate text-xs text-slate-400 line-through decoration-rose-300" title="{{ $v->original_text }}">{{ \Illuminate\Support\Str::limit($v->original_text, 58) }}</div>
                                        <div class="mt-0.5 max-w-[260px] truncate text-xs text-emerald-700" title="{{ $v->censored_text }}">{{ \Illuminate\Support\Str::limit($v->censored_text, 58) }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $severityStyles[$v->severity] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ ucfirst($v->severity) }}</span>
                                        @if($v->found_words)
                                            <div class="mt-1 max-w-[150px] truncate text-[10px] text-rose-500" title="{{ implode(', ', $v->found_words) }}">{{ implode(', ', array_slice($v->found_words, 0, 3)) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium ring-1 {{ $sourceStyles[$v->source] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ $sourceLabels[$v->source] ?? ucfirst($v->source) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-xs text-slate-500" title="{{ $v->created_at?->format('M j, Y g:i A') }}">{{ $v->created_at?->diffForHumans() }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <button type="button" class="js-view-violation inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" data-violation-id="{{ $v->id }}">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center">
                                        <i class="fas fa-shield-halved mb-3 block text-3xl {{ count($vFilters) > 0 ? 'text-slate-300' : 'text-emerald-400' }}"></i>
                                        <p class="text-sm font-medium text-slate-600">{{ count($vFilters) > 0 ? 'No violations match these filters' : 'No violations recorded yet' }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ count($vFilters) > 0 ? 'Try a different severity, source or date range.' : 'The review agent has not censored any content so far.' }}</p>
                                        @if(count($vFilters) > 0)
                                            <a href="{{ route('admin.moderation', ['tab' => 'violations']) }}" class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-800">Reset filters</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                    <p class="text-[11px] text-slate-400">
                        @if($violations->total() > 0)
                            Showing {{ number_format($violations->firstItem()) }}–{{ number_format($violations->lastItem()) }} of {{ number_format($violations->total()) }}
                        @else
                            No results
                        @endif
                    </p>
                    {{ $violations->links() }}
                </div>

                <script type="application/json" id="safetyPageData">@json($pageViolations)</script>
            @elseif($tab === 'users')
                <div class="border-b border-slate-100 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900">Offender watchlist</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ number_format($users->total()) }} accounts</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $uSortLabel }}
                                @if($users->hasPages())
                                    &middot; page {{ $users->currentPage() }} of {{ $users->lastPage() }}
                                @endif
                                &middot; strikes counted over the last {{ $thresholds['window_days'] }} days
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-700">
                                <i class="fas fa-triangle-exclamation"></i>{{ number_format($counts['repeat_offenders']) }} repeat offenders
                            </span>
                            <a href="{{ route('admin.moderation', ['tab' => 'users']) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                                <i class="fas fa-rotate-right"></i> Refresh
                            </a>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('admin.moderation') }}" class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-12">
                        <input type="hidden" name="tab" value="users">
                        <div class="sm:col-span-2 xl:col-span-5">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="uSearch">Search</label>
                            <input id="uSearch" type="search" name="q" value="{{ request('q') }}" placeholder="Name or email..." class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="uStatus">Status</label>
                            <select id="uStatus" name="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All statuses</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                <option value="banned" {{ request('status') === 'banned' ? 'selected' : '' }}>Blocked</option>
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="uSort">Sort</label>
                            <select id="uSort" name="sort" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($uSortLabels as $key => $label)
                                    <option value="{{ $key }}" {{ $uSort === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="uPerPage">Per page</label>
                            <select id="uPerPage" name="per_page" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" {{ (int) $uPerPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2 xl:col-span-3">
                            <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary-700 px-3 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <a href="{{ route('admin.moderation', ['tab' => 'users']) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-500 transition hover:border-slate-300 hover:bg-slate-50" title="Reset filters">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>

                    @if(count($uFilters) > 0)
                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Filters</span>
                            @foreach($uFilters as $filter)
                                <a href="{{ $filter['url'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                                    {{ $filter['label'] }} <i class="fas fa-xmark text-[9px]"></i>
                                </a>
                            @endforeach
                            <a href="{{ route('admin.moderation', ['tab' => 'users']) }}" class="ml-1 text-[11px] font-semibold text-primary-700 hover:underline">Clear all</a>
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1080px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">User</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Violations</th>
                                <th class="px-4 py-3 text-left font-semibold">Strikes ({{ $thresholds['window_days'] }}d)</th>
                                <th class="px-4 py-3 text-left font-semibold">Risk</th>
                                <th class="px-4 py-3 text-left font-semibold">Last event</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($users as $u)
                                @php $risk = $riskFor($u); @endphp
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2.5">
                                            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                                            <div class="min-w-0">
                                                <div class="truncate font-medium text-slate-800">{{ $u->name }}</div>
                                                <div class="truncate text-[11px] text-slate-400">{{ $u->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $statusStyles[$u->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ ucfirst($u->status) }}</span>
                                        @if($u->status === 'suspended' && $u->suspended_until)
                                            <div class="mt-1 text-[10px] text-amber-600">until {{ $u->suspended_until->format('M j, g:i A') }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex rounded-lg px-2 py-1 text-xs font-semibold {{ $u->content_violations_count > 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-50 text-slate-500' }}">{{ number_format($u->content_violations_count) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex rounded-lg px-2 py-1 text-xs font-semibold {{ $u->moderation_strikes_count > 0 ? 'bg-amber-50 text-amber-700' : 'bg-slate-50 text-slate-500' }}">{{ number_format($u->moderation_strikes_count) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $risk['class'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $risk['dot'] }}"></span>{{ $risk['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-500">
                                        @if($u->content_violations_max_created_at)
                                            {{ \Illuminate\Support\Carbon::parse($u->content_violations_max_created_at)->diffForHumans() }}
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                                            <a href="{{ route('admin.moderation.users', $u) }}" class="inline-flex items-center gap-1 rounded-lg bg-primary-700 px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-primary-800">
                                                <i class="fas fa-clock-rotate-left"></i> History
                                            </a>
                                            @if($u->status !== 'banned')
                                                <button type="button" class="js-strike inline-flex items-center gap-1 rounded-lg bg-amber-500 px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-amber-600" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" data-level="warning">
                                                    <i class="fas fa-flag"></i> Warn
                                                </button>
                                            @endif
                                            @if($u->status !== 'banned' && $u->status !== 'suspended')
                                                <button type="button" class="js-strike inline-flex items-center gap-1 rounded-lg bg-orange-500 px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-orange-600" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" data-level="suspend">
                                                    <i class="fas fa-pause"></i> Suspend
                                                </button>
                                            @endif
                                            @if($u->status !== 'banned')
                                                <button type="button" class="js-strike inline-flex items-center gap-1 rounded-lg bg-rose-600 px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-rose-700" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" data-level="block">
                                                    <i class="fas fa-ban"></i> Block
                                                </button>
                                            @endif
                                            @if($u->status !== 'active')
                                                <form method="POST" action="{{ route('admin.moderation.activate', $u) }}" data-native>
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-emerald-700">
                                                        <i class="fas fa-undo"></i> Activate
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-14 text-center">
                                        <i class="fas fa-users mb-3 block text-3xl text-slate-300"></i>
                                        <p class="text-sm font-medium text-slate-600">No accounts match these filters</p>
                                        <p class="mt-1 text-xs text-slate-400">Try a different status or clear the search.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                    <p class="text-[11px] text-slate-400">
                        @if($users->total() > 0)
                            Showing {{ number_format($users->firstItem()) }}–{{ number_format($users->lastItem()) }} of {{ number_format($users->total()) }}
                        @else
                            No results
                        @endif
                    </p>
                    {{ $users->links() }}
                </div>
            @else
                <div class="border-b border-slate-100 p-4 sm:p-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-semibold text-slate-900">Censor dictionary</h2>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ number_format($counts['words_active']) }} active / {{ number_format($counts['words_total']) }} total</span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                Matching is case-insensitive, leet-aware and covers romanized + Devanagari Nepali. Changes apply to the very next scan.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700">Severe {{ number_format($counts['words_severe']) }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">Moderate {{ number_format($counts['words_moderate']) }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">Mild {{ number_format($counts['words_mild']) }}</span>
                        </div>
                    </div>

                    @if($canManageSafety)
                        <form id="wordForm" method="POST" action="{{ route('admin.moderation.words.store') }}" data-native class="mt-4 flex flex-wrap items-end gap-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-3">
                            @csrf
                            <div class="min-w-[180px] flex-1">
                                <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="newWord">Add a word or phrase</label>
                                <input id="newWord" type="text" name="word" required minlength="2" maxlength="100" placeholder="e.g. abusive term" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            </div>
                            <div>
                                <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="newWordSeverity">Severity</label>
                                <select id="newWordSeverity" name="severity" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                    <option value="severe">Severe</option>
                                    <option value="moderate" selected>Moderate</option>
                                    <option value="mild">Mild</option>
                                </select>
                            </div>
                            <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-xl bg-primary-700 px-4 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-plus"></i> Add word
                            </button>
                            <p class="w-full text-[10px] text-slate-400">The word is stored lowercase; existing entries are never overwritten.</p>
                        </form>
                    @endif

                    <form method="GET" action="{{ route('admin.moderation') }}" class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-12">
                        <input type="hidden" name="tab" value="words">
                        <div class="sm:col-span-2 xl:col-span-4">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="wSearch">Search</label>
                            <input id="wSearch" type="search" name="q" value="{{ request('q') }}" placeholder="Word or phrase..." class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="wSeverity">Severity</label>
                            <select id="wSeverity" name="severity" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All severities</option>
                                @foreach(['mild', 'moderate', 'severe'] as $severity)
                                    <option value="{{ $severity }}" {{ request('severity') === $severity ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="wStatus">Status</label>
                            <select id="wStatus" name="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All statuses</option>
                                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Disabled</option>
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="wSort">Sort</label>
                            <select id="wSort" name="sort" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($wSortLabels as $key => $label)
                                    <option value="{{ $key }}" {{ $wSort === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2">
                            <label class="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-slate-400" for="wPerPage">Per page</label>
                            <select id="wPerPage" name="per_page" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($perPageOptions as $option)
                                    <option value="{{ $option }}" {{ (int) $wPerPage === $option ? 'selected' : '' }}>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2 xl:col-span-2">
                            <button type="submit" class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary-700 px-3 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <a href="{{ route('admin.moderation', ['tab' => 'words']) }}" class="inline-flex h-9 items-center justify-center rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-500 transition hover:border-slate-300 hover:bg-slate-50" title="Reset filters">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        </div>
                    </form>

                    @if(count($wFilters) > 0)
                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Filters</span>
                            @foreach($wFilters as $filter)
                                <a href="{{ $filter['url'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700">
                                    {{ $filter['label'] }} <i class="fas fa-xmark text-[9px]"></i>
                                </a>
                            @endforeach
                            <a href="{{ route('admin.moderation', ['tab' => 'words']) }}" class="ml-1 text-[11px] font-semibold text-primary-700 hover:underline">Clear all</a>
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Word / phrase</th>
                                <th class="px-4 py-3 text-left font-semibold">Severity</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Updated</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($words as $word)
                                <tr class="transition hover:bg-slate-50/70">
                                    <td class="px-5 py-3">
                                        <span class="font-mono text-sm font-medium text-slate-800">{{ $word->word }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $severityStyles[$word->severity] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">{{ ucfirst($word->severity) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $word->active ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-500 ring-slate-200' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $word->active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                            {{ $word->active ? 'Active' : 'Disabled' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $word->updated_at?->diffForHumans() }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center justify-end gap-1.5">
                                            <form method="POST" action="{{ route('admin.moderation.words.update', $word) }}" data-native class="flex items-center gap-1">
                                                @csrf
                                                @method('PUT')
                                                <select name="severity" aria-label="Severity for {{ $word->word }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-600 outline-none focus:border-primary-400">
                                                    @foreach(['mild', 'moderate', 'severe'] as $severity)
                                                        <option value="{{ $severity }}" {{ $word->severity === $severity ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg bg-slate-800 px-2.5 py-1.5 text-[11px] font-semibold text-white transition hover:bg-slate-900">
                                                    <i class="fas fa-check"></i> Save
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.moderation.words.update', $word) }}" data-native>
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="active" value="{{ $word->active ? 0 : 1 }}">
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold transition {{ $word->active ? 'border-slate-200 text-slate-600 hover:border-amber-200 hover:bg-amber-50 hover:text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                    <i class="fas {{ $word->active ? 'fa-pause' : 'fa-play' }}"></i> {{ $word->active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.moderation.words.destroy', $word) }}" data-native onsubmit="return confirm('Remove this word from the dictionary?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50" aria-label="Delete {{ $word->word }}">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-14 text-center">
                                        <i class="fas fa-spell-check mb-3 block text-3xl text-slate-300"></i>
                                        <p class="text-sm font-medium text-slate-600">{{ count($wFilters) > 0 ? 'No dictionary entries match these filters' : 'The dictionary is empty' }}</p>
                                        <p class="mt-1 text-xs text-slate-400">{{ count($wFilters) > 0 ? 'Try a different severity or status.' : 'Add the first word above to arm the review agent.' }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 sm:px-5">
                    <p class="text-[11px] text-slate-400">
                        @if($words->total() > 0)
                            Showing {{ number_format($words->firstItem()) }}–{{ number_format($words->lastItem()) }} of {{ number_format($words->total()) }}
                        @else
                            No results
                        @endif
                    </p>
                    {{ $words->links() }}
                </div>
            @endif
        </div>
    </section>
</div>

{{-- Violation detail modal --}}
<div id="violationModal" class="hidden fixed inset-0 z-40 bg-slate-950/60 p-4 backdrop-blur-sm">
    <div class="mx-auto mt-6 max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-5 py-4">
            <div>
                <p id="violationModalEntity" class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-400">Violation</p>
                <h3 class="mt-1 text-lg font-semibold text-slate-900">Violation detail</h3>
            </div>
            <button type="button" onclick="closeViolationModal()" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close violation detail">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="max-h-[70vh] space-y-4 overflow-y-auto p-5">
            <div class="flex flex-wrap items-center gap-2">
                <span id="violationModalSeverity"></span>
                <span id="violationModalSource"></span>
                <span id="violationModalTime" class="text-xs text-slate-400"></span>
            </div>
            <div id="violationModalUser" class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700"></div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-rose-500">Original</p>
                <p id="violationModalOriginal" class="mt-1 whitespace-pre-line break-words rounded-2xl bg-rose-50/60 p-3 text-sm text-slate-600 line-through decoration-rose-400"></p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-emerald-600">Censored</p>
                <p id="violationModalCensored" class="mt-1 whitespace-pre-line break-words rounded-2xl bg-emerald-50/60 p-3 text-sm text-slate-700"></p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Detected words</p>
                <div id="violationModalWords" class="mt-1 flex flex-wrap gap-1.5"></div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-4">
            <div id="violationModalActions" class="flex flex-wrap gap-2"></div>
            <button type="button" onclick="closeViolationModal()" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-200">Close</button>
        </div>
    </div>
</div>

{{-- Strike modal --}}
<div id="strikeModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
        <h3 class="mb-1 text-lg font-bold text-slate-800" id="strikeTitle"></h3>
        <p class="mb-4 text-sm text-slate-500" id="strikeDesc"></p>
        <form method="POST" action="" id="strikeForm">
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
        showChartFallback('safetyTrendFallback');
        showChartFallback('safetySeverityFallback');
    }

    function destroySafetyCharts() {
        if (!window.__safetyCharts) {
            window.__safetyCharts = {};
            return;
        }
        Object.keys(window.__safetyCharts).forEach(function (key) {
            try { window.__safetyCharts[key].destroy(); } catch (e) {}
        });
        window.__safetyCharts = {};
    }

    function initSafetyCharts() {
        if (!window.__safetyCharts) window.__safetyCharts = {};

        if (!window.Chart) {
            showAllFallbacks();
            return;
        }

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';

        var trendCanvas = document.getElementById('safetyTrendChart');
        if (trendCanvas && !window.__safetyCharts.trend) {
            window.__safetyCharts.trend = new Chart(trendCanvas, {
                type: 'bar',
                data: {
                    labels: trend.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Violations',
                            data: trend.violations,
                            backgroundColor: 'rgba(15, 118, 110, 0.72)',
                            hoverBackgroundColor: 'rgba(15, 118, 110, 0.95)',
                            borderRadius: 4,
                            maxBarThickness: 14,
                            order: 2
                        },
                        {
                            type: 'line',
                            label: 'Enforcement',
                            data: trend.enforcement,
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

        var severityCanvas = document.getElementById('safetySeverityChart');
        if (severityCanvas && !window.__safetyCharts.severity) {
            var hasSeverityData = severities.some(function (row) { return Number(row.value) > 0; });
            if (!hasSeverityData) {
                showChartFallback('safetySeverityFallback');
            } else {
                window.__safetyCharts.severity = new Chart(severityCanvas, {
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
                                        return ' ' + context.label + ': ' + (Number(context.parsed) || 0).toLocaleString();
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

        var existing = document.querySelector('script[data-safety-chartjs], script[data-alert-chartjs], script[data-report-chartjs]');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            existing.addEventListener('error', showAllFallbacks, { once: true });
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.async = true;
        script.setAttribute('data-safety-chartjs', '1');
        script.addEventListener('load', callback, { once: true });
        script.addEventListener('error', showAllFallbacks, { once: true });
        document.head.appendChild(script);
    }

    destroySafetyCharts();
    ensureChartLibrary(initSafetyCharts);

    var breakdownButtons = Array.prototype.slice.call(document.querySelectorAll('[data-safety-breakdown-tab]'));
    breakdownButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-safety-breakdown-tab');
            breakdownButtons.forEach(function (other) {
                var active = other === button;
                other.classList.toggle('bg-white', active);
                other.classList.toggle('shadow-sm', active);
                other.classList.toggle('text-slate-900', active);
                other.classList.toggle('text-slate-500', !active);
            });
            document.querySelectorAll('[data-safety-breakdown-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-safety-breakdown-panel') !== target);
            });
            if (target === 'severity' && window.__safetyCharts && window.__safetyCharts.severity) {
                window.__safetyCharts.severity.resize();
            }
        });
    });
})();
</script>
<script>
(function () {
    'use strict';

    function el(id) {
        return document.getElementById(id);
    }

    function currentViolations() {
        var node = el('safetyPageData');
        if (!node) return [];
        try {
            var parsed = JSON.parse(node.textContent || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function setBadge(node, label, classes) {
        if (!node) return;
        node.className = 'inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ' + classes;
        node.textContent = label;
    }

    function openViolationModal(id) {
        var violation = currentViolations().filter(function (row) { return String(row.id) === String(id); })[0];
        if (!violation) return;

        el('violationModalEntity').textContent = violation.entity_label + (violation.entity_id ? ' #' + violation.entity_id : '') + ' · ' + (violation.field || 'content');
        setBadge(el('violationModalSeverity'), violation.severity.charAt(0).toUpperCase() + violation.severity.slice(1), {
            mild: 'bg-slate-100 text-slate-600 ring-slate-200',
            moderate: 'bg-amber-50 text-amber-700 ring-amber-200',
            severe: 'bg-rose-50 text-rose-700 ring-rose-200'
        }[violation.severity] || 'bg-slate-100 text-slate-600 ring-slate-200');
        setBadge(el('violationModalSource'), violation.source, 'bg-slate-100 text-slate-600 ring-slate-200');
        el('violationModalTime').textContent = violation.created_at || '';

        var user = el('violationModalUser');
        user.textContent = violation.user_name
            ? violation.user_name + (violation.user_email ? ' · ' + violation.user_email : '') + (violation.user_status ? ' · ' + violation.user_status : '')
            : 'User record no longer exists.';

        el('violationModalOriginal').textContent = violation.original || '';
        el('violationModalCensored').textContent = violation.censored || '';

        var words = el('violationModalWords');
        words.innerHTML = '';
        var list = Array.isArray(violation.found_words) ? violation.found_words : [];
        if (list.length === 0) {
            var none = document.createElement('span');
            none.className = 'text-xs text-slate-400';
            none.textContent = 'No dictionary words stored for this event.';
            words.appendChild(none);
        } else {
            list.forEach(function (word) {
                var chip = document.createElement('span');
                chip.className = 'rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-200';
                chip.textContent = word;
                words.appendChild(chip);
            });
        }

        var actions = el('violationModalActions');
        actions.innerHTML = '';
        if (violation.user_id && violation.user_status !== 'banned') {
            var levels = [['warning', 'Warn', 'bg-amber-500 hover:bg-amber-600']];
            if (violation.user_status !== 'suspended') {
                levels.push(['suspend', 'Suspend', 'bg-orange-500 hover:bg-orange-600']);
            }
            levels.push(['block', 'Block', 'bg-rose-600 hover:bg-rose-700']);
            levels.forEach(function (level) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold text-white transition ' + level[2];
                button.textContent = level[1];
                button.addEventListener('click', function () {
                    openStrike(violation.user_id, violation.user_name, level[0]);
                });
                actions.appendChild(button);
            });
            var history = document.createElement('a');
            history.href = '/' + (window.adminPrefix || 'admin') + '/moderation/users/' + violation.user_id;
            history.className = 'inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50';
            history.textContent = 'History';
            actions.appendChild(history);
        }

        el('violationModal').classList.remove('hidden');
    }

    function closeViolationModal() {
        el('violationModal').classList.add('hidden');
    }

    function openStrike(userId, name, level) {
        var isWarning = level === 'warning';
        var isSuspend = level === 'suspend';

        el('strikeForm').action = '/' + (window.adminPrefix || 'admin') + '/moderation/strike/' + userId;
        el('strikeLevel').value = level;
        el('strikeTitle').textContent = name || 'Account action';
        el('strikeDesc').textContent = isWarning
            ? 'Record a formal warning for this account. The reason is kept in the moderation history.'
            : (isSuspend
                ? 'Temporarily suspend this user with a record of the reason.'
                : 'Permanently block this user with a record of the reason.');
        var submit = el('strikeSubmit');
        submit.textContent = isWarning ? 'Issue Warning' : (isSuspend ? 'Suspend User' : 'Block User');
        submit.className = 'rounded-lg px-5 py-2 text-sm font-semibold text-white ' + (isWarning ? 'bg-amber-500 hover:bg-amber-600' : (isSuspend ? 'bg-orange-500 hover:bg-orange-600' : 'bg-red-600 hover:bg-red-700'));
        el('strikeModal').classList.remove('hidden');
    }

    function closeStrike() {
        el('strikeModal').classList.add('hidden');
    }

    document.addEventListener('click', function (event) {
        var view = event.target.closest ? event.target.closest('.js-view-violation') : null;
        if (view) {
            event.preventDefault();
            openViolationModal(view.getAttribute('data-violation-id'));
            return;
        }

        var strike = event.target.closest ? event.target.closest('.js-strike') : null;
        if (strike) {
            event.preventDefault();
            openStrike(strike.getAttribute('data-user-id'), strike.getAttribute('data-user-name'), strike.getAttribute('data-level'));
            return;
        }

        if (event.target === el('violationModal')) closeViolationModal();
        if (event.target === el('strikeModal')) closeStrike();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeViolationModal();
            closeStrike();
        }
    });

    window.openViolationModal = openViolationModal;
    window.closeViolationModal = closeViolationModal;
    window.openStrike = openStrike;
    window.closeStrike = closeStrike;
})();
</script>
@endsection
