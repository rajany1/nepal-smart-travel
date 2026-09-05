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

<div class="mb-5">
    <a href="{{ route('partner.offers') }}" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1"><i class="fas fa-arrow-left"></i> Back to offers</a>
    <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mt-2">{{ $offer->title }}</h2>
    <p class="text-sm text-slate-500 mt-1">
        {{ $offer->label() }} &middot; {{ $redemptions->total() }} total &middot; limit {{ $offer->usage_limit ?: '∞' }}
    </p>
</div>

{{-- Desktop Table --}}
<div class="hidden lg:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-6 py-3 font-semibold">Code</th>
                    <th class="text-left px-4 py-3 font-semibold">User</th>
                    <th class="text-left px-4 py-3 font-semibold">Status</th>
                    <th class="text-left px-4 py-3 font-semibold">Value (Rs)</th>
                    <th class="text-left px-4 py-3 font-semibold">Commission</th>
                    <th class="text-left px-4 py-3 font-semibold">Your Earnings</th>
                    <th class="text-left px-4 py-3 font-semibold">Claimed At</th>
                    <th class="text-left px-4 py-3 font-semibold">Used At</th>
                    <th class="text-right px-6 py-3 font-semibold">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($redemptions as $r)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4">
                            <span class="font-mono font-bold text-primary-700 tracking-wider text-xs">{{ $r->code }}</span>
                        </td>
                        <td class="px-4 py-4 text-slate-700">{{ $r->user?->name ?? '-' }}</td>
                        <td class="px-4 py-4">
                            <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$r->status] ?? '' }}">{{ ucfirst($r->status) }}</span>
                        </td>
                        <td class="px-4 py-4 text-right font-semibold text-slate-800">Rs. {{ number_format((float) ($r->value_npr ?? 0), 2) }}</td>
                        <td class="px-4 py-4 text-right text-slate-500">Rs. {{ number_format((float) ($r->admin_commission ?? 0), 2) }}</td>
                        <td class="px-4 py-4 text-right font-semibold text-emerald-600">Rs. {{ number_format((float) ($r->partner_earnings ?? 0), 2) }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ $r->claimed_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ $r->used_at?->format('M j, Y H:i') ?? '—' }}</td>
                        <td class="px-6 py-4 text-right">
                            @if($r->status === 'claimed')
                                <span class="text-xs text-slate-500"><i class="fas fa-qrcode mr-1"></i>Use Scan/Redeem</span>
                            @else
                                <span class="text-xs text-slate-400">{{ $r->consumed_at ? 'Used' : ($r->applied_at ? 'Applied' : '—') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-ticket-alt text-4xl mb-3 block text-slate-300"></i>
                            No codes claimed yet. Share this offer so customers can claim it!
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-slate-100">{{ $redemptions->links() }}</div>
</div>

{{-- Mobile Cards --}}
<div class="lg:hidden space-y-3">
    @forelse($redemptions as $r)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <span class="font-mono font-bold text-primary-700 tracking-wider text-sm">{{ $r->code }}</span>
                        <div class="text-xs text-slate-500 mt-0.5">{{ $r->user?->name ?? 'Unknown user' }}</div>
                    </div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $statusColors[$r->status] ?? '' }} shrink-0">{{ ucfirst($r->status) }}</span>
                </div>

                <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-slate-100">
                    <div>
                        <div class="text-[10px] text-slate-400">Value</div>
                        <div class="text-sm font-semibold text-slate-800">Rs. {{ number_format((float) ($r->value_npr ?? 0), 0) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">Commission</div>
                        <div class="text-sm font-semibold text-red-500">-Rs. {{ number_format((float) ($r->admin_commission ?? 0), 0) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">You Get</div>
                        <div class="text-sm font-semibold text-emerald-600">Rs. {{ number_format((float) ($r->partner_earnings ?? 0), 0) }}</div>
                    </div>
                </div>

                <div class="flex justify-between text-[11px] text-slate-400 mt-2">
                    <span>Claimed: {{ $r->claimed_at?->format('M j, g:i A') ?? '—' }}</span>
                    <span>Used: {{ $r->used_at?->format('M j, g:i A') ?? '—' }}</span>
                </div>
            </div>

            @if($r->status === 'claimed')
                <div class="border-t border-slate-100 px-4 py-2.5 bg-slate-50/50 text-center">
                    <span class="text-xs text-slate-500"><i class="fas fa-qrcode mr-1"></i>Use Scan/Redeem from wallet to redeem</span>
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-10 text-center text-slate-400">
            <i class="fas fa-ticket-alt text-4xl mb-3 block text-slate-300"></i>
            No codes claimed yet. Share this offer!
        </div>
    @endforelse
    <div class="mt-4">{{ $redemptions->links() }}</div>
</div>
@endsection
