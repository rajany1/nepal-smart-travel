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

<div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-slate-900">Ad Campaigns</h2>
            <p class="text-sm text-slate-500 mt-1">Reach travelers on the right screen. Pay a budget via eSewa/Khalti - you are billed per view (CPM) and per click (CPC) until the budget is used.</p>
    </div>
    <a href="{{ route('partner.ads.create') }}" class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shadow self-start">
        <i class="fas fa-plus"></i> New Ad Campaign
    </a>
</div>

<div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-2xl font-bold text-primary-600">{{ $stats['total'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Total Campaigns</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-2xl font-bold text-emerald-600">{{ $stats['active'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Live</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-2xl font-bold text-amber-500">{{ $stats['pending'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Pending Approval</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-2xl font-bold text-slate-800">{{ number_format($stats['impressions']) }}</div>
        <div class="text-xs text-slate-500 mt-1">Impressions</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-2xl font-bold text-slate-800">{{ number_format($stats['clicks']) }}</div>
        <div class="text-xs text-slate-500 mt-1">Clicks ({{ $stats['ctr'] }}% CTR)</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-2xl font-bold text-amber-600">Rs. {{ number_format($stats['spent'], 1) }}</div>
        <div class="text-xs text-slate-500 mt-1">Spent (budget used)</div>
    </div>
</div>

<div class="flex gap-2 flex-wrap mb-6">
    <a href="{{ route('partner.ads') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$status ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">All</a>
    @foreach(['pending', 'active', 'paused', 'rejected'] as $s)
        <a href="{{ route('partner.ads', ['status' => $s]) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === $s ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">{{ ucfirst($s) }}</a>
    @endforeach
</div>

<div class="bg-white rounded-2xl shadow border border-slate-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
            <tr>
                <th class="text-left px-6 py-3">Campaign</th>
                <th class="text-left px-4 py-3">Type</th>
                <th class="text-center px-4 py-3">Impressions</th>
                <th class="text-center px-4 py-3">Clicks</th>
                <th class="text-center px-4 py-3">CTR</th>
                <th class="text-right px-4 py-3">Budget</th>
<th class="text-right px-4 py-3">Spent</th>
<th class="text-right px-4 py-3">Remaining</th>
                <th class="text-left px-4 py-3">Status</th>
                <th class="text-right px-6 py-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($campaigns as $ad)
                <tr class="hover:bg-slate-50/60">
                    <td class="px-6 py-4">
                        <div class="font-medium text-slate-800">{{ $ad->name }}</div>
                        @if($ad->status === 'rejected' && $ad->rejection_reason)
                            <div class="text-xs text-red-500 mt-0.5"><i class="fas fa-times-circle"></i> {{ $ad->rejection_reason }}</div>
                        @endif
                        <div class="text-xs text-slate-400 mt-0.5">
                            @if($ad->contexts)
                                <i class="fas fa-crosshairs"></i> {{ implode(', ', array_map('ucfirst', $ad->contexts)) }}
                            @else
                                <i class="fas fa-globe"></i> All screens
                            @endif
@if($ad->target_district) - {{ $ad->target_district }}@endif
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
                        @if($ad->status === 'active' && $ad->ends_at && $ad->ends_at->lte(now()))<span class="block text-[10px] text-orange-500 mt-0.5">Ended — no longer serving</span>
                        @elseif($ad->status === 'active' && !$ad->hasBudget())<span class="block text-[10px] text-orange-500 mt-0.5">Budget exhausted — no longer serving</span>
                        @elseif($ad->status === 'paused')
                            @if($ad->paused_by === 'system' && !$ad->hasBudget())<span class="block text-[10px] text-orange-500 mt-0.5">Budget exhausted</span>
                            @elseif($ad->paused_by === 'admin')<span class="block text-[10px] text-orange-500 mt-0.5">Paused by admin</span>
                            @elseif($ad->ends_at && $ad->ends_at->lte(now()))<span class="block text-[10px] text-orange-500 mt-0.5">Ended</span>
                            @elseif($ad->paused_by === 'partner')<span class="block text-[10px] text-orange-500 mt-0.5">Paused by you</span>@endif
                        @endif
                        <span class="text-xs px-3 py-1 rounded-full border {{ $ad->payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($ad->payment_status === 'refunded' ? 'bg-slate-100 text-slate-500 border-slate-200' : 'bg-red-50 text-red-600 border-red-200') }}">Payment: {{ ucfirst($ad->payment_status) }}</span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-1.5">
                            @if($ad->payment_status !== 'paid' && (float) $ad->budget > 0)
                                <a href="{{ route('partner.ads.pay', $ad) }}" class="px-2.5 py-1.5 text-xs font-bold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition" title="Pay now"><i class="fas fa-credit-card"></i> Pay</a>
                            @endif
                            @if($ad->payment_status !== 'paid' && !in_array($ad->status, ['active', 'rejected']))
                                <a href="{{ route('partner.ads.edit', $ad) }}" class="px-2.5 py-1.5 text-xs font-medium bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-100 transition" title="Edit"><i class="fas fa-edit"></i></a>
                            @endif
                            @if($ad->status === 'active')
                                <form method="POST" action="{{ route('partner.ads.pause', $ad) }}">
                                    @csrf
                                    <button class="px-2.5 py-1.5 text-xs font-medium bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition" title="Pause"><i class="fas fa-pause"></i></button>
                                </form>
                            @elseif($ad->status === 'paused')
                                @if($ad->paused_by === 'admin')
                                    <span class="text-[10px] text-orange-500 px-1" title="Contact admin to resume">Admin locked</span>
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
                    <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                        <i class="fas fa-bullhorn text-3xl mb-3 block"></i>
                        No ad campaigns yet. <a href="{{ route('partner.ads.create') }}" class="text-primary-600 font-semibold">Create your first campaign</a> to reach travelers.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $campaigns->links() }}</div>
@endsection
