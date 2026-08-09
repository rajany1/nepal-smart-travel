@extends('web.layout')

@section('title', isset($title) ? $title . ' — Places' : 'Places')

@section('content')
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-3xl font-bold text-slate-800 mb-2">{{ isset($title) ? $title : 'All Places' }}</h1>
    <p class="text-slate-500 text-sm mb-8">{{ $places->total() }} places found</p>

    @if(isset($categories))
    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-8 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search places, districts..."
                       class="w-full rounded-xl border border-slate-200 pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
        </div>
        <div class="min-w-[160px]">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
            <select name="category" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[160px]">
            <label class="block text-xs font-semibold text-slate-600 mb-1">District</label>
            <select name="district" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                <option value="">All districts</option>
                @foreach($districts as $d)
                    <option value="{{ $d }}" @selected(request('district') === $d)>{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl px-6 py-2.5 transition">Filter</button>
        @if(request()->hasAny(['q', 'category', 'district']))
            <a href="{{ route('web.places') }}" class="text-sm text-slate-500 hover:text-slate-700 py-2.5">Clear</a>
        @endif
    </form>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($places as $place)
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
                    <p class="text-xs text-slate-500 mt-1.5">
                        <i class="fas fa-location-dot"></i> {{ $place->district ?? 'Nepal' }}
                        @if($place->address) · {{ $place->address }} @endif
                    </p>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center text-slate-400 py-16">
                <i class="fas fa-map-pin text-4xl mb-4 block"></i>
                No places found matching your filters.
            </div>
        @endforelse
    </div>

    <div class="mt-10">{{ $places->links() }}</div>
</section>
@endsection
