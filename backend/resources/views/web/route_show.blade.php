@extends('web.layout')

@section('title', $route->title)

@section('content')
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <nav class="text-sm text-slate-400 mb-6">
        <a href="{{ route('web.home') }}" class="hover:text-primary-600">Home</a>
        <i class="fas fa-chevron-right text-xs mx-2"></i>
        <a href="{{ route('web.routes') }}" class="hover:text-primary-600">Routes</a>
        <i class="fas fa-chevron-right text-xs mx-2"></i>
        <span class="text-slate-600">{{ $route->title }}</span>
    </nav>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="h-64 bg-gradient-to-br from-primary-700 to-primary-900 grid place-items-center text-white text-6xl relative">
            <i class="fas fa-route"></i>
            @if($route->image)
                <img src="{{ $route->image }}" alt="{{ $route->title }}" class="absolute inset-0 w-full h-full object-cover">
            @endif
        </div>
        <div class="p-8">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs {{ $route->route_type === 'trekking' ? 'bg-amber-500/10 text-amber-600' : 'bg-blue-500/10 text-blue-600' }} font-semibold rounded-full px-3 py-1">
                    <i class="fas {{ $route->route_type === 'trekking' ? 'fa-person-hiking' : 'fa-route' }}"></i> {{ $route->route_type === 'trekking' ? 'Trekking' : 'Itinerary' }}
                </span>
                @if($route->difficulty)
                    <span class="text-xs bg-slate-100 text-slate-600 font-semibold rounded-full px-3 py-1"><i class="fas fa-signal"></i> {{ $route->difficultyLabel() }}</span>
                @endif
                <span class="text-xs bg-primary-50 text-primary-700 font-semibold rounded-full px-3 py-1"><i class="fas fa-clock"></i> {{ $route->duration_days }} day{{ $route->duration_days > 1 ? 's' : '' }}</span>
                @if($route->best_season)
                    <span class="text-xs bg-accent-500/10 text-accent-600 font-semibold rounded-full px-3 py-1"><i class="fas fa-sun"></i> Best: {{ $route->best_season }}</span>
                @endif
                <span class="text-xs bg-slate-100 text-slate-600 font-semibold rounded-full px-3 py-1"><i class="fas fa-location-dot"></i> {{ count($places) }} stops</span>
            </div>
            <h1 class="text-3xl font-bold text-slate-800 mt-4">{{ $route->title }}</h1>
            @if($route->description)
                <p class="text-slate-600 text-sm leading-relaxed mt-4 whitespace-pre-line">{{ $route->description }}</p>
            @endif

            {{-- Trekking / route stats --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6">
                @if($route->total_distance_km)
                    <div class="bg-slate-50 rounded-xl p-4 text-center">
                        <i class="fas fa-route text-primary-600 mb-1 block"></i>
                        <div class="text-xl font-bold text-slate-800">{{ number_format($route->total_distance_km) }} km</div>
                        <div class="text-xs text-slate-400">Total distance</div>
                    </div>
                @endif
                @if($route->max_altitude_m)
                    <div class="bg-slate-50 rounded-xl p-4 text-center">
                        <i class="fas fa-mountain text-primary-600 mb-1 block"></i>
                        <div class="text-xl font-bold text-slate-800">{{ number_format($route->max_altitude_m) }} m</div>
                        <div class="text-xs text-slate-400">Max altitude</div>
                    </div>
                @endif
                @if($route->elevation_gain_m)
                    <div class="bg-slate-50 rounded-xl p-4 text-center">
                        <i class="fas fa-arrow-trend-up text-primary-600 mb-1 block"></i>
                        <div class="text-xl font-bold text-slate-800">{{ number_format($route->elevation_gain_m) }} m</div>
                        <div class="text-xs text-slate-400">Elevation gain</div>
                    </div>
                @endif
                @if($route->starting_point)
                    <div class="bg-slate-50 rounded-xl p-4 text-center">
                        <i class="fas fa-flag text-primary-600 mb-1 block"></i>
                        <div class="text-sm font-bold text-slate-800 leading-snug">{{ $route->starting_point }} → {{ $route->ending_point ?? $route->starting_point }}</div>
                        <div class="text-xs text-slate-400">Start → End</div>
                    </div>
                @endif
            </div>

            @if($track = $route->trackPoints())
                <h2 class="font-semibold text-slate-800 mt-8 mb-4">Route map</h2>
                <div id="routeMap" class="h-96 rounded-2xl border border-slate-100"></div>

                <h2 class="font-semibold text-slate-800 mt-8 mb-4">Trail waypoints</h2>
                <div class="space-y-4">
                    @foreach($track as $i => $tp)
                        <div class="flex items-center gap-4 bg-slate-50 rounded-xl p-4">
                            <div class="w-9 h-9 rounded-full {{ $i === 0 || $i === count($track) - 1 ? 'bg-accent-600' : 'bg-primary-600' }} text-white grid place-items-center font-bold text-sm shrink-0">{{ $i + 1 }}</div>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-800 text-sm">{{ $tp['name'] ?? 'Waypoint ' . ($i + 1) }}</div>
                                <div class="text-xs text-slate-400 truncate">{{ number_format($tp['lat'], 4) }}, {{ number_format($tp['lng'], 4) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(count($places) > 0)
                <h2 class="font-semibold text-slate-800 mt-8 mb-4">Stops on this route</h2>
                <div class="space-y-4">
                    @foreach($places as $i => $place)
                        <a href="{{ route('web.place', $place->id) }}" class="flex items-center gap-4 bg-slate-50 hover:bg-primary-50 rounded-xl p-4 transition">
                            <div class="w-9 h-9 rounded-full bg-primary-600 text-white grid place-items-center font-bold text-sm shrink-0">{{ $i + 1 }}</div>
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-800 text-sm">{{ $place->name }}</div>
                                <div class="text-xs text-slate-400 truncate">{{ $place->district ?? 'Nepal' }}</div>
                            </div>
                            @if($place->average_rating > 0)
                                <div class="ml-auto text-xs text-amber-500"><i class="fas fa-star"></i> {{ number_format($place->average_rating, 1) }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>

                <div class="mt-8">
                    <x-app-gate feature="Offline navigation for this route">
                        <div class="bg-slate-50 rounded-xl p-5 text-center">
                            <i class="fas fa-map-location-dot text-2xl text-primary-600 mb-2 block"></i>
                            <p class="text-sm text-slate-600">Follow this route offline with live turn-by-turn directions in the app.</p>
                        </div>
                    </x-app-gate>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection

@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const mapEl = document.getElementById('routeMap');
    if (!mapEl || typeof L === 'undefined') return;

    const track = @json($route->trackPoints());
    if (!track || track.length === 0) return;

    const latlngs = track.map(p => [p.lat, p.lng]);
    const map = L.map('routeMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.polyline(latlngs, { color: '#0f766e', weight: 4, opacity: 0.85 }).addTo(map);

    track.forEach((p, i) => {
        const isEnd = i === 0 || i === track.length - 1;
        const icon = L.divIcon({
            className: 'route-wp',
            html: '<div style="width:26px;height:26px;border-radius:50%;background:' + (isEnd ? '#d97706' : '#0f766e') + ';color:#fff;display:grid;place-items:center;font-size:11px;font-weight:700;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)">' + (i + 1) + '</div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });
        L.marker(latlngs[i], { icon }).addTo(map)
            .bindPopup('<b>' + (p.name || ('Waypoint ' + (i + 1))) + '</b><br>' + p.lat.toFixed(4) + ', ' + p.lng.toFixed(4));
    });

    map.fitBounds(L.latLngBounds(latlngs).pad(0.15));
});
</script>
@endpush
