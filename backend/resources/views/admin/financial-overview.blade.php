@extends('admin.layout')

@section('title', 'Financial Overview - Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Financial Overview</h1>
            <p class="text-sm text-gray-500 mt-1">Revenue, expenses, rewards & net profit — {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}</p>
        </div>
        <a href="{{ route('admin.expenses') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>

    {{-- Month Selector --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form action="{{ route('admin.financial-overview') }}" method="GET" class="flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700">Period:</label>
            <select name="month" class="border border-gray-300 rounded-lg px-3 py-2">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                @endfor
            </select>
            <select name="year" class="border border-gray-300 rounded-lg px-3 py-2">
                @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">View</button>
        </form>
    </div>

    {{-- Main P&L Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        {{-- Revenue --}}
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-green-100">Revenue</h3>
                <div class="w-10 h-10 rounded-lg bg-white/20 grid place-items-center">
                    <i class="fas fa-arrow-up text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-bold">Rs. {{ number_format($totalRevenue, 0) }}</p>
            <div class="mt-3 text-sm text-green-100 space-y-1">
                <div class="flex justify-between"><span>Merchant Ads</span><span>Rs. {{ number_format($adRevenue, 0) }}</span></div>
                <div class="flex justify-between"><span>Bookings</span><span>Rs. {{ number_format($bookingRevenue, 0) }}</span></div>
                <div class="flex justify-between"><span>Subscriptions</span><span>Rs. {{ number_format($subscriptionRevenue, 0) }}</span></div>
            </div>
        </div>

        {{-- Expenses --}}
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-red-100">Expenses</h3>
                <div class="w-10 h-10 rounded-lg bg-white/20 grid place-items-center">
                    <i class="fas fa-arrow-down text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-bold">Rs. {{ number_format($totalExpenses, 0) }}</p>
            <div class="mt-3 text-sm text-red-100 space-y-1">
                <div class="flex justify-between"><span>Operations</span><span>Rs. {{ number_format($monthlyExpenses, 0) }}</span></div>
                <div class="flex justify-between"><span>Salaries</span><span>Rs. {{ number_format($salaryExpenses, 0) }}</span></div>
            </div>
        </div>

        {{-- Rewards --}}
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-purple-100">Rewards</h3>
                <div class="w-10 h-10 rounded-lg bg-white/20 grid place-items-center">
                    <i class="fas fa-coins text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-bold">Rs. {{ number_format($rewardCost, 0) }}</p>
            <div class="mt-3 text-sm text-purple-100 space-y-1">
                <div class="flex justify-between"><span>Points Issued</span><span>{{ number_format($pointsIssued) }}</span></div>
                <div class="flex justify-between"><span>Points Redeemed</span><span>{{ number_format($pointsRedeemed) }}</span></div>
                <div class="flex justify-between"><span>Outstanding</span><span>{{ number_format($outstandingPoints) }}</span></div>
            </div>
        </div>

        {{-- Net Profit --}}
        <div class="bg-gradient-to-br {{ $netProfit >= 0 ? 'from-blue-500 to-blue-600' : 'from-orange-500 to-red-500' }} rounded-xl p-5 text-white shadow-lg">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold {{ $netProfit >= 0 ? 'text-blue-100' : 'text-orange-100' }}">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</h3>
                <div class="w-10 h-10 rounded-lg bg-white/20 grid place-items-center">
                    <i class="fas fa-{{ $netProfit >= 0 ? 'chart-line' : 'exclamation-triangle' }} text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-bold">{{ $netProfit >= 0 ? '' : '-' }}Rs. {{ number_format(abs($netProfit), 0) }}</p>
            <div class="mt-3 text-sm {{ $netProfit >= 0 ? 'text-blue-100' : 'text-orange-100' }}">
                <div class="flex justify-between">
                    <span>Revenue − Expenses − Rewards</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Detailed Breakdown --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        {{-- Revenue Breakdown --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-green-50 border-b border-green-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-hand-holding-usd text-green-500 mr-2"></i> Revenue Breakdown
                </h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-green-100 grid place-items-center"><i class="fas fa-ad text-green-600 text-sm"></i></div>
                            <span class="font-medium text-gray-700">Merchant Ads</span>
                        </div>
                        <span class="font-bold text-green-600">Rs. {{ number_format($adRevenue, 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 grid place-items-center"><i class="fas fa-calendar-check text-blue-600 text-sm"></i></div>
                            <span class="font-medium text-gray-700">Bookings</span>
                        </div>
                        <span class="font-bold text-blue-600">Rs. {{ number_format($bookingRevenue, 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 grid place-items-center"><i class="fas fa-id-card text-purple-600 text-sm"></i></div>
                            <span class="font-medium text-gray-700">Subscriptions</span>
                        </div>
                        <span class="font-bold text-purple-600">Rs. {{ number_format($subscriptionRevenue, 0) }}</span>
                    </div>
                    <div class="border-t pt-3 flex justify-between font-bold text-lg">
                        <span>Total Revenue</span>
                        <span class="text-green-600">Rs. {{ number_format($totalRevenue, 0) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Expense Breakdown --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-red-50 border-b border-red-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-receipt text-red-500 mr-2"></i> Expense Breakdown
                </h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-orange-100 grid place-items-center"><i class="fas fa-server text-orange-600 text-sm"></i></div>
                            <span class="font-medium text-gray-700">Operations</span>
                        </div>
                        <span class="font-bold text-orange-600">Rs. {{ number_format($monthlyExpenses, 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-indigo-100 grid place-items-center"><i class="fas fa-users text-indigo-600 text-sm"></i></div>
                            <span class="font-medium text-gray-700">Salaries</span>
                        </div>
                        <span class="font-bold text-indigo-600">Rs. {{ number_format($salaryExpenses, 0) }}</span>
                    </div>
                    <div class="border-t pt-3 flex justify-between font-bold text-lg">
                        <span>Total Expenses</span>
                        <span class="text-red-600">Rs. {{ number_format($totalExpenses, 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- P&L Summary --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-calculator text-gray-500 mr-2"></i> Profit & Loss Summary
            </h2>
        </div>
        <div class="p-6">
            <div class="max-w-md mx-auto space-y-3">
                <div class="flex justify-between text-lg"><span class="text-gray-600">💰 Revenue</span><span class="font-bold text-green-600">Rs. {{ number_format($totalRevenue, 0) }}</span></div>
                <div class="flex justify-between text-lg"><span class="text-gray-600">💸 Expenses</span><span class="font-bold text-red-600">- Rs. {{ number_format($totalExpenses, 0) }}</span></div>
                <div class="flex justify-between text-lg"><span class="text-gray-600">🎁 Reward Cost</span><span class="font-bold text-purple-600">- Rs. {{ number_format($rewardCost, 0) }}</span></div>
                <div class="border-t-2 pt-3 flex justify-between text-xl font-bold">
                    <span>{{ $netProfit >= 0 ? '📊 Net Profit' : '📊 Net Loss' }}</span>
                    <span class="{{ $netProfit >= 0 ? 'text-blue-600' : 'text-red-600' }}">{{ $netProfit >= 0 ? '' : '-' }}Rs. {{ number_format(abs($netProfit), 0) }}</span>
                </div>
                @if($totalRevenue > 0)
                <div class="text-center text-sm text-gray-500 mt-2">
                    Profit Margin: <span class="font-bold {{ ($netProfit / $totalRevenue * 100) >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($netProfit / $totalRevenue * 100, 1) }}%</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Upcoming Renewals --}}
    @if($upcomingRenewals->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-yellow-50 border-b border-yellow-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-clock text-yellow-500 mr-2"></i> Upcoming Renewals (30 days)
            </h2>
        </div>
        <div class="p-6">
            <div class="space-y-3">
                @foreach($upcomingRenewals as $expense)
                <div class="flex items-center justify-between p-3 {{ $expense->is_expired ? 'bg-red-50 border border-red-200' : ($expense->is_renewal_soon ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50') }} rounded-lg">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg {{ $expense->is_expired ? 'bg-red-100' : ($expense->is_renewal_soon ? 'bg-yellow-100' : 'bg-gray-200') }} grid place-items-center">
                            <i class="fas fa-{{ $expense->is_expired ? 'exclamation-triangle text-red-600' : ($expense->is_renewal_soon ? 'clock text-yellow-600' : 'info-circle text-gray-600') }} text-sm"></i>
                        </div>
                        <div>
                            <span class="font-medium text-gray-900">{{ $expense->name }}</span>
                            <span class="text-sm text-gray-500 ml-2">({{ \App\Models\PlatformExpense::CATEGORIES[$expense->category] ?? $expense->category }})</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-gray-900">Rs. {{ number_format($expense->amount, 0) }}</span>
                        <div class="text-sm {{ $expense->is_expired ? 'text-red-600 font-medium' : ($expense->is_renewal_soon ? 'text-yellow-600 font-medium' : 'text-gray-500') }}">
                            @if($expense->is_expired)
                                Expired {{ $expense->next_renewal_date->diffInDays(now()) }}d ago
                            @elseif($expense->is_renewal_soon)
                                {{ $expense->next_renewal_date->diffInDays(now()) }}d left
                            @else
                                {{ $expense->next_renewal_date->format('M d, Y') }}
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
