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

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-800">My Offers</h2>
        <p class="text-sm text-slate-500 mt-1">Create offers and track redemptions</p>
    </div>
    <div class="flex items-center gap-3">
        <div class="flex rounded-xl border border-slate-200 bg-white overflow-hidden text-sm">
            <a href="{{ route('partner.offers') }}" class="px-4 py-2 {{ !$status ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">All</a>
            @foreach(['pending', 'approved', 'paused', 'rejected'] as $s)
                <a href="{{ route('partner.offers', ['status' => $s]) }}" class="px-4 py-2 {{ $status === $s ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-50' }}">{{ ucfirst($s) }}</a>
            @endforeach
        </div>
        <a href="{{ route('partner.offers.create') }}" class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shadow">
            <i class="fas fa-plus"></i> New Offer
        </a>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-3xl font-bold text-primary-600">{{ $stats['total'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Total</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-3xl font-bold text-emerald-600">{{ $stats['approved'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Approved</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-3xl font-bold text-amber-500">{{ $stats['pending'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Pending</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-3xl font-bold text-accent-500">{{ $stats['claims'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Total Claims</div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-6 py-3">Offer</th>
                    <th class="text-left px-4 py-3">Type</th>
                    <th class="text-left px-4 py-3">XP Price</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-left px-4 py-3">Claims</th>
                        <th class="text-left px-4 py-3">Value (Rs.)</th>
                        <th class="text-left px-4 py-3">Earned (Rs.)</th>
                    <th class="text-left px-4 py-3">Expires</th>
                    <th class="text-right px-6 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($offers as $offer)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800">{{ $offer->title }}</div>
                            @if($offer->trashed() && $offer->admin_removed_reason)
                                <div class="text-xs text-red-600 mt-0.5"><i class="fas fa-exclamation-triangle"></i> Removed by admin — {{ $offer->admin_removed_reason }}</div>
                            @elseif($offer->status === 'rejected' && $offer->rejection_reason)
                                <div class="text-xs text-red-500 mt-0.5"><i class="fas fa-times-circle"></i> {{ $offer->rejection_reason }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-slate-600">{{ $typeLabels[$offer->offer_type] ?? $offer->offer_type }}</td>
                        <td class="px-4 py-4 font-semibold text-primary-600">{{ $offer->price_xp }} XP</td>
                        <td class="px-4 py-4">
                            @if($offer->trashed())
                                <span class="text-xs px-3 py-1 rounded-full border bg-red-100 text-red-700 border-red-200">Removed by admin</span>
                            @else
                                <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$offer->status] ?? '' }}">{{ ucfirst($offer->status) }}</span>
                                @if($offer->paused_by === 'system')
                                    <span class="block text-[10px] text-red-500 mt-0.5">Ended — locked by system</span>
                                @elseif($offer->paused_by === 'admin')
                                    <span class="block text-[10px] text-orange-500 mt-0.5">Paused by admin</span>
                                @elseif($offer->paused_by === 'partner')
                                    <span class="block text-[10px] text-orange-500 mt-0.5">Paused by you</span>
                                @elseif($offer->ends_at && $offer->ends_at->lte(now()) && in_array($offer->status, ['approved', 'paused']))
                                    <span class="block text-[10px] text-red-500 mt-0.5">Ended</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-4 text-slate-700">{{ $offer->redemptions_count }} / {{ $offer->usage_limit ?: 'Unlimited' }}</td>
                    <td class="px-4 py-4 text-slate-700">Rs. {{ number_format($offer->value_npr ?? 0, 0) }}</td>
                    <td class="px-4 py-4 font-medium text-emerald-600">Rs. {{ number_format($offer->redemptions()->where('status', 'used')->sum('partner_earnings'), 2) }}</td>
                        <td class="px-4 py-4 {{ $offer->isEnded() ? 'text-red-500' : 'text-slate-600' }}">{{ $offer->ends_at ? $offer->ends_at->format('M j, Y') : 'No expiry' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('partner.offers.redemptions', $offer) }}" title="View redemptions"
                                   class="w-8 h-8 grid place-items-center rounded-lg text-slate-500 hover:bg-primary-50 hover:text-primary-600"><i class="fas fa-ticket-alt"></i></a>
                                @if(!$offer->trashed())
                                    @if(!in_array($offer->status, ['approved', 'rejected']) && !$offer->isSystemLocked())
                                        <a href="{{ route('partner.offers.edit', $offer) }}" title="Edit"
                                           class="w-8 h-8 grid place-items-center rounded-lg text-slate-500 hover:bg-primary-50 hover:text-primary-600"><i class="fas fa-edit"></i></a>
                                    @endif
                                    @if($offer->status === 'approved')
                                        <form method="POST" action="{{ route('partner.offers.pause', $offer) }}">
                                            @csrf
                                            <button type="submit" title="Pause"
                                                    class="w-8 h-8 grid place-items-center rounded-lg text-amber-600 hover:bg-amber-50"><i class="fas fa-pause"></i></button>
                                        </form>
                                    @elseif($offer->status === 'paused' && !$offer->isSystemLocked())
                                        <form method="POST" action="{{ route('partner.offers.resume', $offer) }}">
                                            @csrf
                                            <button type="submit" title="Resume"
                                                    class="w-8 h-8 grid place-items-center rounded-lg text-emerald-600 hover:bg-emerald-50"><i class="fas fa-play"></i></button>
                                        </form>
                                    @elseif($offer->status === 'paused' && $offer->isSystemLocked())
                                        <span class="text-[10px] text-red-500 font-medium px-2" title="Ended offer cannot be resumed">Locked</span>
                                    @endif
                                    <form method="POST" action="{{ route('partner.offers.destroy', $offer) }}" onsubmit="return confirm('Delete this offer?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" title="Delete"
                                                class="w-8 h-8 grid place-items-center rounded-lg text-red-500 hover:bg-red-50"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-gift text-3xl mb-3 block"></i>
                            @if($status)
                                No {{ $status }} offers.
                            @else
                                No offers yet. <a href="{{ route('partner.offers.create') }}" class="text-primary-600 font-semibold">Create your first offer</a>!
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4">{{ $offers->links() }}</div>
</div>
@endsection
