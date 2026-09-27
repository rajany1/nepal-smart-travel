@extends('admin.layout')

@section('title', 'Money Flow Audit - Oripori')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>Money Flow Audit
            </h1>
            <p class="text-sm text-gray-500 mt-1">पैसा कहाँबाट आउँछ र कहाँ जान्छ — Where money comes from and where it goes</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.financial-audit') }}" target="_blank"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm">
                <i class="fas fa-file-invoice mr-1"></i> Full Audit
            </a>
            <a href="{{ route('admin.earnings-report') }}"
                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    {{-- ═══════════ HEALTH CHECK ═══════════ --}}
    @if($isBacked)
    <div class="bg-green-50 border border-green-200 rounded-2xl p-5 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-green-800 text-lg">System Healthy — Coins are backed by revenue</h3>
                <p class="text-sm text-green-700">Coin liability (Rs. {{ number_format($coinLiabilityNpr, 2) }}) is less than total revenue (Rs. {{ number_format($totalAdRevenuePlusOther, 2) }}). Backing ratio: {{ number_format($backingRatio * 100, 1) }}%</p>
            </div>
        </div>
    </div>
    @else
    <div class="bg-red-50 border-2 border-red-300 rounded-2xl p-5 mb-6">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
            <div>
                <h3 class="font-bold text-red-800 text-lg">⚠️ WARNING: Coins exceed revenue backing!</h3>
                <p class="text-sm text-red-700">Coin liability (Rs. {{ number_format($coinLiabilityNpr, 2) }}) exceeds total revenue (Rs. {{ number_format($totalAdRevenuePlusOther, 2) }}). Backing ratio: {{ number_format($backingRatio * 100, 1) }}%. Users could withdraw more NPR than the platform has earned.</p>
            </div>
        </div>
    </div>
    @endif

    {{-- ═══════════ FLOW DIAGRAM ═══════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

        {{-- MONEY IN --}}
        <div class="bg-white rounded-2xl shadow-sm border border-green-100 overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-green-50 to-emerald-50 border-b border-green-100">
                <h3 class="font-bold text-green-800 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="fas fa-arrow-down text-green-600 text-xs"></i>
                    </span>
                    MONEY IN / पैसा आउने
                </h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-ad text-red-400 mr-2"></i>Ad Payments</span>
                    <span class="font-bold text-green-700">Rs. {{ number_format($adPaymentsReceived, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-bed text-blue-400 mr-2"></i>Bookings</span>
                    <span class="font-bold text-green-700">Rs. {{ number_format($bookingPaymentsReceived, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-crown text-yellow-500 mr-2"></i>Subscriptions</span>
                    <span class="font-bold text-green-700">Rs. {{ number_format($subscriptionPaymentsReceived, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-star text-orange-400 mr-2"></i>Featured Listings</span>
                    <span class="font-bold text-green-700">Rs. {{ number_format($featuredPaymentsReceived, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-wallet text-purple-400 mr-2"></i>Partner Top-ups</span>
                    <span class="font-bold text-green-700">Rs. {{ number_format($partnerTopupsReceived, 2) }}</span>
                </div>
                <div class="border-t-2 border-green-200 pt-3 mt-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-green-800">TOTAL MONEY IN</span>
                        <span class="text-xl font-extrabold text-green-600">Rs. {{ number_format($totalMoneyIn, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- COIN ECONOMY (center) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-primary-100 overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-primary-50 to-teal-50 border-b border-primary-100">
                <h3 class="font-bold text-primary-800 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
                        <i class="fas fa-coins text-primary-600 text-xs"></i>
                    </span>
                    COIN ECONOMY / कोइन अर्थतन्त्र
                </h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Coins Issued</span>
                    <span class="font-bold">{{ number_format($totalCoinsIssued, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Lifetime Earned</span>
                    <span class="font-semibold">{{ number_format($totalCoinsEarned, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600">Total Withdrawn</span>
                    <span class="font-semibold">{{ number_format($totalCoinsWithdrawn, 2) }}</span>
                </div>
                <div class="border-t border-gray-200 pt-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-gray-800">Coin Liability (NPR)</span>
                        <span class="text-lg font-extrabold {{ $isBacked ? 'text-primary-600' : 'text-red-600' }}">
                            Rs. {{ number_format($coinLiabilityNpr, 2) }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ number_format($totalCoinsIssued, 2) }} coins × Rs. {{ number_format($coinToNpr, 2) }} rate
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 mt-3">
                    <div class="text-xs text-gray-500 mb-2">Conversion Settings</div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>1 Coin = Rs. {{ number_format($coinToNpr, 2) }}</div>
                        <div>User Share: {{ number_format($userSharePercent, 0) }}%</div>
                        <div>Admin Share: {{ number_format($adminSharePercent, 0) }}%</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MONEY OUT --}}
        <div class="bg-white rounded-2xl shadow-sm border border-red-100 overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-red-50 to-rose-50 border-b border-red-100">
                <h3 class="font-bold text-red-800 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-600 text-xs"></i>
                    </span>
                    MONEY OUT / पैसा जाने
                </h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-user text-blue-400 mr-2"></i>User Withdrawals</span>
                    <span class="font-bold text-red-700">Rs. {{ number_format($userWithdrawalsPaid, 2) }} <span class="text-xs text-gray-400">({{ number_format($userWithdrawalsCoins, 0) }} coins)</span></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-handshake text-purple-400 mr-2"></i>Partner Withdrawals</span>
                    <span class="font-bold text-red-700">Rs. {{ number_format($partnerWithdrawalsPaid, 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-building text-orange-400 mr-2"></i>Platform Expenses</span>
                    <span class="font-bold text-red-700">Rs. {{ number_format($platformExpensesPaid, 2) }}</span>
                </div>
                @if($platformExpensesUnpaid > 0)
                <div class="flex justify-between items-center pl-6">
                    <span class="text-xs text-gray-400"><i class="fas fa-clock mr-1"></i>Unpaid (due)</span>
                    <span class="text-xs text-orange-500">Rs. {{ number_format($platformExpensesUnpaid, 2) }}</span>
                </div>
                @endif
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600"><i class="fas fa-users text-teal-400 mr-2"></i>Employee Salaries</span>
                    <span class="font-bold text-red-700">Rs. {{ number_format($salariesPaid, 2) }}</span>
                </div>
                @if($salariesPending > 0)
                <div class="flex justify-between items-center pl-6">
                    <span class="text-xs text-gray-400"><i class="fas fa-clock mr-1"></i>Pending salaries</span>
                    <span class="text-xs text-orange-500">Rs. {{ number_format($salariesPending, 2) }}</span>
                </div>
                @endif
                <div class="border-t-2 border-red-200 pt-3 mt-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-red-800">TOTAL MONEY OUT</span>
                        <span class="text-xl font-extrabold text-red-600">Rs. {{ number_format($totalMoneyOut, 2) }}</span>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 mt-3">
                    <div class="text-xs text-gray-500 mb-2">Net Position (Revenue − All Expenses)</div>
                    @php $net = $totalMoneyIn - $totalMoneyOut; @endphp
                    <div class="text-lg font-extrabold {{ $net >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        Rs. {{ number_format($net, 2) }}
                    </div>
                    <div class="text-xs {{ $net >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $net >= 0 ? '✅ Surplus — platform is profitable' : '⚠️ Deficit — platform is losing money' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ COIN CREATION FLOW ═══════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-cogs text-amber-600"></i>
                How Coins Are Created / कोइन कसरी बन्छ
            </h3>
            <p class="text-xs text-gray-500 mt-1">Coins are created when users view or click ads — NOT when partners pay</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Impression Flow --}}
                <div class="border border-gray-200 rounded-xl p-5">
                    <h4 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <span class="w-6 h-6 rounded bg-blue-100 flex items-center justify-center"><i class="fas fa-eye text-blue-600 text-xs"></i></span>
                        1 Ad Impression
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Partner pays (gross):</span>
                            <span class="font-semibold">Rs. {{ number_format($grossPerImpression, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Admin keeps ({{ number_format($adminSharePercent, 0) }}%):</span>
                            <span class="font-bold text-green-700">Rs. {{ number_format($adminKeepsPerImpression, 4) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Coins created ({{ number_format($userSharePercent, 0) }}%):</span>
                            <span class="font-bold text-primary-600">{{ number_format($userGetsPerImpression, 4) }} coins</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Coin value (NPR):</span>
                            <span class="font-semibold">Rs. {{ number_format($userGetsPerImpression * $coinToNpr, 4) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 mt-2">
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Total impressions:</span>
                                <span class="font-semibold">{{ number_format($totalImpressions) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Total coins from impressions:</span>
                                <span class="font-bold text-primary-600">{{ number_format($totalImpressions * $userGetsPerImpression, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Click Flow --}}
                <div class="border border-gray-200 rounded-xl p-5">
                    <h4 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <span class="w-6 h-6 rounded bg-indigo-100 flex items-center justify-center"><i class="fas fa-mouse-pointer text-indigo-600 text-xs"></i></span>
                        1 Ad Click
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Partner pays (gross):</span>
                            <span class="font-semibold">Rs. {{ number_format($grossPerClick, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Admin keeps ({{ number_format($adminSharePercent, 0) }}%):</span>
                            <span class="font-bold text-green-700">Rs. {{ number_format($adminKeepsPerClick, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Coins created ({{ number_format($userSharePercent, 0) }}%):</span>
                            <span class="font-bold text-primary-600">{{ number_format($userGetsPerClick, 2) }} coins</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Coin value (NPR):</span>
                            <span class="font-semibold">Rs. {{ number_format($userGetsPerClick * $coinToNpr, 2) }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-2 mt-2">
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Total clicks:</span>
                                <span class="font-semibold">{{ number_format($totalClicks) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-gray-500">Total coins from clicks:</span>
                                <span class="font-bold text-primary-600">{{ number_format($totalClicks * $userGetsPerClick, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ REVENUE vs COINS COMPARISON ═══════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-purple-50 to-pink-50 border-b border-purple-100">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-balance-scale text-purple-600"></i>
                Ledger vs Actual Coins / लेजर बनाम वास्तविक कोइन
            </h3>
        </div>
        <div class="p-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">What</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Ledger Says</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Actually Created</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">Difference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">User Share from Ads</td>
                        <td class="px-4 py-3 text-sm text-right">Rs. {{ number_format($ledgerUserShareTotal, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right font-bold text-primary-600">Rs. {{ number_format($actualCoinsValue, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right">
                            @php $diff = $ledgerUserShareTotal - $actualCoinsValue; @endphp
                            <span class="{{ $diff > 0 ? 'text-red-600' : 'text-green-600' }} font-bold">
                                {{ $diff > 0 ? '-' : '+' }}Rs. {{ number_format(abs($diff), 2) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">Total Coins Withdrawn</td>
                        <td class="px-4 py-3 text-sm text-right">—</td>
                        <td class="px-4 py-3 text-sm text-right">Rs. {{ number_format($userWithdrawalsPaid, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right text-red-600 font-bold">-Rs. {{ number_format($userWithdrawalsPaid, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">Net Coin Liability Remaining</td>
                        <td class="px-4 py-3 text-sm text-right">—</td>
                        <td class="px-4 py-3 text-sm text-right font-extrabold {{ $isBacked ? 'text-primary-600' : 'text-red-600' }}">
                            Rs. {{ number_format($coinLiabilityNpr, 2) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-right">
                            <span class="text-xs text-gray-500">must be ≤ revenue</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════ QUICK MATH ═══════════ --}}
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 text-white mb-6">
        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
            <i class="fas fa-calculator text-green-400"></i>
            Quick Math / सानो हिसाब
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div class="text-sm text-gray-300">If 1,000 users view 10 ads each per day:</div>
                <div class="bg-gray-800 rounded-lg p-3 font-mono text-sm space-y-1">
                    <div>Impressions/day: <span class="text-green-400">10,000</span></div>
                    <div>Coins created/day: <span class="text-green-400">{{ number_format(10000 * $userGetsPerImpression, 2) }}</span></div>
                    <div>NPR liability/day: <span class="text-yellow-400">Rs. {{ number_format(10000 * $userGetsPerImpression * $coinToNpr, 2) }}</span></div>
                    <div>Admin revenue/day: <span class="text-green-400">Rs. {{ number_format(10000 * $adminKeepsPerImpression, 4) }}</span></div>
                    <div class="border-t border-gray-700 pt-1 text-xs text-gray-400">
                        Revenue covers: {{ number_format((10000 * $adminKeepsPerImpression) / max(10000 * $userGetsPerImpression * $coinToNpr, 0.01) * 100, 1) }}% of coin liability
                    </div>
                </div>
            </div>
            <div class="space-y-3">
                <div class="text-sm text-gray-300">If 500 users click 2 ads each per day:</div>
                <div class="bg-gray-800 rounded-lg p-3 font-mono text-sm space-y-1">
                    <div>Clicks/day: <span class="text-green-400">1,000</span></div>
                    <div>Coins created/day: <span class="text-green-400">{{ number_format(1000 * $userGetsPerClick, 2) }}</span></div>
                    <div>NPR liability/day: <span class="text-yellow-400">Rs. {{ number_format(1000 * $userGetsPerClick * $coinToNpr, 2) }}</span></div>
                    <div>Admin revenue/day: <span class="text-green-400">Rs. {{ number_format(1000 * $adminKeepsPerClick, 2) }}</span></div>
                    <div class="border-t border-gray-700 pt-1 text-xs text-gray-400">
                        Revenue covers: {{ number_format((1000 * $adminKeepsPerClick) / max(1000 * $userGetsPerClick * $coinToNpr, 0.01) * 100, 1) }}% of coin liability
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ BACKING RATIO ═══════════ --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-shield-alt text-primary-600"></i>
                Revenue Backing Status / राजस्व समर्थन
            </h3>
        </div>
        <div class="p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="flex-1">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Coin Liability (what users could withdraw)</span>
                        <span class="font-bold">Rs. {{ number_format($coinLiabilityNpr, 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="bg-primary-500 h-4 rounded-full transition-all" style="width: {{ min($backingRatio * 100, 100) }}%"></div>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-600">Total Revenue (money received)</span>
                        <span class="font-bold">Rs. {{ number_format($totalAdRevenuePlusOther, 2) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4">
                        <div class="bg-green-500 h-4 rounded-full" style="width: 100%"></div>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4">
                <span class="text-3xl font-extrabold {{ $isBacked ? 'text-green-600' : 'text-red-600' }}">
                    {{ number_format($backingRatio * 100, 1) }}%
                </span>
                <div class="text-sm text-gray-500 mt-1">
                    {{ $isBacked ? '✅ System is solvent — coins are backed by revenue' : '⚠️ System is insolvent — coins exceed revenue' }}
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════ RECOMMENDATION ═══════════ --}}
    <div class="bg-gradient-to-r from-primary-50 to-teal-50 rounded-2xl p-6 border border-primary-100">
        <h3 class="font-bold text-primary-800 mb-3 flex items-center gap-2">
            <i class="fas fa-lightbulb text-primary-600"></i>
            Recommendation / सिफारिस
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">Current Issues:</h4>
                <ul class="space-y-1 text-gray-600">
                    <li>• Coins are created independently of ad revenue</li>
                    <li>• No automatic check if coins exceed revenue</li>
                    <li>• Click coins ({{ number_format($userGetsPerClick, 2) }}) vs ledger user share (Rs. {{ number_format($grossPerClick * ($userSharePercent/100), 2) }}) — {{ number_format($grossPerClick * ($userSharePercent/100) / max($userGetsPerClick * $coinToNpr, 0.01), 0) }}x gap</li>
                    <li>• Admin can change coin values without revenue validation</li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">What to fix:</h4>
                <ul class="space-y-1 text-gray-600">
                    <li>• Link coin creation formula to actual ad revenue</li>
                    <li>• Add daily cap: coins created ≤ revenue received that day</li>
                    <li>• Auto-pause coin creation if backing ratio &lt; 100%</li>
                    <li>• Require admin approval for coin setting changes</li>
                </ul>
            </div>
        </div>
    </div>

</div>
@endsection
