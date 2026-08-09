@extends('web.layout')

@section('title', $place->name)

@section('content')
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <nav class="text-sm text-slate-400 mb-6">
        <a href="{{ route('web.home') }}" class="hover:text-primary-600">Home</a>
        <i class="fas fa-chevron-right text-xs mx-2"></i>
        <a href="{{ route('web.places') }}" class="hover:text-primary-600">Places</a>
        <i class="fas fa-chevron-right text-xs mx-2"></i>
        <span class="text-slate-600">{{ $place->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="h-72 bg-gradient-to-br from-primary-600 to-primary-800 grid place-items-center text-white text-6xl relative">
                    <i class="fas fa-map-marker-alt"></i>
                    @if($place->images->isNotEmpty())
                        <img src="{{ $place->images->first()->image_url }}" alt="{{ $place->name }}" class="absolute inset-0 w-full h-full object-cover">
                    @endif
                </div>
                <div class="p-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-xs bg-primary-50 text-primary-700 font-semibold uppercase tracking-wide rounded-full px-3 py-1">{{ $place->category?->name ?? 'Place' }}</span>
                        @if($place->is_featured)
                            <span class="text-xs bg-accent-500/10 text-accent-600 font-semibold rounded-full px-3 py-1"><i class="fas fa-star"></i> Featured</span>
                        @endif
                        @if($place->average_rating > 0)
                            <span class="text-xs bg-amber-50 text-amber-600 font-semibold rounded-full px-3 py-1"><i class="fas fa-star"></i> {{ number_format($place->average_rating, 1) }} ({{ $place->total_reviews }} reviews)</span>
                        @endif
                    </div>
                    <h1 class="text-3xl font-bold text-slate-800 mt-3">{{ $place->name }}</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        <i class="fas fa-location-dot text-primary-600"></i>
                        {{ $place->address ? $place->address . ', ' : '' }}{{ $place->district ?? 'Nepal' }}
                    </p>

                    @if($place->description)
                        <div class="mt-6">
                            <h2 class="font-semibold text-slate-800 mb-2">About</h2>
                            <p class="text-slate-600 text-sm leading-relaxed whitespace-pre-line">{{ $place->description }}</p>
                        </div>
                    @endif

                    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @if($place->phone)
                            <a href="tel:{{ $place->phone }}" class="flex items-center gap-3 bg-slate-50 rounded-xl px-4 py-3 text-sm hover:bg-primary-50 transition">
                                <i class="fas fa-phone text-primary-600"></i> <span class="font-medium text-slate-700">{{ $place->phone }}</span>
                            </a>
                        @endif
                        @if($place->website)
                            <a href="{{ str_starts_with($place->website, 'http') ? $place->website : 'https://' . $place->website }}" target="_blank" rel="noopener" class="flex items-center gap-3 bg-slate-50 rounded-xl px-4 py-3 text-sm hover:bg-primary-50 transition">
                                <i class="fas fa-globe text-primary-600"></i> <span class="font-medium text-slate-700">Website</span>
                            </a>
                        @endif
                    </div>

                    <div class="mt-6">
                        <x-app-gate feature="Directions & offline maps">
                            <div class="bg-slate-50 rounded-xl p-4 text-center">
                                <i class="fas fa-map text-2xl text-primary-600 mb-2 block"></i>
                                <p class="text-sm text-slate-600">Get turn-by-turn directions to this place and explore offline maps.</p>
                                <div class="text-xs text-slate-400 mt-2">Lat {{ number_format($place->latitude, 5) }} · Lng {{ number_format($place->longitude, 5) }}</div>
                            </div>
                        </x-app-gate>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 mt-6 p-6">
                <h2 class="font-semibold text-slate-800 mb-4">Reviews & Community Reports</h2>
                <x-app-gate feature="Live reviews and community reports">
                    <div class="space-y-4">
                        @forelse($reviews as $review)
                            <div class="border border-slate-100 rounded-xl p-4">
                                <div class="flex items-center justify-between">
                                    <div class="font-medium text-slate-800 text-sm">{{ $review->user?->name ?? 'Traveler' }}</div>
                                    <div class="text-xs text-amber-500">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="fas fa-star{{ $i <= $review->rating ? '' : '-o text-slate-300' }}"></i>
                                        @endfor
                                    </div>
                                </div>
                                @if($review->title) <div class="font-semibold text-sm text-slate-700 mt-1">{{ $review->title }}</div> @endif
                                <p class="text-sm text-slate-600 mt-1">{{ $review->description }}</p>
                                <div class="text-xs text-slate-400 mt-2">{{ $review->created_at?->format('M j, Y') }}</div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400 text-center py-4">No reviews yet.</p>
                        @endforelse
                    </div>
                </x-app-gate>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-primary-600 text-white grid place-items-center text-2xl mb-3">
                    <i class="fas fa-mobile-screen"></i>
                </div>
                <h3 class="font-bold text-slate-800">Experience it live</h3>
                <p class="text-xs text-slate-500 mt-1 mb-4">Real-time road conditions, live reports, and exclusive rewards — in the app.</p>
                <a href="{{ env('PLAY_STORE_URL', '#') }}" class="block w-full bg-black text-white rounded-xl px-4 py-3 text-sm font-semibold mb-2 hover:bg-slate-800 transition">
                    <i class="fab fa-google-play"></i> Get the App
                </a>
                <a href="{{ env('APP_STORE_URL', '#') }}" class="block w-full bg-black text-white rounded-xl px-4 py-3 text-sm font-semibold hover:bg-slate-800 transition">
                    <i class="fab fa-apple"></i> App Store
                </a>
            </div>

            @if($similar->isNotEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                    <h3 class="font-semibold text-slate-800 mb-4">Similar Places</h3>
                    <div class="space-y-3">
                        @foreach($similar as $s)
                            <a href="{{ route('web.place', $s->id) }}" class="flex items-center gap-3 group">
                                <div class="w-10 h-10 rounded-lg bg-primary-50 grid place-items-center text-primary-600 shrink-0">
                                    <i class="fas fa-map-marker-alt text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-800 group-hover:text-primary-600 truncate">{{ $s->name }}</div>
                                    <div class="text-xs text-slate-400 truncate">{{ $s->district }}</div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
