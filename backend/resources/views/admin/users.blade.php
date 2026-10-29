@extends('admin.layout')
@section('title', 'Users Management')

@section('content')
@php
    $counts = $insights['counts'];

    $roleTabs = [
        ['key' => 'all', 'label' => 'All users', 'count' => $counts['total'], 'dot' => 'bg-slate-400'],
        ['key' => 'user', 'label' => 'Users', 'count' => $counts['role_user'], 'dot' => 'bg-sky-500'],
        ['key' => 'moderator', 'label' => 'Moderators', 'count' => $counts['role_moderator'], 'dot' => 'bg-amber-500'],
        ['key' => 'admin', 'label' => 'Admins', 'count' => $counts['role_admin'], 'dot' => 'bg-indigo-500'],
        ['key' => 'business', 'label' => 'Business', 'count' => $counts['role_business'], 'dot' => 'bg-emerald-500'],
    ];

    $statusTabs = [
        ['key' => 'all', 'label' => 'All statuses', 'count' => $counts['total'], 'dot' => 'bg-slate-400'],
        ['key' => 'active', 'label' => 'Active', 'count' => $counts['active'], 'dot' => 'bg-emerald-500'],
        ['key' => 'suspended', 'label' => 'Suspended', 'count' => $counts['suspended'], 'dot' => 'bg-amber-500'],
        ['key' => 'banned', 'label' => 'Banned', 'count' => $counts['banned'], 'dot' => 'bg-rose-500'],
        ['key' => 'suspicious', 'label' => 'Suspicious', 'count' => $counts['suspicious'], 'dot' => 'bg-orange-500'],
    ];

    $roleTabUrl = fn (string $key) => route('admin.users', array_merge(
        request()->except(['role', 'page', 'fragment']),
        $key === 'all' ? [] : ['role' => $key]
    ));
    $statusTabUrl = fn (string $key) => route('admin.users', array_merge(
        request()->except(['status', 'page', 'fragment']),
        $key === 'all' ? [] : ['status' => $key]
    ));
    $filterUrl = fn (array $remove = []) => route('admin.users', request()->except(array_merge(['page', 'fragment'], $remove)));

    $activeFilters = [];
    if ($filters['search'] !== '') {
        $activeFilters[] = ['label' => 'Search: "'.$filters['search'].'"', 'remove' => 'search'];
    }
    if ($filters['verification'] !== '') {
        $activeFilters[] = ['label' => 'Verification: '.$verificationLabels[$filters['verification']], 'remove' => 'verification'];
    }
    if ($filters['risk'] !== '') {
        $activeFilters[] = ['label' => 'Risk: '.$riskLabels[$filters['risk']], 'remove' => 'risk'];
    }
    $hasFilters = count($activeFilters) > 0;

    $resetParams = [];
    if ($filters['role'] !== 'all') {
        $resetParams['role'] = $filters['role'];
    }
    if ($filters['status'] !== 'all') {
        $resetParams['status'] = $filters['status'];
    }
    $resetUrl = route('admin.users', $resetParams);
    $refreshUrl = route('admin.users', request()->except('fragment'));

    $kpiLinks = [
        route('admin.users'),
        route('admin.users', ['status' => 'active']),
        route('admin.users', ['sort' => 'newest']),
        route('admin.users', ['status' => 'suspicious']),
    ];

    $roleClasses = [
        'super_admin' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'admin' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'moderator' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'business' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'user' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
    $statusClasses = [
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'suspended' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'banned' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
    $statusIcons = [
        'active' => 'fa-circle-check',
        'suspended' => 'fa-circle-pause',
        'banned' => 'fa-ban',
    ];
@endphp

<div id="adminUsers" class="space-y-5 p-4 sm:p-5 lg:p-6">
    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-accent-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-primary-200">Identity &amp; access &middot; Users</p>
                <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Users command center</h1>
                <p class="mt-1 max-w-xl text-sm leading-relaxed text-primary-100">Find anyone, understand their standing and keep roles, verification and fraud risk in one place.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-users text-[10px]"></i>{{ number_format($counts['total']) }} accounts
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-user-shield text-[10px]"></i>{{ number_format($counts['staff']) }} staff
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-clock text-[10px]"></i>
                        @if($insights['latest_signup'])
                            Latest signup {{ $insights['latest_signup'] }}
                        @else
                            No signups yet
                        @endif
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-semibold {{ $counts['suspicious'] > 0 ? 'bg-amber-400/15 text-amber-100' : 'bg-emerald-400/15 text-emerald-100' }}">
                        <span class="h-2 w-2 rounded-full {{ $counts['suspicious'] > 0 ? 'bg-amber-300' : 'bg-emerald-300' }}"></span>
                        {{ $counts['suspicious'] > 0 ? number_format($counts['suspicious']).' under watch' : 'No accounts under watch' }}
                    </span>
                    <a href="{{ $refreshUrl }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20">
                        <i class="fas fa-rotate-right"></i> Refresh
                    </a>
                    <a href="{{ route('admin.users') }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                        <i class="fas fa-users"></i> All users
                    </a>
                </div>
                <p class="text-xs text-primary-200">Snapshot generated at {{ $insights['generated_at'] }}</p>
            </div>
        </div>
    </section>

    {{-- KPI cards --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($insights['kpis'] as $index => $kpi)
            <a href="{{ $kpiLinks[$index] }}" class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md" data-users-kpi="{{ $index }}">
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
            </a>
        @endforeach
    </section>

    {{-- Analytics --}}
    <section class="grid gap-3 xl:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Signup activity</h2>
                    <p class="mt-1 text-xs text-slate-500">New registrations and email verifications over the last 30 days.</p>
                </div>
                <div class="flex items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 font-semibold text-primary-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                        {{ number_format($insights['trend']['signups_total']) }} signups
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 font-semibold text-sky-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                        {{ number_format($insights['trend']['verifications_total']) }} verified
                    </span>
                </div>
            </div>
            <div class="relative mt-4 h-64">
                <canvas id="usersTrendChart" aria-label="New registrations and email verifications over the last 30 days" role="img"></canvas>
                <div id="usersTrendFallback" class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                    Chart library unavailable &mdash; totals are shown above.
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-700/70"></span> New registrations</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-sky-500"></span> Email verified</span>
                @if($insights['trend']['today'] === 0)
                    <span class="ml-auto text-slate-400">No signups today yet</span>
                @else
                    <span class="ml-auto font-semibold text-slate-600">{{ number_format($insights['trend']['today']) }} today</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Breakdown</h2>
                    <p class="mt-1 text-xs text-slate-500">Across all {{ number_format($counts['total']) }} accounts.</p>
                </div>
                <div class="flex flex-wrap justify-end gap-0.5 rounded-xl bg-slate-100 p-0.5 text-[10px] font-semibold">
                    <button type="button" data-users-breakdown-tab="roles" class="rounded-lg bg-white px-2 py-1.5 text-slate-900 shadow-sm">Roles</button>
                    <button type="button" data-users-breakdown-tab="status" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Status</button>
                    <button type="button" data-users-breakdown-tab="verification" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Verification</button>
                    <button type="button" data-users-breakdown-tab="risk" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Risk</button>
                </div>
            </div>

            <div data-users-breakdown-panel="roles" class="mt-4 flex-1 space-y-3">
                @foreach($insights['roles'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="inline-flex min-w-0 items-center gap-2 text-slate-600"><span class="h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span><span class="truncate">{{ $row['label'] }}</span></span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span> &middot; {{ number_format($row['pct'], 1) }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
                <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Admins include administrators and super admins. Accounts without a role are counted as users.</p>
            </div>

            <div data-users-breakdown-panel="status" class="mt-4 hidden flex-1 space-y-3">
                @foreach($insights['statuses'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="inline-flex min-w-0 items-center gap-2 text-slate-600"><span class="h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span><span class="truncate">{{ $row['label'] }}</span></span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span> &middot; {{ number_format($row['pct'], 1) }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
                <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Status controls whether an account can sign in. Suspicious accounts are flagged separately by fraud risk.</p>
            </div>

            <div data-users-breakdown-panel="verification" class="mt-4 hidden flex-1 space-y-3">
                @foreach($insights['verification'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="inline-flex min-w-0 items-center gap-2 text-slate-600"><span class="h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span><span class="truncate">{{ $row['label'] }}</span></span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span> &middot; {{ number_format($row['pct'], 1) }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
                <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">{{ number_format($counts['phone_verified']) }} phone numbers are verified. Email verification is the primary trust signal.</p>
            </div>

            <div data-users-breakdown-panel="risk" class="mt-4 hidden flex-1 space-y-3">
                @foreach($insights['risk'] as $row)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="inline-flex min-w-0 items-center gap-2 text-slate-600"><span class="h-2 w-2 flex-shrink-0 rounded-full" style="background: {{ $row['color'] }}"></span><span class="truncate">{{ $row['label'] }}</span></span>
                            <span class="flex-shrink-0 text-slate-400"><span class="font-semibold text-slate-800">{{ number_format($row['value']) }}</span> &middot; {{ number_format($row['pct'], 1) }}%</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full" style="width: {{ max(2, $row['pct']) }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
                <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">{{ number_format($counts['suspicious']) }} flagged as suspicious &middot; {{ number_format($counts['scored']) }} accounts have a fraud score.</p>
            </div>
        </div>
    </section>

    {{-- Coverage strip --}}
    <section class="grid gap-2 rounded-3xl border border-slate-200 bg-white p-2 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-2xl bg-primary-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-primary-600 shadow-sm"><i class="fas fa-envelope-circle-check text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-700/70">Email verified</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['coverage']['email_pct'], 1) }}% <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($counts['email_verified']) }} accounts</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-sky-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-sky-600 shadow-sm"><i class="fas fa-phone text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-sky-700/70">Phone on file</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['coverage']['phone_pct'], 1) }}% <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($counts['phone']) }} accounts</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-indigo-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm"><i class="fas fa-id-badge text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700/70">Profile complete</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['coverage']['profile_pct'], 1) }}% <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($counts['profile']) }} accounts</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-rose-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-rose-600 shadow-sm"><i class="fas fa-triangle-exclamation text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-700/70">Under watch</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($insights['coverage']['watch_pct'], 1) }}% <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($counts['suspicious']) }} flagged</span></p>
            </div>
        </div>
    </section>

    {{-- User directory --}}
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-slate-900">User directory</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ number_format($users->total()) }} results</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $sortLabels[$sort] }}
                        @if($users->hasPages())
                            &middot; page {{ $users->currentPage() }} of {{ $users->lastPage() }}
                        @endif
                        @if($hasFilters)
                            &middot; filtered
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if(!$currentUser->isModerator())
                        <a href="{{ route('admin.roles') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                            <i class="fas fa-user-tag"></i> Roles
                        </a>
                        <a href="{{ route('admin.permissions') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                            <i class="fas fa-key"></i> Permissions
                        </a>
                    @endif
                    <a href="{{ $refreshUrl }}" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                        <i class="fas fa-rotate-right"></i> Refresh
                    </a>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-1.5 rounded-2xl bg-slate-100/80 p-1">
                @foreach($roleTabs as $tab)
                    @php $isActive = $filters['role'] === $tab['key']; @endphp
                    <a href="{{ $roleTabUrl($tab['key']) }}"
                       aria-current="{{ $isActive ? 'page' : 'false' }}"
                       class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold transition {{ $isActive ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:bg-white/60 hover:text-slate-800' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $tab['dot'] }}"></span>
                        {{ $tab['label'] }}
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $isActive ? 'bg-slate-100 text-slate-600' : 'bg-white/70 text-slate-400' }}">{{ number_format($tab['count']) }}</span>
                    </a>
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Status</span>
                @foreach($statusTabs as $tab)
                    @php $isActive = $filters['status'] === $tab['key']; @endphp
                    <a href="{{ $statusTabUrl($tab['key']) }}"
                       aria-current="{{ $isActive ? 'page' : 'false' }}"
                       class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold transition {{ $isActive ? 'bg-slate-900 text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-600 hover:border-primary-200 hover:text-primary-700' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $tab['dot'] }}"></span>
                        {{ $tab['label'] }}
                        <span class="rounded-full px-1.5 text-[10px] {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($tab['count']) }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-5">
            <form method="GET" action="{{ route('admin.users') }}" class="space-y-3">
                <input type="hidden" name="role" value="{{ $filters['role'] }}">
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
                    <label class="relative block flex-1">
                        <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                        <input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Search name, email, phone or user ID..." aria-label="Search users" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </label>
                    <div class="flex flex-wrap items-center gap-2">
                        <select name="verification" aria-label="Filter by verification" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            <option value="">All verification</option>
                            @foreach($verificationLabels as $key => $label)
                                <option value="{{ $key }}" {{ $filters['verification'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="risk" aria-label="Filter by fraud risk" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            <option value="">All risk levels</option>
                            @foreach($riskLabels as $key => $label)
                                <option value="{{ $key }}" {{ $filters['risk'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="sort" aria-label="Sort users" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            @foreach($sortLabels as $key => $label)
                                <option value="{{ $key }}" {{ $sort === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-primary-800">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        @if($hasFilters)
                            <a href="{{ $resetUrl }}" class="inline-flex items-center gap-1.5 rounded-xl px-2.5 py-2.5 text-xs font-semibold text-slate-500 transition hover:text-slate-800">
                                <i class="fas fa-xmark"></i> Clear
                            </a>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-1.5">
                        @if($hasFilters)
                            @foreach($activeFilters as $filter)
                                <a href="{{ $filterUrl([$filter['remove']]) }}" class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">
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
                                <option value="{{ $option }}" {{ (int) $filters['per_page'] === $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px]">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">User</th>
                        <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Role</th>
                        <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Status</th>
                        <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Verification</th>
                        <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Activity</th>
                        <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Fraud risk</th>
                        <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Joined</th>
                        <th scope="col" class="px-3 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        @php
                            $roleName = $user->role?->name ?? 'user';
                            $roleClass = $roleClasses[$roleName] ?? $roleClasses['user'];
                            $status = $user->status ?? 'active';
                            $statusClass = $statusClasses[$status] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
                            $fraudProfile = $user->fraudProfile;
                            $fraudScore = $fraudProfile ? (int) $fraudProfile->fraud_score : 0;
                            $canManageThisUser = $canManageUsers && $currentUser->canManageUser($user);
                            $canAssignThisUser = $canAssignModerator && $currentUser->canManageUser($user);
                        @endphp
                        <tr class="group transition hover:bg-slate-50/80">
                            <td class="px-3 py-3.5">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-xs font-bold text-primary-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="max-w-[220px] truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                        <p class="max-w-[220px] truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[10px] text-slate-400">
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500">#{{ $user->id }}</span>
                                            @if($user->phone)
                                                <span><i class="fas fa-phone text-[8px]"></i> {{ $user->phone }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                @if($roleName === 'super_admin')
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $roleClass }}">
                                        <i class="fas fa-crown text-[9px]"></i> Super admin
                                    </span>
                                @elseif($canAssignThisUser)
                                    <form method="POST" action="{{ route('admin.users.assign-role', $user->id) }}" class="inline-flex items-center gap-1" onsubmit="return confirm('Change role for {{ $user->name }}?');">
                                        @csrf
                                        <select name="role_id" onchange="this.form.submit()" aria-label="Role for {{ $user->name }}" class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] font-medium text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                            @foreach($roles as $r)
                                                <option value="{{ $r->id }}" {{ $r->name === $roleName ? 'selected' : '' }}>{{ $r->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    @if($user->role && $user->role->permissions->count() > 0)
                                        <div class="relative mt-1 inline-block group/tip">
                                            <i class="fas fa-circle-info text-[10px] text-slate-400"></i>
                                            <div class="pointer-events-none absolute bottom-full left-0 z-20 mb-1 hidden w-56 rounded-xl bg-slate-900 p-2.5 text-[10px] leading-relaxed text-white shadow-xl group-hover/tip:block">
                                                <p class="mb-1 font-semibold">{{ $user->role->display_name }} permissions</p>
                                                @foreach($user->role->permissions as $perm)
                                                    <span class="block text-slate-300">{{ $perm->display_name }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $roleClass }}">
                                        {{ $user->role?->display_name ?? 'User' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $statusClass }}">
                                    <i class="fas {{ $statusIcons[$status] ?? 'fa-circle' }} text-[9px]"></i>
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="flex flex-col gap-1">
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium {{ $user->email_verified_at ? 'text-emerald-600' : 'text-slate-400' }}">
                                        <i class="fas {{ $user->email_verified_at ? 'fa-circle-check' : 'fa-circle-xmark' }} text-[9px]"></i>
                                        {{ $user->email_verified_at ? 'Email verified' : 'Email unverified' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium {{ $user->phone ? 'text-sky-600' : 'text-slate-400' }}">
                                        <i class="fas {{ $user->phone ? 'fa-phone' : 'fa-phone-slash' }} text-[9px]"></i>
                                        {{ $user->phone ? 'Phone on file' : 'No phone' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3.5">
                                <p class="text-sm font-semibold text-slate-800">{{ number_format($user->total_xp ?? 0) }} <span class="text-[10px] font-normal text-slate-400">XP</span></p>
                                <p class="mt-0.5 text-[10px] text-slate-400">{{ number_format($user->reports_count ?? 0) }} report{{ (int) ($user->reports_count ?? 0) === 1 ? '' : 's' }}</p>
                            </td>
                            <td class="px-3 py-3.5">
                                @if($fraudScore > 0)
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $fraudScore >= 80 ? 'bg-rose-50 text-rose-700 ring-rose-200' : ($fraudScore >= 50 ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-slate-100 text-slate-600 ring-slate-200') }}">
                                        <i class="fas fa-shield-halved text-[9px]"></i> {{ $fraudScore }}
                                    </span>
                                    @if($fraudProfile->is_suspicious)
                                        <p class="mt-1 text-[10px] font-semibold text-rose-600"><i class="fas fa-flag text-[9px]"></i> Suspicious</p>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <p class="text-xs font-medium text-slate-700">{{ $user->created_at?->format('M j, Y') }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-400" title="{{ $user->created_at?->format('M j, Y g:i A') }}">{{ $user->created_at?->diffForHumans(short: true) }}</p>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.users.progress', $user) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-sky-600 transition hover:border-sky-200 hover:bg-sky-50" title="View progress">
                                        <i class="fas fa-trophy text-[11px]"></i>
                                    </a>
                                    @if($canManageThisUser)
                                        <form method="POST" action="{{ route('admin.users.toggle-status', $user->id) }}" class="inline" onsubmit="return confirm('Change status for {{ $user->name }}? Current: {{ $status }}. Next: {{ $status === 'active' ? 'suspended' : ($status === 'suspended' ? 'banned' : 'active') }}.');">
                                            @csrf
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 transition {{ $status === 'active' ? 'text-rose-600 hover:border-rose-200 hover:bg-rose-50' : ($status === 'banned' ? 'text-emerald-600 hover:border-emerald-200 hover:bg-emerald-50' : 'text-amber-600 hover:border-amber-200 hover:bg-amber-50') }}" title="Toggle status">
                                                <i class="fas {{ $status === 'active' ? 'fa-ban' : ($status === 'banned' ? 'fa-check' : 'fa-forward-step') }} text-[11px]"></i>
                                            </button>
                                        </form>
                                    @endif

                                    @if($roleName === 'super_admin')
                                        <span class="inline-flex h-8 items-center gap-1 rounded-xl bg-rose-50 px-2 text-[10px] font-semibold text-rose-700"><i class="fas fa-crown"></i> Super admin</span>
                                    @elseif($roleName === 'admin')
                                        @if($canAssignThisUser)
                                            <form method="POST" action="{{ route('admin.users.remove-admin', $user->id) }}" class="inline" onsubmit="return confirm('Remove admin privileges from {{ $user->name }}?');">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-orange-600 transition hover:border-orange-200 hover:bg-orange-50" title="Remove admin">
                                                    <i class="fas fa-user text-[11px]"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @elseif($roleName === 'moderator')
                                        @if($canAssignThisUser)
                                            <form method="POST" action="{{ route('admin.users.make-admin', $user->id) }}" class="inline" onsubmit="return confirm('Promote {{ $user->name }} to admin?');">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-indigo-600 transition hover:border-indigo-200 hover:bg-indigo-50" title="Promote to admin">
                                                    <i class="fas fa-crown text-[11px]"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.remove-moderator', $user->id) }}" class="inline" onsubmit="return confirm('Remove moderator privileges from {{ $user->name }}?');">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-amber-700 transition hover:border-amber-200 hover:bg-amber-50" title="Remove moderator">
                                                    <i class="fas fa-user-slash text-[11px]"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        @if($canAssignThisUser)
                                            <form method="POST" action="{{ route('admin.users.make-admin', $user->id) }}" class="inline" onsubmit="return confirm('Promote {{ $user->name }} to admin?');">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-indigo-600 transition hover:border-indigo-200 hover:bg-indigo-50" title="Promote to admin">
                                                    <i class="fas fa-crown text-[11px]"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.make-moderator', $user->id) }}" class="inline" onsubmit="return confirm('Promote {{ $user->name }} to moderator?');">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 text-amber-700 transition hover:border-amber-200 hover:bg-amber-50" title="Promote to moderator">
                                                    <i class="fas fa-user-shield text-[11px]"></i>
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-14 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    @if($counts['total'] === 0)
                                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                                            <i class="fas fa-users text-xl"></i>
                                        </span>
                                        <p class="mt-3 text-sm font-semibold text-slate-800">No users yet</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-400">Accounts will appear here as soon as people sign up.</p>
                                    @else
                                        <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-300">
                                            <i class="fas fa-user-magnifying-glass text-xl"></i>
                                        </span>
                                        <p class="mt-3 text-sm font-semibold text-slate-800">No users match this view</p>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-400">Try a different role, status or clear one of the active filters.</p>
                                        @if($hasFilters)
                                            <a href="{{ $resetUrl }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-slate-800">
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

        <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">
                Showing
                <span class="font-semibold text-slate-700">{{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }}</span>
                of <span class="font-semibold text-slate-700">{{ number_format($users->total()) }}</span> users
            </p>
            @if($users->hasPages())
                <nav class="flex items-center gap-1" role="navigation" aria-label="User pagination">
                    @if($users->onFirstPage())
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true"><i class="fas fa-chevron-left text-[10px]"></i></span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" aria-label="Previous page"><i class="fas fa-chevron-left text-[10px]"></i></a>
                    @endif

                    @php
                        $currentPage = $users->currentPage();
                        $lastPage = $users->lastPage();
                        $windowStart = max(1, $currentPage - 2);
                        $windowEnd = min($lastPage, $currentPage + 2);
                    @endphp

                    @if($windowStart > 1)
                        <a href="{{ $users->url(1) }}" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">1</a>
                        @if($windowStart > 2)
                            <span class="px-1 text-xs text-slate-400">&hellip;</span>
                        @endif
                    @endif

                    @foreach($users->getUrlRange($windowStart, $windowEnd) as $pageNumber => $pageUrl)
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
                        <a href="{{ $users->url($lastPage) }}" class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">{{ $lastPage }}</a>
                    @endif

                    @if($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" aria-label="Next page"><i class="fas fa-chevron-right text-[10px]"></i></a>
                    @else
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-300" aria-disabled="true"><i class="fas fa-chevron-right text-[10px]"></i></span>
                    @endif
                </nav>
            @endif
        </div>
    </section>

    {{-- Moderator Permissions (Read-only display) --}}
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">Moderator permissions</h2>
                <p class="mt-1 text-xs text-slate-500">Current permissions for each moderator based on their assigned role.</p>
            </div>
            @if($canManageRoles)
                <a href="{{ route('admin.roles') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                    <i class="fas fa-user-tag"></i> Edit roles
                </a>
            @endif
        </div>

        <div class="mt-4 flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-800">
            <i class="fas fa-circle-info mt-0.5"></i>
            <span>Moderator permissions are controlled via the role assigned to each user.@if($canManageRoles) To change permissions, go to <a href="{{ route('admin.roles') }}" class="font-semibold underline hover:text-amber-900">Roles</a> and edit the moderator role.@else Permissions are managed by an administrator.@endif</span>
        </div>

        <div class="mt-4 grid gap-3 xl:grid-cols-2">
            @forelse($moderators as $mod)
                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-2xl bg-amber-500 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($mod->name, 0, 1)) }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ $mod->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $mod->email }}</p>
                            </div>
                        </div>
                        <span class="flex-shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-700">Moderator</span>
                    </div>

                    <div class="mt-3">
                        <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Role: {{ $mod->role?->display_name ?? $mod->role?->name ?? 'None' }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @php $modPerms = $mod->role?->permissions ?? collect(); @endphp
                            @forelse($modPerms as $perm)
                                <span class="inline-flex items-center gap-1 rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-[10px] font-medium text-primary-700">
                                    <i class="fas fa-circle-check text-[9px] text-primary-400"></i>
                                    {{ $perm->display_name }}
                                </span>
                            @empty
                                <span class="text-xs text-slate-400">No permissions assigned</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            @empty
                <div class="xl:col-span-2">
                    <div class="flex flex-col items-center rounded-2xl bg-slate-50 px-4 py-10 text-center">
                        <i class="fas fa-user-shield mb-2 text-2xl text-slate-300"></i>
                        <p class="text-sm font-semibold text-slate-700">No moderators found</p>
                        <p class="mt-1 text-xs text-slate-400">Promote a user to moderator to assign permissions.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var trend = @json($insights['trend']);

    function showChartFallback() {
        var el = document.getElementById('usersTrendFallback');
        if (el) {
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
    }

    function destroyUserCharts() {
        if (!window.__usersCharts) {
            window.__usersCharts = {};
            return;
        }
        Object.keys(window.__usersCharts).forEach(function (key) {
            try { window.__usersCharts[key].destroy(); } catch (e) {}
        });
        window.__usersCharts = {};
    }

    function initUserChart() {
        if (!window.__usersCharts) window.__usersCharts = {};
        if (!window.Chart) {
            showChartFallback();
            return;
        }

        var canvas = document.getElementById('usersTrendChart');
        if (!canvas || window.__usersCharts.trend) return;

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';

        window.__usersCharts.trend = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: trend.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'New registrations',
                        data: trend.signups,
                        backgroundColor: 'rgba(15, 118, 110, 0.72)',
                        hoverBackgroundColor: 'rgba(15, 118, 110, 0.95)',
                        borderRadius: 4,
                        maxBarThickness: 14,
                        order: 2
                    },
                    {
                        type: 'line',
                        label: 'Email verified',
                        data: trend.verifications,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14, 165, 233, 0.10)',
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
        var existing = document.querySelector('script[data-users-chartjs]');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            existing.addEventListener('error', showChartFallback, { once: true });
            return;
        }
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.async = true;
        script.setAttribute('data-users-chartjs', '1');
        script.addEventListener('load', callback, { once: true });
        script.addEventListener('error', showChartFallback, { once: true });
        document.head.appendChild(script);
    }

    destroyUserCharts();
    ensureChartLibrary(initUserChart);

    var breakdownButtons = Array.prototype.slice.call(document.querySelectorAll('[data-users-breakdown-tab]'));
    breakdownButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-users-breakdown-tab');
            breakdownButtons.forEach(function (other) {
                var active = other === button;
                other.classList.toggle('bg-white', active);
                other.classList.toggle('shadow-sm', active);
                other.classList.toggle('text-slate-900', active);
                other.classList.toggle('text-slate-500', !active);
            });
            document.querySelectorAll('[data-users-breakdown-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-users-breakdown-panel') !== target);
            });
        });
    });
})();
</script>
@endsection
