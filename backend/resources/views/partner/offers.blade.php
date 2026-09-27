@extends('partner.layout')

@section('title', 'My Offers')

@section('content')
@php
    $statusColors = [
        'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
        'approved' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-red-100 text-red-700 border-red-200',
        'paused' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
    $typeLabels = [
        'percentage_off' => 'Percentage Off',
        'fixed_off' => 'Fixed Amount Off',
        'free_item' => 'Free Item',
        'buy_one_get_one' => 'Buy One Get One',
    ];
@endphp

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-800">My Offers</h2>
        <p class="text-sm text-slate-500 mt-0.5">Create offers and track redemptions</p>
    </div>
    <a href="{{ route('partner.offers.create') }}" class="inline-flex items-center justify-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shadow shrink-0">
        <i class="fas fa-plus"></i> New Offer
    </a>
</div>

{{-- Filters --}}
<div class="flex flex-wrap items-center gap-2 mb-5">
    <div class="flex rounded-xl border border-slate-200 bg-white overflow-hidden text-sm shadow-sm">
        <a href="{{ route('partner.offers') }}" class="px-3 sm:px-4 py-2 font-medium {{ !$status ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-50' }} transition">All</a>
        @foreach(['approved', 'paused'] as $s)
            <a href="{{ route('partner.offers', ['status' => $s]) }}" class="px-3 sm:px-4 py-2 font-medium {{ $status === $s ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-50' }} transition">{{ ucfirst($s) }}</a>
        @endforeach
    </div>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-primary-50 grid place-items-center text-primary-600"><i class="fas fa-list"></i></div>
            <div>
                <div class="text-2xl font-bold text-primary-600">{{ $stats['total'] }}</div>
                <div class="text-[11px] text-slate-500">Total</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 grid place-items-center text-emerald-600"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="text-2xl font-bold text-emerald-600">{{ $stats['approved'] }}</div>
                <div class="text-[11px] text-slate-500">Approved</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 grid place-items-center text-amber-600"><i class="fas fa-clock"></i></div>
            <div>
                <div class="text-2xl font-bold text-amber-500">{{ $stats['pending'] }}</div>
                <div class="text-[11px] text-slate-500">Pending</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-accent-50 grid place-items-center text-accent-600"><i class="fas fa-ticket-alt"></i></div>
            <div>
                <div class="text-2xl font-bold text-accent-500">{{ $stats['claims'] }}</div>
                <div class="text-[11px] text-slate-500">Claims</div>
            </div>
        </div>
    </div>
</div>

{{-- Desktop Table --}}
<div class="hidden lg:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-6 py-3 font-semibold">Offer</th>
                    <th class="text-left px-4 py-3 font-semibold">Type</th>
                    <th class="text-left px-4 py-3 font-semibold">XP Price</th>
                    <th class="text-left px-4 py-3 font-semibold">Status</th>
                    <th class="text-left px-4 py-3 font-semibold">Claims</th>
                    <th class="text-left px-4 py-3 font-semibold">Value (Rs.)</th>
                    <th class="text-left px-4 py-3 font-semibold">Earned (Rs.)</th>
                    <th class="text-left px-4 py-3 font-semibold">Expires</th>
                    <th class="text-right px-6 py-3 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($offers as $offer)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if($offer->image)
                                    <img src="{{ asset('storage/' . $offer->image) }}" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                                @endif
                                <div>
                                    <div class="font-medium text-slate-800">{{ $offer->title }}</div>
                                    @if($offer->trashed() && $offer->admin_removed_reason)
                                        <div class="text-xs text-red-600 mt-0.5"><i class="fas fa-exclamation-triangle"></i> Removed — {{ $offer->admin_removed_reason }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-slate-600">{{ $typeLabels[$offer->offer_type] ?? $offer->offer_type }}</td>
                        <td class="px-4 py-4 font-semibold text-primary-600">{{ $offer->price_xp }} XP</td>
                        <td class="px-4 py-4">
                            @if($offer->trashed())
                                <span class="text-xs px-3 py-1 rounded-full border bg-red-100 text-red-700 border-red-200">Removed</span>
                            @else
                                <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$offer->status] ?? '' }}">{{ ucfirst($offer->status) }}</span>
                                @if($offer->paused_by === 'system')
                                    <span class="block text-[10px] text-red-500 mt-0.5">Ended — locked</span>
                                @elseif($offer->paused_by === 'admin')
                                    <span class="block text-[10px] text-orange-500 mt-0.5">Paused by admin</span>
                                @elseif($offer->paused_by === 'partner')
                                    <span class="block text-[10px] text-orange-500 mt-0.5">Paused by you</span>
                                @elseif($offer->ends_at && $offer->ends_at->lte(now()) && in_array($offer->status, ['approved', 'paused']))
                                    <span class="block text-[10px] text-red-500 mt-0.5">Ended</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-4 text-slate-700">{{ $offer->redemptions_count }} / {{ $offer->usage_limit ?: '∞' }}</td>
                        <td class="px-4 py-4 text-slate-700">Rs. {{ number_format($offer->value_npr ?? 0, 0) }}</td>
                        <td class="px-4 py-4 font-medium text-emerald-600">Rs. {{ number_format($offer->redemptions()->where('status', 'used')->sum('partner_earnings'), 2) }}</td>
                        <td class="px-4 py-4 {{ $offer->isEnded() ? 'text-red-500' : 'text-slate-600' }}">{{ $offer->ends_at ? $offer->ends_at->format('M j, Y') : 'No expiry' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('partner.offers.redemptions', $offer) }}" title="Redemptions" class="w-8 h-8 grid place-items-center rounded-lg text-slate-500 hover:bg-primary-50 hover:text-primary-600 transition"><i class="fas fa-ticket-alt"></i></a>
                                @if(!$offer->trashed())
                                    @if($offer->status !== 'approved' && !$offer->isSystemLocked())
                                        <a href="{{ route('partner.offers.edit', $offer) }}" title="Edit" class="w-8 h-8 grid place-items-center rounded-lg text-slate-500 hover:bg-primary-50 hover:text-primary-600 transition"><i class="fas fa-edit"></i></a>
                                    @endif
                                    @if($offer->status === 'approved')
                                        <form method="POST" action="{{ route('partner.offers.pause', $offer) }}">
                                            @csrf
                                            <button type="submit" title="Pause" class="w-8 h-8 grid place-items-center rounded-lg text-amber-600 hover:bg-amber-50 transition"><i class="fas fa-pause"></i></button>
                                        </form>
                                    @elseif($offer->status === 'paused' && !$offer->isSystemLocked())
                                        <form method="POST" action="{{ route('partner.offers.resume', $offer) }}">
                                            @csrf
                                            <button type="submit" title="Resume" class="w-8 h-8 grid place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50 transition"><i class="fas fa-play"></i></button>
                                        </form>
                                    @elseif($offer->status === 'paused' && $offer->isSystemLocked())
                                        <span class="text-[10px] text-red-500 font-medium px-2">Locked</span>
                                    @endif
                                    <form method="POST" action="{{ route('partner.offers.destroy', $offer) }}" onsubmit="return confirm('Delete this offer?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete" class="w-8 h-8 grid place-items-center rounded-lg text-red-500 hover:bg-red-50 transition"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-gift text-4xl mb-3 block text-slate-300"></i>
                            @if($status)
                                No {{ $status }} offers found.
                            @else
                                No offers yet. <a href="{{ route('partner.offers.create') }}" class="text-primary-600 font-semibold hover:underline">Create your first offer</a>!
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-slate-100">{{ $offers->links() }}</div>
</div>

{{-- Mobile Cards --}}
<div class="lg:hidden space-y-3">
    @forelse($offers as $offer)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @if($offer->image)
                            <img src="{{ asset('storage/' . $offer->image) }}" alt="" class="w-12 h-12 rounded-xl object-cover flex-shrink-0">
                        @endif
                        <div class="min-w-0">
                            <div class="font-semibold text-slate-800 text-sm leading-tight truncate">{{ $offer->title }}</div>
                            <div class="text-[11px] text-slate-500 mt-0.5">{{ $typeLabels[$offer->offer_type] ?? $offer->offer_type }}</div>
                        </div>
                    </div>
                    @if($offer->trashed())
                        <span class="text-[10px] px-2 py-0.5 rounded-full border bg-red-100 text-red-700 border-red-200 shrink-0">Removed</span>
                    @else
                        <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $statusColors[$offer->status] ?? '' }} shrink-0">{{ ucfirst($offer->status) }}</span>
                    @endif
                </div>

                {{-- Stats Row --}}
                <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-slate-100">
                    <div>
                        <div class="text-xs text-slate-400">Price</div>
                        <div class="text-sm font-semibold text-primary-600">{{ $offer->price_xp }} XP</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Claims</div>
                        <div class="text-sm font-semibold text-slate-700">{{ $offer->redemptions_count }} / {{ $offer->usage_limit ?: '∞' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-400">Earned</div>
                        <div class="text-sm font-semibold text-emerald-600">Rs. {{ number_format($offer->redemptions()->where('status', 'used')->sum('partner_earnings'), 0) }}</div>
                    </div>
                </div>

                @if($offer->ends_at)
                    <div class="text-[11px] text-slate-400 mt-2 {{ $offer->isEnded() ? 'text-red-500' : '' }}">
                        <i class="fas fa-calendar-alt mr-1"></i>Expires {{ $offer->ends_at->format('M j, Y') }}
                    </div>
                @endif

                @if($offer->trashed() && $offer->admin_removed_reason)
                    <div class="text-[11px] text-red-600 mt-2 bg-red-50 rounded-lg px-2.5 py-1.5">
                        <i class="fas fa-exclamation-triangle mr-1"></i>{{ $offer->admin_removed_reason }}
                    </div>
                @endif
            </div>

            {{-- Action Bar --}}
            @if(!$offer->trashed())
                <div class="border-t border-slate-100 px-4 py-2.5 flex items-center justify-between bg-slate-50/50">
                    <a href="{{ route('partner.offers.redemptions', $offer) }}" class="text-xs text-primary-600 hover:text-primary-700 font-medium"><i class="fas fa-ticket-alt mr-1"></i>Redemptions</a>
                    <div class="flex items-center gap-1">
                        @if($offer->status !== 'approved' && !$offer->isSystemLocked())
                            <a href="{{ route('partner.offers.edit', $offer) }}" class="w-8 h-8 grid place-items-center rounded-lg text-slate-500 hover:bg-white hover:text-primary-600 transition" title="Edit"><i class="fas fa-edit text-xs"></i></a>
                        @endif
                        @if($offer->status === 'approved')
                            <form method="POST" action="{{ route('partner.offers.pause', $offer) }}">
                                @csrf
                                <button class="w-8 h-8 grid place-items-center rounded-lg text-amber-600 hover:bg-white transition" title="Pause"><i class="fas fa-pause text-xs"></i></button>
                            </form>
                        @elseif($offer->status === 'paused' && !$offer->isSystemLocked())
                            <form method="POST" action="{{ route('partner.offers.resume', $offer) }}">
                                @csrf
                                <button class="w-8 h-8 grid place-items-center rounded-lg text-emerald-600 hover:bg-white transition" title="Resume"><i class="fas fa-play text-xs"></i></button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('partner.offers.destroy', $offer) }}" onsubmit="return confirm('Delete this offer?');">
                            @csrf
                            @method('DELETE')
                            <button class="w-8 h-8 grid place-items-center rounded-lg text-red-500 hover:bg-white transition" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-10 text-center text-slate-400">
            <i class="fas fa-gift text-4xl mb-3 block text-slate-300"></i>
            @if($status)
                No {{ $status }} offers found.
            @else
                No offers yet.<br><a href="{{ route('partner.offers.create') }}" class="text-primary-600 font-semibold hover:underline">Create your first offer</a>!
            @endif
        </div>
    @endforelse
    <div class="mt-4">{{ $offers->links() }}</div>
</div>
@endsection
