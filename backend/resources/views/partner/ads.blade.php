@extends('partner.layout')
@section('title', 'Ad Campaigns')

@section('content')
@php
    $statusColors = [
        'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
        'active' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-red-100 text-red-700 border-red-200',
        'paused' => 'bg-slate-100 text-slate-600 border-slate-200',
        'completed' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
    $typeLabels = ['banner' => 'Banner', 'promoted_place' => 'Promoted Place', 'sponsored_card' => 'Sponsored Card'];
@endphp

{{-- Header --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Ad Campaigns</h2>
        <p class="text-sm text-slate-500 mt-0.5">Reach travelers on the right screen. Pay via eSewa/Khalti — billed per view (CPM) and click (CPC).</p>
    </div>
    <a href="{{ route('partner.ads.create') }}" class="inline-flex items-center justify-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shadow shrink-0">
        <i class="fas fa-plus"></i> New Campaign
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-5">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 group hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-primary-50 grid place-items-center text-primary-600"><i class="fas fa-bullhorn text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-primary-600">{{ $stats['total'] }}</div>
                <div class="text-[10px] text-slate-500">Total</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 group hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 grid place-items-center text-emerald-600"><i class="fas fa-play-circle text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-emerald-600">{{ $stats['active'] }}</div>
                <div class="text-[10px] text-slate-500">Live</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 group hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-amber-50 grid place-items-center text-amber-600"><i class="fas fa-clock text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-500">{{ $stats['pending'] }}</div>
                <div class="text-[10px] text-slate-500">Pending</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 group hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-blue-50 grid place-items-center text-blue-600"><i class="fas fa-eye text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-slate-800">{{ number_format($stats['impressions']) }}</div>
                <div class="text-[10px] text-slate-500">Views</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 group hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-purple-50 grid place-items-center text-purple-600"><i class="fas fa-mouse-pointer text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-slate-800">{{ number_format($stats['clicks']) }}</div>
                <div class="text-[10px] text-slate-500">{{ $stats['ctr'] }}% CTR</div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 group hover:shadow-md transition">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-amber-50 grid place-items-center text-amber-600"><i class="fas fa-coins text-xs"></i></div>
            <div>
                <div class="text-xl font-bold text-amber-600">Rs. {{ number_format($stats['spent'], 0) }}</div>
                <div class="text-[10px] text-slate-500">Spent</div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="flex gap-2 flex-wrap mb-5">
    <a href="{{ route('partner.ads') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$status ? 'bg-primary-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200' }} transition">All</a>
    @foreach(['pending', 'active', 'paused'] as $s)
        <a href="{{ route('partner.ads', ['status' => $s]) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === $s ? 'bg-primary-600 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200' }} transition">{{ ucfirst($s) }}</a>
    @endforeach
</div>

{{-- Desktop Table --}}
<div class="hidden lg:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="text-left px-6 py-3 font-semibold">Campaign</th>
                <th class="text-left px-4 py-3 font-semibold">Type</th>
                <th class="text-center px-4 py-3 font-semibold">Views</th>
                <th class="text-center px-4 py-3 font-semibold">Clicks</th>
                <th class="text-center px-4 py-3 font-semibold">CTR</th>
                <th class="text-right px-4 py-3 font-semibold">Budget</th>
                <th class="text-right px-4 py-3 font-semibold">Spent</th>
                <th class="text-right px-4 py-3 font-semibold">Left</th>
                <th class="text-left px-4 py-3 font-semibold">Status</th>
                <th class="text-right px-6 py-3 font-semibold">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($campaigns as $ad)
                <tr class="hover:bg-slate-50/60 transition">
                    <td class="px-6 py-4">
                        <div class="font-medium text-slate-800">{{ $ad->name }}</div>
                        <div class="text-xs text-slate-400 mt-0.5">
                            @if($ad->contexts)
                                <i class="fas fa-crosshairs"></i> {{ implode(', ', array_map('ucfirst', $ad->contexts)) }}
                            @else
                                <i class="fas fa-globe"></i> All screens
                            @endif
                            @if($ad->target_district) — {{ $ad->target_district }}@endif
                        </div>
                    </td>
                    <td class="px-4 py-4 text-slate-600">{{ $typeLabels[$ad->ad_type] ?? $ad->ad_type }}</td>
                    <td class="px-4 py-4 text-center text-slate-700">{{ number_format($ad->current_impressions) }}@if($ad->max_impressions > 0) / {{ number_format($ad->max_impressions) }}@endif</td>
                    <td class="px-4 py-4 text-center text-slate-700">{{ number_format($ad->current_clicks) }}</td>
                    <td class="px-4 py-4 text-center text-slate-700">{{ $ad->ctr() }}%</td>
                    <td class="px-4 py-4 text-right text-slate-700">Rs. {{ number_format($ad->budget, 0) }}</td>
                    <td class="px-4 py-4 text-right font-semibold text-amber-600">Rs. {{ number_format($ad->spent_amount, 1) }}</td>
                    <td class="px-4 py-4 text-right text-slate-500">Rs. {{ number_format($ad->budgetRemaining(), 1) }}</td>
                    <td class="px-4 py-4">
                        <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$ad->status] ?? '' }}">{{ ucfirst($ad->status) }}</span>
                        @if($ad->status === 'active' && $ad->ends_at && $ad->ends_at->lte(now()))<span class="block text-[10px] text-orange-500 mt-0.5">Ended</span>
                        @elseif($ad->status === 'active' && !$ad->hasBudget())<span class="block text-[10px] text-orange-500 mt-0.5">Budget exhausted</span>
                        @elseif($ad->status === 'paused')
                            @if($ad->paused_by === 'system' && !$ad->hasBudget())<span class="block text-[10px] text-orange-500 mt-0.5">Budget exhausted</span>
                            @elseif($ad->paused_by === 'admin')<span class="block text-[10px] text-orange-500 mt-0.5">Paused by admin</span>
                            @elseif($ad->ends_at && $ad->ends_at->lte(now()))<span class="block text-[10px] text-orange-500 mt-0.5">Ended</span>
                            @elseif($ad->paused_by === 'partner')<span class="block text-[10px] text-orange-500 mt-0.5">Paused by you</span>@endif
                        @endif
                        <span class="text-xs px-3 py-1 rounded-full border {{ $ad->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($ad->payment_status === 'refunded' ? 'bg-slate-100 text-slate-500 border-slate-200' : 'bg-red-50 text-red-600 border-red-200') }}">{{ ucfirst($ad->payment_status) }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-1.5">
                            @if($ad->payment_status !== 'paid' && (float) $ad->budget > 0)
                                <a href="{{ route('partner.ads.pay', $ad) }}" class="px-2.5 py-1.5 text-xs font-bold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition" title="Pay"><i class="fas fa-credit-card"></i></a>
                            @endif
                            @if($ad->payment_status !== 'paid' && $ad->status !== 'active')
                                <a href="{{ route('partner.ads.edit', $ad) }}" class="px-2.5 py-1.5 text-xs font-medium bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-100 transition" title="Edit"><i class="fas fa-edit"></i></a>
                            @endif
                            @if($ad->status === 'active')
                                <form method="POST" action="{{ route('partner.ads.pause', $ad) }}">
                                    @csrf
                                    <button class="px-2.5 py-1.5 text-xs font-medium bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition" title="Pause"><i class="fas fa-pause"></i></button>
                                </form>
                            @elseif($ad->status === 'paused')
                                @if($ad->paused_by === 'admin')
                                    <span class="text-[10px] text-orange-500 px-1">Admin locked</span>
                                @else
                                    <form method="POST" action="{{ route('partner.ads.resume', $ad) }}">
                                        @csrf
                                        <button class="px-2.5 py-1.5 text-xs font-medium bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100 transition" title="Resume"><i class="fas fa-play"></i></button>
                                    </form>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('partner.ads.destroy', $ad) }}" onsubmit="return confirm('Delete this campaign?')">
                                @csrf
                                @method('DELETE')
                                <button class="px-2.5 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="px-6 py-12 text-center text-slate-400">
                        <i class="fas fa-bullhorn text-4xl mb-3 block text-slate-300"></i>
                        No ad campaigns yet. <a href="{{ route('partner.ads.create') }}" class="text-primary-600 font-semibold hover:underline">Create your first campaign</a> to reach travelers.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t border-slate-100">{{ $campaigns->links() }}</div>
</div>

{{-- Mobile Cards --}}
<div class="lg:hidden space-y-3">
    @forelse($campaigns as $ad)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-800 text-sm leading-tight">{{ $ad->name }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            {{ $typeLabels[$ad->ad_type] ?? $ad->ad_type }}
                            @if($ad->target_district) &middot; {{ $ad->target_district }}@endif
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1 shrink-0">
                        <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $statusColors[$ad->status] ?? '' }}">{{ ucfirst($ad->status) }}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $ad->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200' }}">{{ ucfirst($ad->payment_status) }}</span>
                    </div>
                </div>

                {{-- Stats Grid --}}
                <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-slate-100">
                    <div>
                        <div class="text-[10px] text-slate-400">Views</div>
                        <div class="text-sm font-semibold text-slate-700">{{ number_format($ad->current_impressions) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">Clicks</div>
                        <div class="text-sm font-semibold text-slate-700">{{ number_format($ad->current_clicks) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">CTR</div>
                        <div class="text-sm font-semibold text-slate-700">{{ $ad->ctr() }}%</div>
                    </div>
                </div>

                {{-- Budget Bar --}}
                <div class="mt-3">
                    <div class="flex justify-between text-[11px] mb-1">
                        <span class="text-slate-500">Budget: Rs. {{ number_format($ad->budget, 0) }}</span>
                        <span class="text-amber-600 font-medium">Spent: Rs. {{ number_format($ad->spent_amount, 0) }}</span>
                    </div>
                    @php
                        $percent = $ad->budget > 0 ? min(($ad->spent_amount / $ad->budget) * 100, 100) : 0;
                    @endphp
                    <div class="w-full bg-slate-100 rounded-full h-1.5">
                        <div class="bg-gradient-to-r from-primary-500 to-accent-500 h-1.5 rounded-full transition-all" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5 text-right">Rs. {{ number_format($ad->budgetRemaining(), 0) }} remaining</div>
                </div>

                @if($ad->contexts)
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($ad->contexts as $ctx)
                            <span class="text-[9px] px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded">{{ ucfirst($ctx) }}</span>
                        @endforeach
                    </div>
                @endif

                @if($ad->status === 'active' && $ad->ends_at && $ad->ends_at->lte(now()))
                    <div class="text-[11px] text-orange-500 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Ended — no longer serving</div>
                @elseif($ad->status === 'active' && !$ad->hasBudget())
                    <div class="text-[11px] text-orange-500 mt-2"><i class="fas fa-exclamation-triangle mr-1"></i>Budget exhausted</div>
                @endif
            </div>

            {{-- Action Bar --}}
            <div class="border-t border-slate-100 px-4 py-2.5 flex items-center justify-end gap-1 bg-slate-50/50">
                @if($ad->payment_status !== 'paid' && (float) $ad->budget > 0)
                    <a href="{{ route('partner.ads.pay', $ad) }}" class="px-3 py-1.5 text-xs font-bold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition"><i class="fas fa-credit-card mr-1"></i>Pay</a>
                @endif
                @if($ad->payment_status !== 'paid' && $ad->status !== 'active')
                    <a href="{{ route('partner.ads.edit', $ad) }}" class="w-8 h-8 grid place-items-center rounded-lg text-slate-500 hover:bg-white transition" title="Edit"><i class="fas fa-edit text-xs"></i></a>
                @endif
                @if($ad->status === 'active')
                    <form method="POST" action="{{ route('partner.ads.pause', $ad) }}">
                        @csrf
                        <button class="w-8 h-8 grid place-items-center rounded-lg text-amber-600 hover:bg-white transition" title="Pause"><i class="fas fa-pause text-xs"></i></button>
                    </form>
                @elseif($ad->status === 'paused')
                    @if($ad->paused_by !== 'admin')
                        <form method="POST" action="{{ route('partner.ads.resume', $ad) }}">
                            @csrf
                            <button class="w-8 h-8 grid place-items-center rounded-lg text-emerald-600 hover:bg-white transition" title="Resume"><i class="fas fa-play text-xs"></i></button>
                        </form>
                    @endif
                @endif
                <form method="POST" action="{{ route('partner.ads.destroy', $ad) }}" onsubmit="return confirm('Delete this campaign?')">
                    @csrf
                    @method('DELETE')
                    <button class="w-8 h-8 grid place-items-center rounded-lg text-red-500 hover:bg-white transition" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                </form>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-10 text-center text-slate-400">
            <i class="fas fa-bullhorn text-4xl mb-3 block text-slate-300"></i>
            No ad campaigns yet.<br><a href="{{ route('partner.ads.create') }}" class="text-primary-600 font-semibold hover:underline">Create your first campaign</a>!
        </div>
    @endforelse
    <div class="mt-4">{{ $campaigns->links() }}</div>
</div>
@endsection
