@extends('web.layout')

@section('title', 'Curated Routes')

@section('content')
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-3xl font-bold text-slate-800 mb-2">Curated Routes</h1>
    <p class="text-slate-500 text-sm mb-8">Hand-picked itineraries to explore Nepal's best places.</p>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($routes as $route)
            <a href="{{ route('web.route', $route->slug) }}" class="group bg-white rounded-2xl shadow-sm hover:shadow-xl overflow-hidden transition border border-slate-100">
                <div class="h-44 bg-gradient-to-br from-primary-700 to-primary-900 grid place-items-center text-white text-4xl relative">
                    <i class="fas {{ $route->route_type === 'trekking' ? 'fa-person-hiking' : 'fa-route' }}"></i>
                    <span class="absolute top-3 left-3 bg-white/95 text-slate-800 text-xs font-bold rounded-full px-3 py-1.5">
                        <i class="fas fa-clock text-primary-600"></i> {{ $route->duration_days }} day{{ $route->duration_days > 1 ? 's' : '' }}
                    </span>
                    <span class="absolute top-3 right-3 text-xs font-bold rounded-full px-3 py-1.5
                        {{ $route->route_type === 'trekking' ? 'bg-amber-500 text-white' : 'bg-blue-500 text-white' }}">
                        {{ $route->route_type === 'trekking' ? 'Trekking' : 'Itinerary' }}
                    </span>
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-slate-800 group-hover:text-primary-600 transition">{{ $route->title }}</h3>
                    <div class="flex flex-wrap gap-2 mt-1.5">
                        @if($route->difficulty)
                            <span class="text-xs bg-slate-100 text-slate-600 font-semibold rounded-full px-2.5 py-0.5"><i class="fas fa-signal"></i> {{ $route->difficultyLabel() }}</span>
                        @endif
                        @if($route->total_distance_km)
                            <span class="text-xs bg-slate-100 text-slate-600 font-semibold rounded-full px-2.5 py-0.5"><i class="fas fa-route"></i> {{ number_format($route->total_distance_km) }} km</span>
                        @endif
                        @if($route->max_altitude_m)
                            <span class="text-xs bg-slate-100 text-slate-600 font-semibold rounded-full px-2.5 py-0.5"><i class="fas fa-mountain"></i> {{ number_format($route->max_altitude_m) }} m</span>
                        @endif
                    </div>
                    @if($route->best_season)
                        <div class="text-xs text-primary-600 font-semibold mt-1.5"><i class="fas fa-sun"></i> Best: {{ $route->best_season }}</div>
                    @endif
                    <p class="text-sm text-slate-500 mt-2 line-clamp-3">{{ $route->description }}</p>
                    <div class="text-xs text-slate-400 mt-3">{{ count($route->waypoints ?? []) }} stops</div>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center text-slate-400 py-16">
                <i class="fas fa-route text-4xl mb-4 block"></i>
                Routes are being curated. Check back soon!
            </div>
        @endforelse
    </div>

    <div class="mt-10">{{ $routes->links() }}</div>
</section>
@endsection
