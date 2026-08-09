@extends('web.layout')

@section('title', 'Offers & Rewards')

@section('content')
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="text-3xl font-bold text-slate-800 mb-2">Local Offers & Rewards</h1>
    <p class="text-slate-500 text-sm mb-8">Exclusive discounts from verified local businesses.</p>

    <x-app-gate feature="Claiming offer codes">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse($offers as $offer)
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-6 bg-gradient-to-br from-primary-600 to-primary-800 text-white">
                        <div class="text-3xl font-extrabold">{{ $offer->label() }}</div>
                        <div class="text-sm text-teal-200 mt-1 font-medium">{{ $offer->title }}</div>
                    </div>
                    <div class="p-5 flex flex-col flex-1">
                        <div class="text-sm font-semibold text-slate-800">
                            <i class="fas fa-store text-primary-600"></i> {{ $offer->business?->name ?? 'Local business' }}
                        </div>
                        @if($offer->business?->district)
                            <div class="text-xs text-slate-500 mt-1"><i class="fas fa-location-dot"></i> {{ $offer->business->district }}</div>
                        @endif
                        @if($offer->description)
                            <p class="text-sm text-slate-600 mt-3 flex-1">{{ $offer->description }}</p>
                        @endif
                        @if($offer->terms)
                            <p class="text-xs text-slate-400 mt-3 italic"><i class="fas fa-info-circle"></i> {{ $offer->terms }}</p>
                        @endif
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100">
                            <span class="text-xs text-amber-600">
                                <i class="fas fa-clock"></i>
                                {{ $offer->ends_at ? 'Till ' . $offer->ends_at->format('M j, Y') : 'No expiry' }}
                            </span>
                            <span class="text-xs text-slate-400">
                                @if($offer->usage_limit > 0)
                                    {{ $offer->usage_limit - $offer->used_count }} left
                                @else
                                    Unlimited
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center text-slate-400 py-16">
                    <i class="fas fa-gift text-4xl mb-4 block"></i>
                    No active offers right now. Check back soon!
                </div>
            @endforelse
        </div>
    </x-app-gate>

    <div class="mt-10">{{ $offers->links() }}</div>
</section>
@endsection
