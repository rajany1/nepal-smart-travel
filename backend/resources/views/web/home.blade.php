@extends('web.layout')

@section('title', 'Nepal Smart Travel & Local Intelligence')

@section('content')
{{-- Hero --}}
<section class="relative bg-primary-900 text-white overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-br from-primary-800 via-primary-900 to-slate-900"></div>
    <div class="absolute -top-24 -right-24 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-32 -left-24 w-96 h-96 bg-teal-400/10 rounded-full blur-3xl"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-24 text-center">
        <span class="inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5 text-xs text-teal-100 mb-6">
            <i class="fas fa-location-dot text-accent-400"></i> {{ $placesCount }}+ places mapped across Nepal
        </span>
        <h1 class="text-4xl md:text-6xl font-extrabold leading-tight mb-5">
            Discover Nepal,<br>
            <span class="text-accent-400">Live & Local.</span>
        </h1>
        <p class="text-teal-100/80 text-lg max-w-2xl mx-auto mb-8">
            Real-time road conditions, community reports, hidden gems, curated routes, and exclusive local offers — powered by locals, for travelers.
        </p>
        <div class="flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('web.places') }}" class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 font-semibold rounded-xl px-7 py-3.5 transition shadow-lg">
                <i class="fas fa-map-location-dot"></i> Explore Places
            </a>
            <a href="{{ env('PLAY_STORE_URL', '#') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 border border-white/30 font-semibold rounded-xl px-7 py-3.5 transition">
                <i class="fas fa-mobile-screen"></i> Get the App
            </a>
        </div>
    </div>
</section>

{{-- Categories --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    <h2 class="text-2xl font-bold text-slate-800 mb-6">Browse by Category</h2>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach([
            ['route' => 'hotels', 'icon' => 'hotel', 'label' => 'Hotels'],
            ['route' => 'restaurants', 'icon' => 'utensils', 'label' => 'Restaurants'],
            ['route' => 'attractions', 'icon' => 'landmark', 'label' => 'Attractions'],
            ['route' => 'cafes', 'icon' => 'mug-hot', 'label' => 'Cafes'],
            ['route' => 'activities', 'icon' => 'person-hiking', 'label' => 'Activities'],
        ] as $cat)
            <a href="{{ route('web.category', $cat['route']) }}" class="group bg-white rounded-2xl shadow-sm hover:shadow-lg p-6 text-center transition border border-slate-100">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-primary-50 group-hover:bg-primary-600 text-primary-600 group-hover:text-white grid place-items-center text-xl mb-3 transition">
                    <i class="fas fa-{{ $cat['icon'] }}"></i>
                </div>
                <div class="font-semibold text-slate-800">{{ $cat['label'] }}</div>
            </a>
        @endforeach
    </div>
</section>

{{-- Featured Places --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 pb-14">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Featured Places</h2>
        <a href="{{ route('web.places') }}" class="text-sm text-primary-600 font-semibold hover:underline">View all <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($featured as $place)
            <a href="{{ route('web.place', $place->id) }}" class="group bg-white rounded-2xl shadow-sm hover:shadow-xl overflow-hidden transition border border-slate-100">
                <div class="h-44 bg-gradient-to-br from-primary-600 to-primary-800 grid place-items-center text-white text-4xl relative">
                    <i class="fas fa-map-marker-alt"></i>
                    @if($place->images->isNotEmpty())
                        <img src="{{ $place->images->first()->image_url }}" alt="{{ $place->name }}" class="absolute inset-0 w-full h-full object-cover">
                    @endif
                    @if($place->average_rating > 0)
                        <span class="absolute top-3 right-3 bg-white/95 text-slate-800 text-xs font-bold rounded-full px-2.5 py-1">
                            <i class="fas fa-star text-amber-400"></i> {{ number_format($place->average_rating, 1) }}
                        </span>
                    @endif
                </div>
                <div class="p-5">
                    <div class="text-xs text-primary-600 font-semibold uppercase tracking-wide">{{ $place->category?->name ?? 'Place' }}</div>
                    <h3 class="font-bold text-slate-800 mt-1 group-hover:text-primary-600 transition">{{ $place->name }}</h3>
                    <p class="text-xs text-slate-500 mt-1.5"><i class="fas fa-location-dot"></i> {{ $place->district ?? 'Nepal' }}</p>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center text-slate-400 py-10">No featured places yet.</div>
        @endforelse
    </div>
</section>

{{-- Routes --}}
@if($routes->isNotEmpty())
    <section class="bg-white border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-slate-800">Curated Routes</h2>
                <a href="{{ route('web.routes') }}" class="text-sm text-primary-600 font-semibold hover:underline">View all <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($routes as $route)
                    <a href="{{ route('web.route', $route->slug) }}" class="group bg-slate-50 rounded-2xl p-6 hover:bg-primary-50 transition border border-slate-100">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-11 h-11 rounded-xl bg-primary-600 text-white grid place-items-center"><i class="fas fa-route"></i></div>
                            <div class="text-xs text-slate-500">{{ $route->duration_days }} day{{ $route->duration_days > 1 ? 's' : '' }}{{ $route->best_season ? ' · ' . $route->best_season : '' }}</div>
                        </div>
                        <h3 class="font-bold text-slate-800 group-hover:text-primary-600 transition">{{ $route->title }}</h3>
                        <p class="text-sm text-slate-500 mt-2 line-clamp-2">{{ $route->description }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- Offers --}}
@if($offers->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-slate-800">Local Offers & Rewards</h2>
            <a href="{{ route('web.offers') }}" class="text-sm text-primary-600 font-semibold hover:underline">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <x-app-gate feature="Claiming offers">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($offers as $offer)
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-5 bg-gradient-to-br from-primary-600 to-primary-800 text-white">
                            <div class="text-2xl font-extrabold">{{ $offer->label() }}</div>
                            <div class="text-xs text-teal-200 mt-1">{{ $offer->title }}</div>
                        </div>
                        <div class="p-4">
                            <div class="text-sm font-semibold text-slate-800"><i class="fas fa-store text-primary-600"></i> {{ $offer->business?->name }}</div>
                            @if($offer->business?->district)
                                <div class="text-xs text-slate-500 mt-1">{{ $offer->business->district }}</div>
                            @endif
                            @if($offer->ends_at)
                                <div class="text-xs text-amber-600 mt-2"><i class="fas fa-clock"></i> Till {{ $offer->ends_at->format('M j, Y') }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-app-gate>
    </section>
@endif
@endsection
