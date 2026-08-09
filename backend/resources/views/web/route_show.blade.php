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
