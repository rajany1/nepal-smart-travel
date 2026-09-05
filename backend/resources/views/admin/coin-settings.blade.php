@extends('admin.layout')

@section('title', 'Coin Settings - Admin')

@php
    $impVal = (float) ($settings->where('key', 'impression_value')->first()?->value ?? 0.05);
    $clkVal = (float) ($settings->where('key', 'click_value')->first()?->value ?? 0.50);
    $usrShare = (float) ($settings->where('key', 'user_share_percent')->first()?->value ?? 70);
    $admShare = (float) ($settings->where('key', 'admin_share_percent')->first()?->value ?? 30);
    $coinRate = (float) ($settings->where('key', 'coin_to_npr_rate')->first()?->value ?? 1);
    $minEsewa = $settings->where('key', 'min_withdrawal_esewa')->first()?->value ?? 100;
    $minKhalti = $settings->where('key', 'min_withdrawal_khalti')->first()?->value ?? 100;
    $minBank = $settings->where('key', 'min_withdrawal_bank')->first()?->value ?? 500;
    $dailyCap = $settings->where('key', 'daily_earning_cap')->first()?->value ?? 500;
    $dailyImpCap = $settings->where('key', 'daily_impression_cap')->first()?->value ?? 1000;
    $cooldown = $settings->where('key', 'impression_cooldown_minutes')->first()?->value ?? 10;
    $coinsPerImp = round($impVal * ($usrShare / 100), 4);
    $coinsPerClick = round($clkVal * ($usrShare / 100), 4);
    $lastUpdated = $settings->sortByDesc('updated_at')->first()?->updated_at;
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Oripori Coin Settings</h1>
            <p class="text-sm text-gray-500 mt-1">Configure how ad revenue is shared with report owners</p>
        </div>
        <a href="{{ route('admin.withdrawals') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
            <i class="fas fa-arrow-left mr-1"></i> Back
        </a>
    </div>

    {{-- How It Works Banner --}}
    <div class="bg-gradient-to-r from-teal-500 to-teal-600 rounded-xl p-6 mb-6 text-white">
        <h3 class="font-bold text-lg mb-3"><i class="fas fa-info-circle mr-2"></i> How Oripori Coins Work</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
            <div class="bg-white/10 rounded-lg p-3">
                <div class="font-bold">1. Partner Posts Ad</div>
                <div class="text-teal-100">Pays Rs. {{ number_format($impVal * 1000, 2) }}/1000 views (CPM)</div>
            </div>
            <div class="bg-white/10 rounded-lg p-3">
                <div class="font-bold">2. Ad Shows on Report</div>
                <div class="text-teal-100">User's report screen ma ad dekhincha</div>
            </div>
            <div class="bg-white/10 rounded-lg p-3">
                <div class="font-bold">3. User Earns Coins</div>
                <div class="text-teal-100" id="earningPreview">{{ $coinsPerImp }} Coins per view</div>
            </div>
            <div class="bg-white/10 rounded-lg p-3">
                <div class="font-bold">4. Withdraw to Rs.</div>
                <div class="text-teal-100" id="conversionPreview">100 Coins = Rs. {{ number_format(100 * $coinRate, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Current Configuration Summary --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="bg-blue-50 border-b border-blue-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-cogs text-blue-500 mr-2"></i> Current Configuration Summary
            </h2>
            <p class="text-sm text-gray-600 mt-1">Haal ma apply bhaeka settings - yaha dekhna sakincha</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">User Per View</span>
                        <span class="font-bold text-green-600 text-lg">{{ $coinsPerImp }} Coins</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">User Per Click</span>
                        <span class="font-bold text-purple-600 text-lg">{{ $coinsPerClick }} Coins</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-amber-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">Exchange Rate</span>
                        <span class="font-bold text-amber-600 text-lg">1 Coin = Rs. {{ number_format($coinRate, 2) }}</span>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">CPM (per 1000 views)</span>
                        <span class="font-bold text-blue-600 text-lg">Rs. {{ number_format($impVal * 1000, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">CPC (per click)</span>
                        <span class="font-bold text-indigo-600 text-lg">Rs. {{ number_format($clkVal, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">Revenue Split</span>
                        <span class="font-bold text-gray-700 text-lg">{{ $usrShare }}% User / {{ $admShare }}% Platform</span>
                    </div>
                </div>
            </div>
            @if($lastUpdated)
            <div class="mt-4 text-xs text-gray-400 text-right">
                <i class="fas fa-clock mr-1"></i> Last updated: {{ $lastUpdated->diffForHumans() }}
            </div>
            @endif
        </div>
    </div>

    <form id="settingsForm" method="POST" action="{{ route('admin.coin-settings.update') }}" class="space-y-6">
        @csrf

        {{-- Revenue Share Settings --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-yellow-50 border-b border-yellow-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-percentage text-yellow-500 mr-2"></i> Revenue Share
                </h2>
                <p class="text-sm text-gray-600 mt-1">Ad revenue kasari distribute hunchha</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Report Owner Gets (%)</label>
                        <div class="relative">
                            <input type="number" name="user_share_percent" step="1" min="0" max="100"
                                value="{{ $usrShare }}"
                                class="w-full text-2xl font-bold border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200"
                                id="userShareInput">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Report garni user le ads bata yeti % paucha</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Platform Keeps (%)</label>
                        <div class="relative">
                            <input type="number" name="admin_share_percent" disabled
                                value="{{ $admShare }}"
                                class="w-full text-2xl font-bold border-2 border-gray-200 rounded-xl px-4 py-3 bg-gray-50 text-gray-500"
                                id="adminShareDisplay">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">%</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Platform le rakhne % (auto: 100 - User Share)</p>
                    </div>
                </div>

                {{-- Visual Preview --}}
                <div class="mt-6 p-4 bg-gray-50 rounded-xl">
                    <p class="text-sm font-semibold text-gray-700 mb-3">Revenue Split Preview (per Rs. 100 ad spend):</p>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-green-500 rounded-l-xl py-2 text-center text-white font-bold text-sm" id="userBar" style="width: {{ $usrShare }}%">
                            <span id="userBarText">Rs. {{ $usrShare }} → User</span>
                        </div>
                        <div class="flex-1 bg-blue-500 rounded-r-xl py-2 text-center text-white font-bold text-sm" id="adminBar" style="width: {{ $admShare }}%">
                            <span id="adminBarText">Rs. {{ $admShare }} → Platform</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Coin Value Settings --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-green-50 border-b border-green-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-coins text-green-500 mr-2"></i> Coin Values
                </h2>
                <p class="text-sm text-gray-600 mt-1">Kati coins dincha per impression/click</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Per 1000 Views (CPM)</label>
                        <div class="relative">
                            <input type="number" name="impression_value" step="0.01" min="0"
                                value="{{ $impVal }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                id="impressionValueInput">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Coins</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">1000 impressions huda kati coins total</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Per Click (CPC)</label>
                        <div class="relative">
                            <input type="number" name="click_value" step="0.01" min="0"
                                value="{{ $clkVal }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-green-500 focus:ring-2 focus:ring-green-200"
                                id="clickValueInput">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">Coins</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Eutai click huda kati coins</p>
                    </div>
                </div>

                {{-- Calculation Formula --}}
                <div class="mt-6 p-4 bg-green-50 rounded-xl">
                    <p class="text-sm font-semibold text-gray-700 mb-2"><i class="fas fa-calculator mr-1"></i> Calculation Formula:</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div class="bg-white rounded-lg p-3 border border-green-200">
                            <p class="font-semibold text-green-700">Per Impression:</p>
                            <code class="text-xs" id="impressionFormula">
                                {{ $impVal }} × ({{ $usrShare }} / 100) = {{ $coinsPerImp }} Coins
                            </code>
                        </div>
                        <div class="bg-white rounded-lg p-3 border border-green-200">
                            <p class="font-semibold text-green-700">Per Click:</p>
                            <code class="text-xs" id="clickFormula">
                                {{ $clkVal }} × ({{ $usrShare }} / 100) = {{ $coinsPerClick }} Coins
                            </code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Exchange Rate --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-purple-50 border-b border-purple-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-exchange-alt text-purple-500 mr-2"></i> Exchange Rate
                </h2>
                <p class="text-sm text-gray-600 mt-1">1 Coin kati NPR ko hunchha</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">1 Coin = ? NPR</label>
                        <div class="relative">
                            <input type="number" name="coin_to_npr_rate" step="0.01" min="0.01"
                                value="{{ $coinRate }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-purple-500 focus:ring-2 focus:ring-purple-200"
                                id="coinToNprInput">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">NPR</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Default: 1 Coin = Rs. 1 (1:1 ratio)</p>
                    </div>
                    <div class="flex items-center">
                        <div class="bg-purple-50 rounded-xl p-4 border border-purple-200 w-full">
                            <p class="text-sm font-semibold text-purple-700 mb-2">Withdrawal Preview:</p>
                            <div id="withdrawalPreview" class="text-sm text-gray-700">
                                100 Coins → Rs. {{ number_format(100 * $coinRate, 2) }}<br>
                                Minimum: {{ $minEsewa }} Coins (eSewa/Khalti), {{ $minBank }} Coins (Bank)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Withdrawal Minimums --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-blue-50 border-b border-blue-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-wallet text-blue-500 mr-2"></i> Withdrawal Minimums (Coins)
                </h2>
                <p class="text-sm text-gray-600 mt-1">Kati Coins collect bhayepachi withdraw garna milcha</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-mobile-alt text-green-500 mr-1"></i> eSewa
                        </label>
                        <div class="relative">
                            <input type="number" name="min_withdrawal_esewa" step="1" min="0"
                                value="{{ $minEsewa }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">Coins</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-mobile-alt text-purple-500 mr-1"></i> Khalti
                        </label>
                        <div class="relative">
                            <input type="number" name="min_withdrawal_khalti" step="1" min="0"
                                value="{{ $minKhalti }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">Coins</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-university text-blue-500 mr-1"></i> Bank Transfer
                        </label>
                        <div class="relative">
                            <input type="number" name="min_withdrawal_bank" step="1" min="0"
                                value="{{ $minBank }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">Coins</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fraud Prevention --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-red-50 border-b border-red-100 px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-shield-alt text-red-500 mr-2"></i> Fraud Prevention
                </h2>
                <p class="text-sm text-gray-600 mt-1">Coin earning abuse rokna</p>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Daily Earning Cap</label>
                        <div class="relative">
                            <input type="number" name="daily_earning_cap" step="1" min="0"
                                value="{{ $dailyCap }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-2 focus:ring-red-200">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">Coins/day</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Per user per day max earning</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Report Impression Cap</label>
                        <div class="relative">
                            <input type="number" name="daily_impression_cap" step="1" min="0"
                                value="{{ $dailyImpCap }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-2 focus:ring-red-200">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">views/day</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Per report per day max earning views</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Cooldown Period</label>
                        <div class="relative">
                            <input type="number" name="impression_cooldown_minutes" step="1" min="0"
                                value="{{ $cooldown }}"
                                class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:border-red-500 focus:ring-2 focus:ring-red-200">
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">min</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Same user + same ad = cooldown</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-8 py-3 bg-gradient-to-r from-teal-500 to-teal-600 text-white rounded-xl hover:from-teal-600 hover:to-teal-700 font-semibold shadow-lg">
                <i class="fas fa-save mr-2"></i> Save All Settings
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    function updatePreview() {
        var userShare = parseFloat(document.getElementById('userShareInput').value) || 70;
        var adminShare = 100 - userShare;
        var impressionValue = parseFloat(document.getElementById('impressionValueInput').value) || 0.05;
        var clickValue = parseFloat(document.getElementById('clickValueInput').value) || 0.50;
        var coinToNpr = parseFloat(document.getElementById('coinToNprInput').value) || 1;

        document.getElementById('adminShareDisplay').value = adminShare;

        document.getElementById('userBar').style.width = userShare + '%';
        document.getElementById('adminBar').style.width = adminShare + '%';
        document.getElementById('userBarText').textContent = 'Rs. ' + userShare + ' → User';
        document.getElementById('adminBarText').textContent = 'Rs. ' + adminShare + ' → Platform';

        var coinsPerImpression = (impressionValue * (userShare / 100));
        var coinsPerClick = (clickValue * (userShare / 100));

        document.getElementById('impressionFormula').textContent =
            impressionValue + ' × (' + userShare + ' / 100) = ' + coinsPerImpression.toFixed(4) + ' Coins';
        document.getElementById('clickFormula').textContent =
            clickValue + ' × (' + userShare + ' / 100) = ' + coinsPerClick.toFixed(4) + ' Coins';

        document.getElementById('earningPreview').textContent =
            coinsPerImpression.toFixed(4) + ' Coins per view';

        var withdrawal100 = 100 * coinToNpr;
        document.getElementById('withdrawalPreview').innerHTML =
            '100 Coins → Rs. ' + withdrawal100.toFixed(2) + '<br>' +
            'Minimum: ' + (document.querySelector('[name=min_withdrawal_esewa]').value || 100) + ' Coins (eSewa/Khalti), ' + (document.querySelector('[name=min_withdrawal_bank]').value || 500) + ' Coins (Bank)';

        document.getElementById('conversionPreview').textContent =
            '100 Coins = Rs. ' + withdrawal100.toFixed(2);
    }

    ['userShareInput', 'impressionValueInput', 'clickValueInput', 'coinToNprInput', 'min_withdrawal_esewa', 'min_withdrawal_bank'].forEach(function(id) {
        var el = document.getElementById(id) || document.querySelector('[name=' + id + ']');
        if (el) el.addEventListener('input', updatePreview);
    });

    updatePreview();
})();
</script>
@endsection
