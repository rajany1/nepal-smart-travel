@extends('admin.layout')

@section('title', 'Earnings Report - Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Oripori Coins Earnings Report</h1>
        <a href="{{ route('admin.withdrawals') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-1"></i> Back to Withdrawals
        </a>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Today -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Today</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-500">Impressions</span>
                    <span class="font-semibold">{{ number_format($stats['today']['impressions']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Clicks</span>
                    <span class="font-semibold">{{ number_format($stats['today']['clicks']) }}</span>
                </div>
                <div class="flex justify-between border-t pt-3">
                    <span class="text-gray-700 font-medium">Coins Earned</span>
                    <span class="text-green-600 font-bold">{{ number_format($stats['today']['coins_earned'], 2) }} Coins</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-700 font-medium">Withdrawals Paid</span>
                    <span class="text-red-600 font-bold">Rs. {{ number_format($stats['today']['withdrawals'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- This Month -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">This Month</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-500">Impressions</span>
                    <span class="font-semibold">{{ number_format($stats['this_month']['impressions']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Clicks</span>
                    <span class="font-semibold">{{ number_format($stats['this_month']['clicks']) }}</span>
                </div>
                <div class="flex justify-between border-t pt-3">
                    <span class="text-gray-700 font-medium">Coins Earned</span>
                    <span class="text-green-600 font-bold">{{ number_format($stats['this_month']['coins_earned'], 2) }} Coins</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-700 font-medium">Withdrawals Paid</span>
                    <span class="text-red-600 font-bold">Rs. {{ number_format($stats['this_month']['withdrawals'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- All Time -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">All Time</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-500">Impressions</span>
                    <span class="font-semibold">{{ number_format($stats['total']['impressions']) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Clicks</span>
                    <span class="font-semibold">{{ number_format($stats['total']['clicks']) }}</span>
                </div>
                <div class="flex justify-between border-t pt-3">
                    <span class="text-gray-700 font-medium">Coins Earned</span>
                    <span class="text-green-600 font-bold">{{ number_format($stats['total']['coins_earned'], 2) }} Coins</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-700 font-medium">Withdrawals Paid</span>
                    <span class="text-red-600 font-bold">Rs. {{ number_format($stats['total']['withdrawals'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Earners -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Top Earners</h2>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Earned</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wallet Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($topEarners as $index => $earner)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        @if($index < 3)
                            <span class="w-6 h-6 flex items-center justify-center rounded-full bg-yellow-100 text-yellow-800 text-xs font-bold">
                                {{ $index + 1 }}
                            </span>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $earner->user->name ?? 'Deleted User' }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-green-600">
                        {{ number_format($earner->total_earned, 2) }} Coins
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        @php
                            $wallet = \App\Models\OriporiCoinWallet::where('user_id', $earner->user_id)->first();
                        @endphp
                        Rs. {{ number_format($wallet?->balance ?? 0, 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">No earnings data yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
