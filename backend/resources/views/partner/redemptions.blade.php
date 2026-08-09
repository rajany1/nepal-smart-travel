@extends('partner.layout')

@section('title', 'Offer Redemptions')

@section('content')
@php
    $statusColors = [
        'claimed' => 'bg-amber-100 text-amber-700 border-amber-200',
        'used' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'expired' => 'bg-slate-100 text-slate-500 border-slate-200',
    ];
@endphp

<div class="mb-6">
    <a href="{{ route('partner.offers') }}" class="text-sm text-primary-600 hover:underline"><i class="fas fa-arrow-left"></i> Back to offers</a>
    <h2 class="text-2xl font-bold text-slate-800 mt-2">{{ $offer->title }}</h2>
    <p class="text-sm text-slate-500 mt-1">
        {{ $offer->label() }} · {{ $redemptions->total() }} total redemptions · limit {{ $offer->usage_limit ?: '∞' }}
    </p>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-6 py-3">Code</th>
                    <th class="text-left px-4 py-3">User</th>
                    <th class="text-left px-4 py-3">Status</th>
<th class="text-left px-4 py-3">Value (Rs)</th>
                    <th class="text-left px-4 py-3">Commission</th>
                    <th class="text-left px-4 py-3">Your Earnings</th>
                    <th class="text-left px-4 py-3">Claimed At</th>
                    <th class="text-left px-4 py-3">Used At</th>
                    <th class="text-right px-6 py-3">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($redemptions as $r)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-4">
                            <span class="font-mono font-bold text-primary-700 tracking-wider">{{ $r->code }}</span>
                        </td>
<td class="px-4 py-4 text-slate-700">{{ $r->user?->name ?? '-' }}</td>
                        <td class="px-4 py-4 text-right font-semibold text-slate-800">Rs. {{ number_format((float) ($r->value_npr ?? 0), 2) }}</td>
                        <td class="px-4 py-4 text-right text-slate-500">Rs. {{ number_format((float) ($r->admin_commission ?? 0), 2) }}</td>
                        <td class="px-4 py-4 text-right font-semibold text-emerald-600">Rs. {{ number_format((float) ($r->partner_earnings ?? 0), 2) }}</td>
                        <td class="px-4 py-4">
                            <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$r->status] ?? '' }}">{{ ucfirst($r->status) }}</span>
                        </td>
                        <td class="px-4 py-4 text-slate-600">{{ $r->claimed_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ $r->used_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            @if($r->status === 'claimed' || ($r->status === 'used' && !$r->consumed_at))
                                <form method="POST" action="{{ route('partner.offers.redemptions.used', [$offer, $r]) }}" onsubmit="return confirm('Confirm the customer showed this code?');">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg px-3 py-1.5 transition">
                                        <i class="fas fa-check"></i> Mark Used
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-slate-400">{{ $r->consumed_at ? 'Used' : ($r->applied_at ? 'Applied to booking' : '—') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-ticket-alt text-3xl mb-3 block"></i>
                            No codes claimed yet. Share this offer so customers can claim it in the app!
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4">{{ $redemptions->links() }}</div>
</div>
@endsection
