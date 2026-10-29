@extends('admin.layout')
@section('title', 'Places Management')

@section('content')
@php
    $counts = $insights['counts'];
    $coverage = $insights['coverage'];
    $tabs = [
        ['key' => 'all', 'label' => 'All places', 'count' => $counts['all'], 'dot' => 'bg-slate-400'],
        ['key' => 'active', 'label' => 'Active', 'count' => $counts['active'], 'dot' => 'bg-emerald-500'],
        ['key' => 'pending', 'label' => 'Pending review', 'count' => $counts['pending'], 'dot' => 'bg-amber-500'],
        ['key' => 'verified', 'label' => 'Verified', 'count' => $counts['verified'], 'dot' => 'bg-primary-500'],
        ['key' => 'featured', 'label' => 'Featured', 'count' => $counts['featured'], 'dot' => 'bg-yellow-400'],
        ['key' => 'inactive', 'label' => 'Inactive', 'count' => $counts['inactive'], 'dot' => 'bg-slate-300'],
    ];
    $activeTab = $status;
    $tabUrl = fn (string $key) => route('admin.places', array_merge(
        request()->except(['status', 'page', 'fragment']),
        ['status' => $key]
    ));
    $chipUrl = function (array $except) use ($status) {
        return route('admin.places', array_merge(
            request()->except(array_merge($except, ['page', 'fragment'])),
            ['status' => $status]
        ));
    };
    $clearUrl = route('admin.places');

    $activeFilters = [];
    if (request()->filled('search')) {
        $activeFilters[] = ['label' => 'Search: “'.request('search').'”', 'url' => $chipUrl(['search'])];
    }
    if ($categoryId !== 'all') {
        $categoryLabel = collect($insights['category_options'])->firstWhere('id', (int) $categoryId)['name'] ?? '#'.$categoryId;
        $activeFilters[] = ['label' => 'Category: '.$categoryLabel, 'url' => $chipUrl(['category_id'])];
    }
    if ($district !== '') {
        $activeFilters[] = ['label' => 'District: '.$district, 'url' => $chipUrl(['district'])];
    }
    if ($source !== 'all') {
        $sourceLabel = collect($insights['sources'])->firstWhere('key', $source)['label'] ?? ucfirst(str_replace('_', ' ', $source));
        $activeFilters[] = ['label' => 'Source: '.$sourceLabel, 'url' => $chipUrl(['source'])];
    }
    $hasFilters = count($activeFilters) > 0;
    $perPageOptions = [15, 25, 50, 100];

    $sortUrl = function ($column) use ($sort, $direction) {
        $newDir = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
        return route('admin.places', array_merge(
            request()->except(['sort', 'direction', 'page', 'fragment']),
            ['sort' => $column, 'direction' => $newDir]
        ));
    };
    $sortIcon = function ($column) use ($sort, $direction) {
        if ($sort !== $column) {
            return '<i class="fas fa-sort ml-1 text-[10px] text-slate-300"></i>';
        }
        $icon = $direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
        return '<i class="fas '.$icon.' ml-1 text-[10px] text-primary-600"></i>';
    };
    $sortLabels = [
        'created_at' => ['asc' => 'Oldest first', 'desc' => 'Newest first'],
        'name' => ['asc' => 'Name A-Z', 'desc' => 'Name Z-A'],
        'district' => ['asc' => 'District A-Z', 'desc' => 'District Z-A'],
        'average_rating' => ['asc' => 'Rating low to high', 'desc' => 'Rating high to low'],
        'total_reviews' => ['asc' => 'Fewest reviews', 'desc' => 'Most reviews'],
        'is_featured' => ['asc' => 'Unfeatured first', 'desc' => 'Featured first'],
        'id' => ['asc' => 'Oldest first', 'desc' => 'Newest first'],
    ];
    $sortLabel = $sortLabels[$sort][$direction] ?? ucfirst(str_replace('_', ' ', $sort)).' '.($direction === 'asc' ? 'ascending' : 'descending');

    $categoryCounts = collect($insights['category_options'])->pluck('count', 'id');
    $topCategories = array_slice($insights['categories'], 0, 5);
    $osmSource = collect($insights['sources'])->firstWhere('key', 'osm');
    $osmCount = (int) ($osmSource['value'] ?? 0);

    $placesData = $places->getCollection()->map(function ($place) {
        return [
            'id' => (int) $place->id,
            'name' => (string) $place->name,
            'description' => (string) ($place->description ?? ''),
            'category_id' => (int) $place->category_id,
            'category' => $place->category?->name ?? 'Uncategorised',
            'address' => (string) ($place->address ?? ''),
            'district' => (string) ($place->district ?? ''),
            'latitude' => (float) $place->latitude,
            'longitude' => (float) $place->longitude,
            'phone' => (string) ($place->phone ?? ''),
            'email' => (string) ($place->email ?? ''),
            'website' => (string) ($place->website ?? ''),
            'is_verified' => (bool) $place->is_verified,
            'is_active' => (bool) $place->is_active,
            'is_featured' => (bool) $place->is_featured,
            'source' => (string) ($place->source ?? 'admin'),
            'average_rating' => (float) ($place->average_rating ?? 0),
            'total_reviews' => (int) ($place->total_reviews ?? 0),
            'created_at' => $place->created_at?->format('M j, Y g:i A'),
            'images' => $place->images->map(function ($image) {
                $url = $image->displayUrl();
                return [
                    'id' => (int) $image->id,
                    'url' => $url,
                    'thumb' => $image->displayThumbUrl() ?: $url,
                ];
            })->values()->toArray(),
        ];
    })->values();
@endphp

<div id="adminPlaces" class="space-y-5 p-4 sm:p-5 lg:p-6">
    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 h-56 w-56 rounded-full bg-accent-400/10 blur-3xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-primary-200">Travel directory &middot; Places</p>
                <h1 class="mt-2 text-2xl font-semibold sm:text-3xl">Places command center</h1>
                <p class="mt-1 max-w-xl text-sm leading-relaxed text-primary-100">Curate Nepal&rsquo;s place directory, verify submissions and keep every map pin trustworthy.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-location-dot text-[10px]"></i>{{ number_format($counts['all']) }} places
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-map text-[10px]"></i>{{ number_format($counts['districts']) }} districts
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1">
                        <i class="fas fa-clock text-[10px]"></i>
                        @if($insights['latest_place'])
                            Latest {{ $insights['latest_place'] }}
                        @else
                            No places added yet
                        @endif
                    </span>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-2xl px-3 py-2 text-xs font-semibold {{ $counts['pending'] > 0 ? 'bg-amber-400/15 text-amber-100' : 'bg-emerald-400/15 text-emerald-100' }}">
                        <span class="h-2 w-2 rounded-full {{ $counts['pending'] > 0 ? 'bg-amber-300' : 'bg-emerald-300' }}"></span>
                        {{ $counts['pending'] > 0 ? number_format($counts['pending']).' need review' : number_format($counts['active']).' active places' }}
                    </span>
                    <a href="{{ route('admin.places', request()->except('fragment')) }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20">
                        <i class="fas fa-rotate-right"></i> Refresh
                    </a>
                    @if($canManagePlaces)
                        <a href="#directoryTools" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                            <i class="fas fa-plus"></i> Add place
                        </a>
                        <button type="button" onclick="openCategoryModal()" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20">
                            <i class="fas fa-tags"></i> Categories
                        </button>
                    @endif
                </div>
                <p class="text-xs text-primary-200">Snapshot generated at {{ $insights['generated_at'] }}</p>
            </div>
        </div>
        <div class="relative mt-5 flex flex-wrap items-center gap-1.5 rounded-2xl bg-white/10 p-1.5">
            <a href="{{ route('admin.places') }}" aria-current="page" class="inline-flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-xs font-semibold text-primary-900 shadow-sm">
                <i class="fas fa-database text-[11px]"></i> Database
            </a>
            <a href="{{ route('admin.places.osm') }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold text-primary-100 transition hover:bg-white/10 hover:text-white">
                <i class="fas fa-globe-asia text-[11px]"></i> OSM Live (Nepal)
            </a>
            <a href="{{ route('admin.places.corrections') }}" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold text-primary-100 transition hover:bg-white/10 hover:text-white">
                <i class="fas fa-clipboard-check text-[11px]"></i> Corrections
            </a>
            <span class="ml-auto hidden items-center gap-1.5 px-2 text-[11px] text-primary-200 sm:inline-flex">
                <i class="fas fa-circle-nodes text-[10px]"></i> Directory database
            </span>
        </div>
    </section>

    {{-- KPI cards --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($insights['kpis'] as $kpi)
            @php
                $href = $kpi['status'] ? route('admin.places', array_merge(request()->except(['status', 'page', 'fragment']), ['status' => $kpi['status']])) : null;
            @endphp
            <a @if($href) href="{{ $href }}" @else aria-disabled="true" @endif class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-4 shadow-sm transition {{ $href ? 'hover:border-slate-300 hover:shadow-md' : 'cursor-default' }}">
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
                    <h2 class="text-sm font-semibold text-slate-900">Directory growth</h2>
                    <p class="mt-1 text-xs text-slate-500">Places added and OSM imports over the last 30 days.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-[11px]">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 font-semibold text-primary-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-600"></span>
                        {{ number_format($insights['trend']['created_total']) }} added
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        {{ number_format($insights['trend']['osm_total']) }} OSM
                    </span>
                </div>
            </div>
            <div class="relative mt-4 h-64">
                <canvas id="placesTrendChart" aria-label="Places added and OSM imports over the last 30 days" role="img"></canvas>
                <div id="placesTrendFallback" class="absolute inset-0 hidden items-center justify-center rounded-2xl bg-slate-50 text-xs text-slate-400">
                    Chart library unavailable &mdash; totals are shown above.
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-4 text-[11px] text-slate-500">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-primary-700/70"></span> Places added</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-amber-500"></span> OSM imports</span>
                @if($counts['today'] === 0)
                    <span class="ml-auto text-slate-400">No places added today yet</span>
                @else
                    <span class="ml-auto font-semibold text-slate-600">{{ number_format($counts['today']) }} today</span>
                @endif
            </div>
        </div>

        <div class="flex flex-col rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Composition</h2>
                    <p class="mt-1 text-xs text-slate-500">Across all {{ number_format($counts['all']) }} places.</p>
                </div>
                <div class="flex flex-wrap justify-end gap-0.5 rounded-xl bg-slate-100 p-0.5 text-[10px] font-semibold">
                    <button type="button" data-place-breakdown-tab="categories" class="rounded-lg bg-white px-2 py-1.5 text-slate-900 shadow-sm">Categories</button>
                    <button type="button" data-place-breakdown-tab="districts" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Districts</button>
                    <button type="button" data-place-breakdown-tab="sources" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Sources</button>
                    <button type="button" data-place-breakdown-tab="media" class="rounded-lg px-2 py-1.5 text-slate-500 transition hover:text-slate-800">Media</button>
                </div>
            </div>

            <div data-place-breakdown-panel="categories" class="mt-4 flex-1 space-y-3">
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
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Top {{ count($insights['categories']) }} categories by place count.</p>
                @endif
            </div>

            <div data-place-breakdown-panel="districts" class="mt-4 hidden flex-1 space-y-3">
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
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Top {{ count($insights['districts']) }} districts by place count.</p>
                @endif
            </div>

            <div data-place-breakdown-panel="sources" class="mt-4 hidden flex-1 space-y-3">
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
                    <p class="py-6 text-center text-xs text-slate-400">No source data yet.</p>
                @endforelse
                @if(count($insights['sources']) > 0)
                    <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">Directory composition by import source.</p>
                @endif
            </div>

            <div data-place-breakdown-panel="media" class="mt-4 hidden flex-1 space-y-3">
                @forelse($insights['images'] as $row)
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
                    <p class="py-6 text-center text-xs text-slate-400">No image discovery data yet.</p>
                @endforelse
                <p class="border-t border-slate-100 pt-2 text-[10px] leading-relaxed text-slate-400">
                    {{ number_format($counts['with_images']) }} places have photos &middot; {{ number_format($coverage['photo_pending']) }} awaiting discovery.
                </p>
            </div>
        </div>
    </section>

    {{-- Coverage strip --}}
    <section class="grid gap-2 rounded-3xl border border-slate-200 bg-white p-2 sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex items-center gap-3 rounded-2xl bg-primary-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-primary-600 shadow-sm"><i class="fas fa-location-crosshairs text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-primary-700/70">Map coverage</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($coverage['mapped']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($coverage['active_pct'], 1) }}% active</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-indigo-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm"><i class="fas fa-images text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700/70">Photo coverage</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['with_images']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($coverage['photo_pct'], 2) }}%</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm"><i class="fas fa-circle-check text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700/70">Verification</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($counts['verified']) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($coverage['verified_pct'], 2) }}%</span></p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-2xl bg-amber-50/70 px-3 py-2.5">
            <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-white text-amber-600 shadow-sm"><i class="fas fa-globe-asia text-xs"></i></span>
            <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-700/70">OpenStreetMap</p>
                <p class="text-sm font-semibold text-slate-900">{{ number_format($osmCount) }} <span class="text-xs font-normal text-slate-400">&middot; {{ number_format($coverage['osm_pct'], 1) }}%</span></p>
            </div>
        </div>
    </section>

    {{-- Place directory --}}
    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <form id="bulkForm" method="POST" action="{{ route('admin.places.bulk-delete') }}" data-native class="hidden">
            @csrf
        </form>

        <div id="liveTable">
            <script type="application/json" id="placesPageData">@json($placesData)</script>

            <div class="border-b border-slate-100 p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-900">Place directory</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ number_format($places->total()) }} results</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $sortLabel }}
                            @if($places->hasPages())
                                &middot; page {{ $places->currentPage() }} of {{ $places->lastPage() }}
                            @endif
                            @if($hasFilters)
                                &middot; filtered
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($canManagePlaces)
                            <a href="#directoryTools" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-plus"></i> Add place
                            </a>
                            <button type="button" onclick="openCategoryModal()" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                                <i class="fas fa-tags"></i> Categories
                            </button>
                        @endif
                        <a href="{{ route('admin.places', request()->except('fragment')) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
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
            </div>

            <div class="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-5">
                <form method="GET" action="{{ route('admin.places') }}" class="space-y-3">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <div class="flex flex-col gap-2 xl:flex-row xl:items-center">
                        <label class="relative block flex-1">
                            <i class="fas fa-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, address, district, phone, email..." aria-label="Search places" class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            <select name="category_id" aria-label="Filter by category" class="max-w-[220px] rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="all">All categories</option>
                                @foreach($insights['category_options'] as $category)
                                    <option value="{{ $category['id'] }}" {{ (string) $categoryId === (string) $category['id'] ? 'selected' : '' }}>{{ $category['name'] }} ({{ number_format($category['count']) }})</option>
                                @endforeach
                            </select>
                            <select name="district" aria-label="Filter by district" class="max-w-[220px] rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="">All districts</option>
                                @foreach($insights['district_options'] as $option)
                                    <option value="{{ $option['name'] }}" {{ $district === $option['name'] ? 'selected' : '' }}>{{ $option['name'] }} ({{ number_format($option['count']) }})</option>
                                @endforeach
                            </select>
                            <select name="source" aria-label="Filter by source" class="rounded-xl border border-slate-200 bg-white px-2.5 py-2.5 text-xs font-medium text-slate-600 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                <option value="all">All sources</option>
                                @foreach($insights['sources'] as $option)
                                    <option value="{{ $option['key'] }}" {{ $source === $option['key'] ? 'selected' : '' }}>{{ $option['label'] }} ({{ number_format($option['value']) }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3.5 py-2.5 text-xs font-semibold text-white transition hover:bg-primary-800">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            @if($hasFilters || $status !== 'all' || $sort !== 'created_at' || $direction !== 'desc')
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
                                <span class="text-[11px] text-slate-400">No extra filters applied</span>
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
                <table class="w-full min-w-[980px]">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th scope="col" class="w-10 px-4 py-3 text-left">
                                <input type="checkbox" id="selectAll" onchange="toggleAll(this)" aria-label="Select all places on this page" class="rounded border-slate-300">
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <a href="{{ $sortUrl('id') }}" class="inline-flex items-center hover:text-primary-700">ID {!! $sortIcon('id') !!}</a>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <a href="{{ $sortUrl('name') }}" class="inline-flex items-center hover:text-primary-700">Place {!! $sortIcon('name') !!}</a>
                            </th>
                            <th scope="col" class="hidden px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 xl:table-cell">Category</th>
                            <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <a href="{{ $sortUrl('district') }}" class="inline-flex items-center hover:text-primary-700">District {!! $sortIcon('district') !!}</a>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <a href="{{ $sortUrl('average_rating') }}" class="inline-flex items-center hover:text-primary-700">Rating {!! $sortIcon('average_rating') !!}</a>
                            </th>
                            <th scope="col" class="hidden px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 lg:table-cell">
                                <a href="{{ $sortUrl('total_reviews') }}" class="inline-flex items-center hover:text-primary-700">Reviews {!! $sortIcon('total_reviews') !!}</a>
                            </th>
                            <th scope="col" class="hidden px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 xl:table-cell">
                                <a href="{{ $sortUrl('is_featured') }}" class="inline-flex items-center hover:text-primary-700">Featured {!! $sortIcon('is_featured') !!}</a>
                            </th>
                            <th scope="col" class="px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Status</th>
                            <th scope="col" class="hidden px-3 py-3 text-left text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400 md:table-cell">Added</th>
                            <th scope="col" class="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($places as $place)
                            @php
                                $isPending = !$place->is_verified && !$place->is_active;
                                if ($isPending) {
                                    $statusLabel = 'Pending';
                                    $statusClass = 'bg-amber-50 text-amber-700 ring-amber-200';
                                    $statusDot = 'bg-amber-500';
                                } elseif ($place->is_verified) {
                                    $statusLabel = 'Verified';
                                    $statusClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                                    $statusDot = 'bg-emerald-500';
                                } elseif ($place->is_active) {
                                    $statusLabel = 'Active';
                                    $statusClass = 'bg-sky-50 text-sky-700 ring-sky-200';
                                    $statusDot = 'bg-sky-500';
                                } else {
                                    $statusLabel = 'Inactive';
                                    $statusClass = 'bg-slate-100 text-slate-500 ring-slate-200';
                                    $statusDot = 'bg-slate-400';
                                }
                                $cover = $place->images->first();
                                $coverUrl = $cover?->displayThumbUrl() ?: $cover?->displayUrl();
                            @endphp
                            <tr class="group transition hover:bg-slate-50/80 {{ $isPending ? 'bg-amber-50/20' : '' }}">
                                <td class="px-4 py-3.5">
                                    <input type="checkbox" name="ids[]" value="{{ $place->id }}" form="bulkForm" class="place-checkbox rounded border-slate-300" onchange="updateBulkBar()" aria-label="Select {{ $place->name }}">
                                </td>
                                <td class="px-3 py-3.5 text-xs font-medium text-slate-400">#{{ $place->id }}</td>
                                <td class="px-3 py-3.5">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100 text-slate-400">
                                            @if($coverUrl)
                                                <img src="{{ $coverUrl }}" alt="" loading="lazy" class="h-full w-full object-cover">
                                            @else
                                                <i class="fas fa-image text-xs"></i>
                                            @endif
                                        </div>
                                        <div class="min-w-0 max-w-[320px]">
                                            <a href="{{ route('admin.places.view', $place->id) }}" class="block truncate text-sm font-semibold text-slate-900 transition group-hover:text-primary-700">{{ $place->name }}</a>
                                            @if($place->address)
                                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $place->address }}</p>
                                            @endif
                                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px] text-slate-400">
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500">{{ $place->category?->name ?? 'Uncategorised' }}</span>
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500">{{ strtoupper($place->source ?? 'admin') }}</span>
                                                @if($place->images->count() > 0)
                                                    <span class="inline-flex items-center gap-1"><i class="fas fa-camera text-[9px]"></i>{{ $place->images->count() }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-3.5 xl:table-cell">
                                    <span class="inline-flex max-w-[150px] truncate rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600">{{ $place->category?->name ?? 'Uncategorised' }}</span>
                                </td>
                                <td class="px-3 py-3.5 text-sm text-slate-600">{{ $place->district ?: '—' }}</td>
                                <td class="px-3 py-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <i class="fas fa-star text-[10px] text-amber-400"></i>
                                        <span class="text-sm font-medium text-slate-700">{{ number_format((float) ($place->average_rating ?? 0), 1) }}</span>
                                    </div>
                                </td>
                                <td class="hidden px-3 py-3.5 text-sm text-slate-600 lg:table-cell">{{ number_format((int) ($place->total_reviews ?? 0)) }}</td>
                                <td class="hidden px-3 py-3.5 xl:table-cell">
                                    @if($place->is_featured)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-yellow-50 px-2.5 py-1 text-[11px] font-semibold text-yellow-700 ring-1 ring-inset ring-yellow-200"><i class="fas fa-star text-[9px]"></i> Featured</span>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset {{ $statusClass }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="hidden px-3 py-3.5 md:table-cell">
                                    <p class="text-xs font-medium text-slate-600">{{ $place->created_at?->format('M j, Y') ?? '—' }}</p>
                                    <p class="mt-0.5 text-[10px] text-slate-400">{{ $place->created_at?->diffForHumans(short: true) }}</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-wrap items-center justify-end gap-1">
                                        @if($canManagePlaces && !$place->is_verified)
                                            <form method="POST" action="{{ route('admin.places.approve', $place->id) }}" data-native class="inline" onsubmit="return confirm('Approve and publish this place?')">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100" title="Approve and publish" aria-label="Approve place #{{ $place->id }}">
                                                    <i class="fas fa-check text-xs"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.places.reject', $place->id) }}" data-native class="inline" onsubmit="return confirm('Reject and remove this place? This cannot be undone.')">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100" title="Reject and remove" aria-label="Reject place #{{ $place->id }}">
                                                    <i class="fas fa-xmark text-xs"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" onclick="openReviewsModal({{ $place->id }})" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100" title="View and edit reviews" aria-label="Reviews for place #{{ $place->id }}">
                                            <i class="fas fa-comments text-xs"></i>
                                        </button>
                                        @if($canManagePlaces)
                                            <button type="button" onclick="openEditModal({{ $place->id }})" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700" title="Edit place" aria-label="Edit place #{{ $place->id }}">
                                                <i class="fas fa-pen text-xs"></i>
                                            </button>
                                            <form method="POST" action="{{ route('admin.places.feature', $place->id) }}" data-native class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border transition {{ $place->is_featured ? 'border-yellow-200 bg-yellow-50 text-yellow-600 hover:bg-yellow-100' : 'border-slate-200 bg-white text-slate-500 hover:border-yellow-200 hover:bg-yellow-50 hover:text-yellow-600' }}" title="{{ $place->is_featured ? 'Remove from featured' : 'Feature this place' }}" aria-label="{{ $place->is_featured ? 'Unfeature' : 'Feature' }} place #{{ $place->id }}">
                                                    <i class="fas {{ $place->is_featured ? 'fa-star' : 'fa-star-half-alt' }} text-xs"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.places.delete', $place->id) }}" data-native class="inline" onsubmit="return confirm('Delete this place and its images? This cannot be undone.')">
                                                @csrf
                                                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100" title="Delete place" aria-label="Delete place #{{ $place->id }}">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-16 text-center">
                                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-3xl bg-slate-100 text-slate-400"><i class="fas fa-map-location-dot text-xl"></i></span>
                                    <p class="mt-3 text-sm font-semibold text-slate-700">No places match these filters</p>
                                    <p class="mt-1 text-xs text-slate-500">Try a different tab, district or search term.</p>
                                    <a href="{{ $clearUrl }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-primary-800">
                                        <i class="fas fa-rotate-left"></i> Reset filters
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="bulkBar" class="hidden items-center justify-between gap-3 border-t border-primary-100 bg-primary-50 px-4 py-3 sm:px-5">
                <span class="text-sm font-medium text-primary-700"><span id="selectedCount">0</span> selected</span>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" onclick="clearSelection()" class="rounded-xl px-2.5 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-100">Clear</button>
                    <button type="button" onclick="openBulkEditModal()" class="inline-flex items-center gap-1.5 rounded-xl bg-primary-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-800">
                        <i class="fas fa-pen-to-square"></i> Bulk edit
                    </button>
                    <button type="button" onclick="bulkDelete()" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-rose-700">
                        <i class="fas fa-trash"></i> Delete selected
                    </button>
                </div>
            </div>

            @if($places->total() > 0)
                <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <p class="text-xs text-slate-500">
                        Showing {{ number_format($places->firstItem() ?? 0) }}&ndash;{{ number_format($places->lastItem() ?? 0) }} of {{ number_format($places->total()) }} places
                    </p>
                    @if($places->hasPages())
                        <div>{{ $places->links() }}</div>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Directory tools --}}
    <section id="directoryTools" class="grid gap-3 xl:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Add a place</h2>
                    <p class="mt-1 text-xs text-slate-500">Create a new directory entry and optionally upload photos.</p>
                </div>
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-primary-50 text-primary-600"><i class="fas fa-location-plus text-xs"></i></span>
            </div>
            @if($canManagePlaces)
                <form method="POST" action="{{ route('admin.places.create') }}" enctype="multipart/form-data" data-native class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Description</label>
                        <textarea name="description" rows="2" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">{{ old('description') }}</textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Category</label>
                            <select name="category_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ (string) old('category_id') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">District</label>
                            <input type="text" name="district" value="{{ old('district') }}" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Address</label>
                        <input type="text" name="address" value="{{ old('address') }}" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Latitude</label>
                            <input type="number" step="0.000001" name="latitude" value="{{ old('latitude') }}" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Longitude</label>
                            <input type="number" step="0.000001" name="longitude" value="{{ old('longitude') }}" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Website</label>
                        <input type="url" name="website" value="{{ old('website') }}" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Photos</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800">
                        <i class="fas fa-plus"></i> Add place
                    </button>
                </form>
            @else
                <p class="mt-4 rounded-2xl bg-slate-50 px-3 py-4 text-xs text-slate-500">You do not have permission to add places.</p>
            @endif
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Import from OpenStreetMap</h2>
                    <p class="mt-1 text-xs text-slate-500">Queue a city import; progress appears below and survives refreshes.</p>
                </div>
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-globe-asia text-xs"></i></span>
            </div>
            @if($canManagePlaces)
                <form method="POST" action="{{ route('admin.places.import-osm') }}" data-native class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">City</label>
                        <select name="city" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                            <option value="">All major Nepali cities</option>
                            <option value="Kathmandu">Kathmandu</option>
                            <option value="Pokhara">Pokhara</option>
                            <option value="Bharatpur">Bharatpur</option>
                            <option value="Lalitpur">Lalitpur</option>
                            <option value="Bhaktapur">Bhaktapur</option>
                            <option value="Birgunj">Birgunj</option>
                            <option value="Janakpur">Janakpur</option>
                            <option value="Butwal">Butwal</option>
                            <option value="Biratnagar">Biratnagar</option>
                            <option value="Dharan">Dharan</option>
                            <option value="Nepalgunj">Nepalgunj</option>
                            <option value="Hetauda">Hetauda</option>
                            <option value="Dhangadhi">Dhangadhi</option>
                            <option value="Lumbini">Lumbini</option>
                            <option value="Jomsom">Jomsom</option>
                            <option value="Namche Bazaar">Namche Bazaar</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Radius (km)</label>
                        <input type="number" name="radius" value="10" min="1" max="50" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-100">
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                        <i class="fas fa-download"></i> Import from OSM
                    </button>
                </form>

                <div id="osmImportProgress" class="mt-4 hidden rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span id="osmProgressLabel" class="text-sm font-medium text-slate-700"></span>
                        <span id="osmProgressCounts" class="text-xs text-slate-500"></span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                        <div id="osmProgressBar" class="h-2 rounded-full bg-emerald-600 transition-all duration-500" style="width:0%"></div>
                    </div>
                    <div id="osmProgressMeta" class="mt-2 text-xs text-slate-500"></div>
                    <pre id="osmProgressOutput" class="mt-2 hidden max-h-40 overflow-auto whitespace-pre-wrap rounded-xl border border-slate-200 bg-white p-2 text-[11px] leading-relaxed text-slate-600"></pre>
                </div>
            @else
                <p class="mt-4 rounded-2xl bg-slate-50 px-3 py-4 text-xs text-slate-500">You do not have permission to import places.</p>
            @endif
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Categories</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(count($categories)) }} categories organise the directory.</p>
                </div>
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><i class="fas fa-tags text-xs"></i></span>
            </div>
            @if($canManagePlaces)
                <form method="POST" action="{{ route('admin.places.categories') }}" data-native class="mt-4 flex items-center gap-2">
                    @csrf
                    <input type="text" name="name" placeholder="New category name" required maxlength="255" class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    <button type="submit" class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-700 text-white transition hover:bg-primary-800" title="Add category" aria-label="Add category">
                        <i class="fas fa-plus text-xs"></i>
                    </button>
                </form>
            @endif
            <div class="mt-4 space-y-2">
                @forelse($topCategories as $category)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2">
                        <span class="min-w-0 truncate text-xs font-medium text-slate-700">{{ $category['label'] }}</span>
                        <span class="flex-shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500">{{ number_format($category['value']) }}</span>
                    </div>
                @empty
                    <p class="rounded-xl bg-slate-50 px-3 py-4 text-center text-xs text-slate-400">No categories yet.</p>
                @endforelse
            </div>
            @if($canManagePlaces)
                <button type="button" onclick="openCategoryModal()" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-700">
                    <i class="fas fa-gear"></i> Manage all categories
                </button>
            @endif
        </div>
    </section>
</div>

{{-- Edit place modal --}}
<div id="editPlaceModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="editPlaceTitle">
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeEditModal()"></div>
        <div class="relative w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 id="editPlaceTitle" class="text-sm font-semibold text-slate-900">Edit place</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Update directory details, visibility and photos.</p>
                </div>
                <button type="button" onclick="closeEditModal()" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close edit modal">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <form id="editPlaceForm" method="POST" enctype="multipart/form-data" class="max-h-[75vh] overflow-y-auto p-5">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Name</label>
                        <input type="text" name="name" id="edit_name" required maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Description</label>
                        <textarea name="description" id="edit_description" rows="3" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Category</label>
                        <select name="category_id" id="edit_category_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">District</label>
                        <input type="text" name="district" id="edit_district" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Address</label>
                        <input type="text" name="address" id="edit_address" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Latitude</label>
                        <input type="number" step="0.000001" name="latitude" id="edit_latitude" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Longitude</label>
                        <input type="number" step="0.000001" name="longitude" id="edit_longitude" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Phone</label>
                        <input type="text" name="phone" id="edit_phone" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                        <input type="email" name="email" id="edit_email" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Website</label>
                        <input type="url" name="website" id="edit_website" maxlength="100" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    </div>
                    <div class="sm:col-span-2 flex flex-wrap items-center gap-4 rounded-2xl bg-slate-50 px-4 py-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            {{-- Hidden 0 first so unchecking submits an explicit false. --}}
                            <input type="hidden" name="is_verified" value="0">
                            <input type="checkbox" name="is_verified" id="edit_is_verified" value="1" class="rounded border-slate-300 text-primary-600">
                            Verified
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded border-slate-300 text-primary-600">
                            Active
                        </label>
                        <span class="ml-auto text-[10px] text-slate-400">Verified appears as the public trust badge.</span>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Add images</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="w-full text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary-700 hover:file:bg-primary-100">
                    </div>
                    <div class="sm:col-span-2" id="existingImages"></div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeEditModal()" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-200">Cancel</button>
                    <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reviews modal --}}
<div id="reviewsModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="reviewsTitle">
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeReviewsModal()"></div>
        <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div class="min-w-0">
                    <h3 id="reviewsTitle" class="text-sm font-semibold text-slate-900">Reviews</h3>
                    <p id="reviewsPlaceName" class="mt-0.5 truncate text-xs text-slate-500"></p>
                </div>
                <button type="button" onclick="closeReviewsModal()" class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close reviews modal">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <div id="reviewsList" class="overflow-y-auto p-5"></div>
        </div>
    </div>
</div>

{{-- Bulk edit modal --}}
<div id="bulkEditModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="bulkEditTitle">
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeBulkEditModal()"></div>
        <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 id="bulkEditTitle" class="text-sm font-semibold text-slate-900">Bulk edit places</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Changes apply to all <span id="bulkEditCount">0</span> selected places.</p>
                </div>
                <button type="button" onclick="closeBulkEditModal()" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close bulk edit modal">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <form id="bulkEditForm" method="POST" action="{{ route('admin.places.bulk-update') }}" data-native class="p-5">
                @csrf
                <div id="bulkEditIds"></div>                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Category</label>
                        <select name="category_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                            <option value="">— No change —</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2 rounded-2xl bg-slate-50 p-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_verified" value="1" class="rounded border-slate-300 text-primary-600">
                            Mark verified
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-primary-600">
                            Set active
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="is_featured" value="1" class="rounded border-slate-300 text-primary-600">
                            Set featured
                        </label>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="closeBulkEditModal()" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-200">Cancel</button>
                    <button type="submit" class="rounded-xl bg-primary-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-800">Apply to selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Category management modal --}}
<div id="categoryModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="categoryTitle">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCategoryModal()"></div>
    <div class="absolute left-1/2 top-1/2 flex max-h-[85vh] w-[calc(100%-2rem)] max-w-2xl -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <h3 id="categoryTitle" class="text-sm font-semibold text-slate-900">Manage categories</h3>
                <p class="mt-0.5 text-xs text-slate-500">{{ number_format(count($categories)) }} categories · rename, add or remove unused ones.</p>
            </div>
            <button type="button" onclick="closeCategoryModal()" class="inline-flex h-8 w-8 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close category modal">
                <i class="fas fa-xmark"></i>
            </button>
        </div>
        <div class="overflow-y-auto p-5">
            @if($canManagePlaces)
                <form method="POST" action="{{ route('admin.places.categories') }}" data-native class="mb-5 grid gap-2 sm:grid-cols-[1fr_10rem_auto]">
                    @csrf
                    <input type="text" name="name" placeholder="New category name" required maxlength="255" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    <input type="text" name="icon" placeholder="Icon name (optional)" maxlength="100" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-primary-400 focus:ring-2 focus:ring-primary-100">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-800">
                        <i class="fas fa-plus"></i> Add
                    </button>
                </form>
            @endif
            <div class="space-y-2">
                @foreach($categories as $cat)
                    @php $catCount = (int) ($categoryCounts[$cat->id] ?? 0); @endphp
                    <div class="group flex items-center gap-2 rounded-2xl bg-slate-50 px-3 py-2">
                        @if($canManagePlaces)
                            <form method="POST" action="{{ route('admin.places.categories') }}" data-native class="flex min-w-0 flex-1 items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="id" value="{{ $cat->id }}">
                                <input type="text" name="name" value="{{ $cat->name }}" required maxlength="255" class="min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 py-1 text-sm text-slate-700 outline-none transition hover:border-slate-300 focus:border-primary-400 focus:bg-white focus:ring-1 focus:ring-primary-200">
                                <input type="text" name="icon" value="{{ $cat->icon }}" placeholder="icon" maxlength="100" class="hidden w-24 rounded-lg border border-transparent bg-transparent px-2 py-1 text-xs text-slate-500 outline-none transition hover:border-slate-300 focus:border-primary-400 focus:bg-white focus:ring-1 focus:ring-primary-200 sm:block">
                                <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-primary-600 opacity-0 transition hover:bg-primary-50 group-hover:opacity-100" title="Save category">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                            </form>
                        @else
                            <span class="min-w-0 flex-1 truncate px-2 text-sm text-slate-700">{{ $cat->name }}</span>
                        @endif
                        <span class="flex-shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-500">{{ number_format($catCount) }}</span>
                        @if($canManagePlaces)
                            @if($catCount === 0)
                                <form method="POST" action="{{ route('admin.places.categories') }}" data-native onsubmit="return confirm('Delete this unused category?')">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="id" value="{{ $cat->id }}">
                                    <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-rose-500 opacity-0 transition hover:bg-rose-50 group-hover:opacity-100" title="Delete category">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </form>
                            @else
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg text-slate-300" title="Move places out of this category before deleting">
                                    <i class="fas fa-lock text-[10px]"></i>
                                </span>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var trend = @json($insights['trend']);

    function showTrendFallback() {
        var el = document.getElementById('placesTrendFallback');
        if (el) {
            el.classList.remove('hidden');
            el.classList.add('flex');
        }
    }

    function destroyPlaceCharts() {
        if (!window.__placesCharts) {
            window.__placesCharts = {};
            return;
        }
        Object.keys(window.__placesCharts).forEach(function (key) {
            try { window.__placesCharts[key].destroy(); } catch (e) {}
        });
        window.__placesCharts = {};
    }

    function initPlaceCharts() {
        if (!window.__placesCharts) window.__placesCharts = {};

        if (!window.Chart) {
            showTrendFallback();
            return;
        }

        Chart.defaults.font.family = 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        Chart.defaults.color = '#64748b';

        var canvas = document.getElementById('placesTrendChart');
        if (!canvas || window.__placesCharts.trend) return;

        var hasData = trend.created.some(function (value) { return Number(value) > 0; });
        if (!hasData) {
            showTrendFallback();
            return;
        }

        window.__placesCharts.trend = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: trend.labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Places added',
                        data: trend.created,
                        backgroundColor: 'rgba(15, 118, 110, 0.72)',
                        hoverBackgroundColor: 'rgba(15, 118, 110, 0.95)',
                        borderRadius: 4,
                        maxBarThickness: 14,
                        order: 2
                    },
                    {
                        type: 'line',
                        label: 'OSM imports',
                        data: trend.osm,
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
                        callbacks: {
                            label: function (context) {
                                return ' ' + context.dataset.label + ': ' + Number(context.parsed.y || 0).toLocaleString();
                            }
                        }
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

        var existing = document.querySelector('script[data-places-chartjs], script[data-alert-chartjs], script[data-report-chartjs]');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            existing.addEventListener('error', showTrendFallback, { once: true });
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.async = true;
        script.setAttribute('data-places-chartjs', '1');
        script.addEventListener('load', callback, { once: true });
        script.addEventListener('error', showTrendFallback, { once: true });
        document.head.appendChild(script);
    }

    destroyPlaceCharts();
    ensureChartLibrary(initPlaceCharts);

    var breakdownButtons = Array.prototype.slice.call(document.querySelectorAll('[data-place-breakdown-tab]'));
    breakdownButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var target = button.getAttribute('data-place-breakdown-tab');
            breakdownButtons.forEach(function (other) {
                var active = other === button;
                other.classList.toggle('bg-white', active);
                other.classList.toggle('shadow-sm', active);
                other.classList.toggle('text-slate-900', active);
                other.classList.toggle('text-slate-500', !active);
            });
            document.querySelectorAll('[data-place-breakdown-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-place-breakdown-panel') !== target);
            });
            if (target === 'categories' && window.__placesCharts && window.__placesCharts.trend) {
                window.__placesCharts.trend.resize();
            }
        });
    });
})();
</script>

<script>
(function () {
    'use strict';

    var places = [];

    // The list is replaced via AJAX (pagination, sorting, filters) without
    // re-running this script, so row data is embedded inside #liveTable and
    // read fresh each time a modal needs it.
    function currentPlaces() {
        var node = document.getElementById('placesPageData');
        if (!node) return places;
        try {
            var parsed = JSON.parse(node.textContent || '[]');
            if (Array.isArray(parsed)) places = parsed;
        } catch (e) {
            // Keep the last known payload if the fragment is malformed.
        }
        return places;
    }
    places = currentPlaces();
    var adminPrefix = window.adminPrefix || 'admin';
    var csrfToken = @json(csrf_token());
    var baseUrl = @json(url('/'));

    function el(id) {
        return document.getElementById(id);
    }

    function placeUrl(path) {
        return baseUrl.replace(/\/$/, '') + '/' + adminPrefix + path;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    // ---------------------------------------------------------------
    // Reviews modal
    // ---------------------------------------------------------------
    window.openReviewsModal = function (placeId) {
        fetch(placeUrl('/places/' + placeId + '/reviews'), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) return;
                el('reviewsPlaceName').textContent = data.place.name;
                renderReviews(data.reviews || []);
                var modal = el('reviewsModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            })
            .catch(function () { alert('Failed to load reviews'); });
    };

    window.closeReviewsModal = function () {
        var modal = el('reviewsModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    };

    function renderReviews(reviews) {
        var list = el('reviewsList');
        if (!list) return;

        if (!reviews.length) {
            list.innerHTML = '<div class="py-10 text-center"><i class="fas fa-comment-slash text-3xl text-slate-300"></i><p class="mt-3 text-sm font-medium text-slate-600">No reviews yet</p><p class="mt-1 text-xs text-slate-400">Reviews submitted by travellers will appear here.</p></div>';
            return;
        }

        list.innerHTML = reviews.map(function (review) {
            var rating = Math.max(0, Math.min(5, Number(review.rating) || 0));
            var stars = '<i class="fas fa-star"></i>'.repeat(rating) + '<i class="far fa-star"></i>'.repeat(5 - rating);
            var statusBadge = review.status === 'approved'
                ? '<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">Approved</span>'
                : '<span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-200">Rejected</span>';
            var ratingOptions = [1, 2, 3, 4, 5].map(function (value) {
                return '<option value="' + value + '"' + (value === rating ? ' selected' : '') + '>' + value + ' star' + (value > 1 ? 's' : '') + '</option>';
            }).join('');

            return '' +
                '<article class="mb-3 rounded-2xl border border-slate-200 bg-white p-4">' +
                    '<div class="flex flex-wrap items-center justify-between gap-2">' +
                        '<div class="flex flex-wrap items-center gap-2">' +
                            '<span class="text-xs text-amber-400">' + stars + '</span>' +
                            '<span class="text-xs font-semibold text-slate-700">' + escapeHtml(review.user_name) + '</span>' +
                            '<span class="text-[10px] text-slate-400">' + escapeHtml(review.created_at || '') + '</span>' +
                        '</div>' +
                        statusBadge +
                    '</div>' +
                    '<p class="mt-2 text-sm font-semibold text-slate-800">' + escapeHtml(review.title) + '</p>' +
                    '<p class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-600">' + escapeHtml(review.description) + '</p>' +
                    '<div class="mt-3 flex flex-wrap gap-2">' +
                        '<button type="button" onclick="toggleReviewEdit(' + review.id + ')" class="inline-flex items-center gap-1.5 rounded-xl bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-100"><i class="fas fa-pen"></i> Edit</button>' +
                        '<form method="POST" action="' + placeUrl('/places/reviews/' + review.id + '/delete') + '" onsubmit="return confirm(\'Delete this review?\')">' +
                            '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                            '<button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100"><i class="fas fa-trash"></i> Delete</button>' +
                        '</form>' +
                    '</div>' +
                    '<div id="review-edit-' + review.id + '" class="mt-4 hidden border-t border-slate-100 pt-4">' +
                        '<form method="POST" action="' + placeUrl('/places/reviews/' + review.id + '/update') + '">' +
                            '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                            '<div class="grid gap-3 sm:grid-cols-2">' +
                                '<div>' +
                                    '<label class="mb-1 block text-xs font-semibold text-slate-600">Rating</label>' +
                                    '<select name="rating" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100">' + ratingOptions + '</select>' +
                                '</div>' +
                                '<div>' +
                                    '<label class="mb-1 block text-xs font-semibold text-slate-600">Status</label>' +
                                    '<select name="status" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100">' +
                                        '<option value="approved"' + (review.status === 'approved' ? ' selected' : '') + '>Approved (visible)</option>' +
                                        '<option value="rejected"' + (review.status === 'rejected' ? ' selected' : '') + '>Rejected (hidden)</option>' +
                                    '</select>' +
                                '</div>' +
                                '<div class="sm:col-span-2">' +
                                    '<label class="mb-1 block text-xs font-semibold text-slate-600">Title</label>' +
                                    '<input type="text" name="title" value="' + escapeHtml(review.title) + '" required maxlength="255" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100">' +
                                '</div>' +
                                '<div class="sm:col-span-2">' +
                                    '<label class="mb-1 block text-xs font-semibold text-slate-600">Description</label>' +
                                    '<textarea name="description" rows="3" required maxlength="5000" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-100">' + escapeHtml(review.description) + '</textarea>' +
                                '</div>' +
                            '</div>' +
                            '<div class="mt-3 flex justify-end gap-2">' +
                                '<button type="button" onclick="toggleReviewEdit(' + review.id + ')" class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200">Cancel</button>' +
                                '<button type="submit" class="rounded-xl bg-primary-700 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-primary-800">Save changes</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                '</article>';
        }).join('');
    }

    window.toggleReviewEdit = function (reviewId) {
        var node = el('review-edit-' + reviewId);
        if (node) node.classList.toggle('hidden');
    };

    // ---------------------------------------------------------------
    // Edit modal
    // ---------------------------------------------------------------
    window.openEditModal = function (placeId) {
        var place = currentPlaces().find(function (item) { return Number(item.id) === Number(placeId); });
        if (!place) return;

        var form = el('editPlaceForm');
        if (!form) return;
        form.action = placeUrl('/places/' + place.id + '/update');

        el('edit_name').value = place.name || '';
        el('edit_description').value = place.description || '';
        el('edit_category_id').value = place.category_id;
        el('edit_address').value = place.address || '';
        el('edit_district').value = place.district || '';
        el('edit_latitude').value = place.latitude;
        el('edit_longitude').value = place.longitude;
        el('edit_phone').value = place.phone || '';
        el('edit_email').value = place.email || '';
        el('edit_website').value = place.website || '';
        el('edit_is_verified').checked = !!place.is_verified;
        el('edit_is_active').checked = place.is_active !== false;

        renderExistingImages(place);

        var modal = el('editPlaceModal');
        if (modal) modal.classList.remove('hidden');
    };

    function renderExistingImages(place) {
        var container = el('existingImages');
        if (!container) return;

        if (!place.images || !place.images.length) {
            container.innerHTML = '<p class="rounded-2xl bg-slate-50 px-3 py-3 text-xs text-slate-400">No images yet. Upload one above.</p>';
            return;
        }

        container.innerHTML = '<p class="mb-2 text-xs font-semibold text-slate-600">Existing images</p>' +
            '<div class="flex flex-wrap gap-2">' +
                place.images.map(function (image) {
                    var preview = image.url
                        ? '<img src="' + escapeHtml(image.thumb || image.url) + '" alt="" class="h-20 w-20 object-cover">'
                        : '<span class="flex h-20 w-20 items-center justify-center text-slate-300"><i class="fas fa-image"></i></span>';
                    return '<div class="group relative overflow-hidden rounded-xl border border-slate-200 bg-slate-50">' +
                        preview +
                        '<form method="POST" action="' + placeUrl('/places/' + place.id + '/images/delete') + '" class="absolute right-1 top-1" onsubmit="return confirm(\'Delete this image?\')">' +
                            '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                            '<input type="hidden" name="id" value="' + image.id + '">' +
                            '<button type="submit" class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-rose-600 text-[9px] text-white shadow-sm transition hover:bg-rose-700" title="Delete image" aria-label="Delete image">' +
                                '<i class="fas fa-xmark"></i>' +
                            '</button>' +
                        '</form>' +
                    '</div>';
                }).join('') +
            '</div>';
    }

    window.closeEditModal = function () {
        var modal = el('editPlaceModal');
        if (modal) modal.classList.add('hidden');
    };

    // ---------------------------------------------------------------
    // Bulk selection + actions
    // ---------------------------------------------------------------
    window.toggleAll = function (source) {
        Array.prototype.forEach.call(document.querySelectorAll('.place-checkbox'), function (checkbox) {
            checkbox.checked = source.checked;
        });
        updateBulkBar();
    };

    window.updateBulkBar = function () {
        var checked = document.querySelectorAll('.place-checkbox:checked');
        var bar = el('bulkBar');
        var count = el('selectedCount');
        var selectAll = el('selectAll');
        var total = document.querySelectorAll('.place-checkbox').length;

        if (bar) {
            bar.classList.toggle('hidden', checked.length === 0);
            bar.classList.toggle('flex', checked.length > 0);
        }
        if (count) count.textContent = checked.length;
        if (selectAll) {
            selectAll.checked = total > 0 && checked.length === total;
            selectAll.indeterminate = checked.length > 0 && checked.length < total;
        }
    };

    window.getSelectedIds = function () {
        return Array.prototype.map.call(document.querySelectorAll('.place-checkbox:checked'), function (checkbox) {
            return checkbox.value;
        });
    };

    window.clearSelection = function () {
        Array.prototype.forEach.call(document.querySelectorAll('.place-checkbox'), function (checkbox) {
            checkbox.checked = false;
        });
        var selectAll = el('selectAll');
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        updateBulkBar();
    };

    window.bulkDelete = function () {
        var ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm('Delete ' + ids.length + ' selected places? This cannot be undone.')) return;
        var form = el('bulkForm');
        if (!form) return;
        form.action = placeUrl('/places/bulk-delete');
        form.submit();
    };

    window.openBulkEditModal = function () {
        var ids = getSelectedIds();
        if (!ids.length) return;
        var form = el('bulkEditForm');
        if (form) form.reset();
        var holder = el('bulkEditIds');
        if (holder) {
            holder.innerHTML = '';
            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                holder.appendChild(input);
            });
        }
        el('bulkEditCount').textContent = ids.length;
        var modal = el('bulkEditModal');
        if (modal) modal.classList.remove('hidden');
    };

    window.closeBulkEditModal = function () {
        var modal = el('bulkEditModal');
        if (modal) modal.classList.add('hidden');
    };

    // ---------------------------------------------------------------
    // Category modal
    // ---------------------------------------------------------------
    window.openCategoryModal = function () {
        var modal = el('categoryModal');
        if (modal) modal.classList.remove('hidden');
    };

    window.closeCategoryModal = function () {
        var modal = el('categoryModal');
        if (modal) modal.classList.add('hidden');
    };

    // Close modals on Escape, bound once per page shell.
    if (!window.__placesKeyBound) {
        window.__placesKeyBound = true;
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            window.closeEditModal();
            window.closeReviewsModal();
            window.closeBulkEditModal();
            window.closeCategoryModal();
        });
    }

    // Auto-open the editor from the place detail page (?edit_id=123).
    (function () {
        var params = new URLSearchParams(window.location.search);
        var editId = params.get('edit_id');
        if (!editId) return;
        window.openEditModal(parseInt(editId, 10));
        params.delete('edit_id');
        var query = params.toString();
        history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));
    })();

    // ---------------------------------------------------------------
    // OSM import progress polling
    // ---------------------------------------------------------------
    (function () {
        var panel = el('osmImportProgress');
        if (!panel) return;

        var label = el('osmProgressLabel');
        var counts = el('osmProgressCounts');
        var bar = el('osmProgressBar');
        var meta = el('osmProgressMeta');
        var output = el('osmProgressOutput');
        var statusUrl = @json(route('admin.places.import-osm.status'));
        var labels = {
            queued: 'Queued…',
            running: 'Importing…',
            completed: 'Import completed',
            failed: 'Import failed'
        };

        function render(status) {
            panel.classList.remove('hidden');
            var state = status.status || 'unknown';
            label.textContent = labels[state] || state;
            label.className = 'text-sm font-medium ' + (state === 'failed' ? 'text-rose-600' : state === 'completed' ? 'text-emerald-600' : 'text-slate-700');

            var total = parseInt(status.total || 0, 10);
            var index = parseInt(status.index || 0, 10);
            var percent = total > 0 ? Math.min(100, Math.round((index / total) * 100)) : (state === 'completed' ? 100 : 0);
            bar.style.width = percent + '%';

            counts.textContent = total > 0 ? index + ' / ' + total + ' cities' : '';

            var bits = [];
            if (status.city) bits.push('City: ' + status.city);
            if (status.imported !== '' && status.imported !== undefined) bits.push(status.imported + ' imported');
            if (status.skipped !== '' && status.skipped !== undefined) bits.push(status.skipped + ' skipped');
            if (status.message) bits.push(status.message);
            meta.textContent = bits.join(' · ');

            if (status.output && (state === 'completed' || state === 'failed')) {
                output.textContent = status.output;
                output.classList.remove('hidden');
            } else {
                output.classList.add('hidden');
            }
        }

        function poll() {
            if (!document.body.contains(panel)) return;
            fetch(statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (status) {
                    if (!status || !status.status) return;
                    render(status);
                    if (status.status === 'queued' || status.status === 'running') {
                        window.__placesOsmTimer = setTimeout(poll, 3000);
                    }
                })
                .catch(function () {
                    // Transient network error: wait for the next page load.
                });
        }

        if (window.__placesOsmTimer) clearTimeout(window.__placesOsmTimer);
        poll();
    })();
})();
</script>
@endsection
