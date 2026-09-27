@extends('admin.layout')

@section('title', 'Earnings Report - Admin')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/tailwind.css">
<style>
    .preset-btn { transition: all 0.15s ease; }
    .preset-btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .preset-btn.active { background: linear-gradient(135deg, #009688 0%, #00796B 100%); color: white; border-color: transparent; }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .nepali-date { font-family: 'Muktinath', 'Noto Sans Devanagari', sans-serif; }
    .flatpickr-day.selected { background: #009688 !important; border-color: #009688 !important; }
    .flatpickr-day:hover { background: #B2DFDB !important; }
    .filter-section { background: linear-gradient(135deg, #f0fdfa 0%, #f8fafc 100%); }
    .section-divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); }
</style>

<div class="max-w-7xl mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                <i class="fas fa-chart-line text-primary-500 mr-2"></i>Oripori Coins & Partner Offers
            </h1>
            <p class="text-sm text-gray-500 mt-1">Earnings analytics and financial overview</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.money-flow') }}" target="_blank"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                <i class="fas fa-money-bill-wave mr-1"></i> Money Flow
            </a>
            <a href="{{ route('admin.financial-audit') }}" target="_blank"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm">
                <i class="fas fa-file-invoice mr-1"></i> Full Audit
            </a>
            <a href="{{ route('admin.withdrawals') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    {{-- Date Range Filter Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
        {{-- Filter Header --}}
        <div class="px-6 py-4 filter-section border-b border-gray-100">
            <div class="flex items-center gap-2 mb-1">
                <i class="fas fa-calendar-alt text-primary-600"></i>
                <h3 class="font-semibold text-gray-800">मिति छान्नुहोस् / Date Filter</h3>
            </div>
            <p class="text-xs text-gray-500">Quick select or pick a custom date range</p>
        </div>

        <div class="p-6">
            {{-- Quick Preset Buttons --}}
            <div class="flex flex-wrap gap-2 mb-5">
                @php
                    $presets = [
                        ['label' => 'आज', 'sublabel' => 'Today', 'from' => now()->format('Y-m-d'), 'to' => now()->format('Y-m-d')],
                        ['label' => 'यो हप्ता', 'sublabel' => 'This Week', 'from' => now()->startOfWeek()->format('Y-m-d'), 'to' => now()->format('Y-m-d')],
                        ['label' => 'यो महिना', 'sublabel' => 'This Month', 'from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d')],
                        ['label' => 'गत महिना', 'sublabel' => 'Last Month', 'from' => now()->subMonth()->startOfMonth()->format('Y-m-d'), 'to' => now()->subMonth()->endOfMonth()->format('Y-m-d')],
                        ['label' => 'यो वर्ष', 'sublabel' => 'This Year', 'from' => now()->startOfYear()->format('Y-m-d'), 'to' => now()->format('Y-m-d')],
                        ['label' => 'सबै', 'sublabel' => 'All Time', 'from' => '', 'to' => ''],
                    ];
                @endphp
                @foreach($presets as $p)
                    @php
                        $isActive = ($dateFrom ?? '') === $p['from'] && ($dateTo ?? '') === $p['to']
                            || ($p['from'] === '' && $p['to'] === '' && empty($dateFrom) && empty($dateTo));
                    @endphp
                    <a href="{{ $p['from'] ? route('admin.earnings-report', ['from' => $p['from'], 'to' => $p['to']]) : route('admin.earnings-report') }}"
                       class="preset-btn inline-flex flex-col items-center px-4 py-2.5 rounded-xl border text-sm font-medium {{ $isActive ? 'active' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }}">
                        <span class="text-xs font-bold">{{ $p['label'] }}</span>
                        <span class="text-[10px] opacity-70">{{ $p['sublabel'] }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Custom Date Picker --}}
            <form method="GET" action="{{ route('admin.earnings-report') }}" id="dateFilterForm">
                <div class="flex flex-wrap items-end gap-4">
                    {{-- From Date --}}
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            <i class="fas fa-calendar-check text-primary-500 mr-1"></i> From / सुरु
                        </label>
                        <div class="relative">
                            <input type="text" id="dateFrom" name="from" placeholder="YYYY-MM-DD"
                                value="{{ $dateFrom ?? '' }}"
                                class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-200 text-sm py-2.5 pr-10 transition-all"
                                autocomplete="off">
                            <i class="fas fa-calendar absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        </div>
                        <div id="dateFromBS" class="text-[11px] text-primary-600 mt-1 font-medium nepali-date min-h-[16px]">
                            @if($dateFrom ?? null)
                                @php
                                    // AD to BS conversion will happen via JS
                                @endphp
                            @endif
                        </div>
                    </div>

                    {{-- Separator --}}
                    <div class="pb-3 text-gray-400">
                        <i class="fas fa-arrow-right text-lg"></i>
                    </div>

                    {{-- To Date --}}
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                            <i class="fas fa-calendar-check text-primary-500 mr-1"></i> To / सम्म
                        </label>
                        <div class="relative">
                            <input type="text" id="dateTo" name="to" placeholder="YYYY-MM-DD"
                                value="{{ $dateTo ?? '' }}"
                                class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-200 text-sm py-2.5 pr-10 transition-all"
                                autocomplete="off">
                            <i class="fas fa-calendar absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                        </div>
                        <div id="dateToBS" class="text-[11px] text-primary-600 mt-1 font-medium nepali-date min-h-[16px]"></div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex gap-2 pb-3">
                        <button type="submit"
                            class="px-6 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-xl hover:from-primary-600 hover:to-primary-700 text-sm font-semibold shadow-sm transition-all">
                            <i class="fas fa-search mr-1"></i> Search
                        </button>
                        @if($dateFrom || $dateTo)
                            <a href="{{ route('admin.earnings-report') }}"
                                class="px-4 py-2.5 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200 text-sm font-medium transition-all">
                                <i class="fas fa-times mr-1"></i> Clear
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Custom Date Range Result --}}
    @if($customStats)
    <div class="bg-white rounded-2xl shadow-sm border border-primary-100 mb-6 overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-primary-50 to-primary-100/50 border-b border-primary-100">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-primary-800 text-lg">
                        <i class="fas fa-chart-bar mr-2"></i>{{ $customStats['label'] }}
                    </h3>
                    <p class="text-xs text-primary-600 mt-0.5">मिति अनुसारको विवरण / Filtered Results</p>
                </div>
                <div class="bg-white rounded-lg px-3 py-1.5 shadow-sm">
                    <span class="text-xs text-gray-500">Range</span>
                    <span class="block text-sm font-bold text-primary-700">
                        {{ \Carbon\Carbon::parse($customStats['from'])->diffInDays(\Carbon\Carbon::parse($customStats['to'])) + 1 }} days
                    </span>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="stat-card text-center p-4 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-eye text-blue-600 text-sm"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($customStats['impressions']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Impressions</div>
                </div>
                <div class="stat-card text-center p-4 rounded-xl bg-gray-50 border border-gray-100">
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-indigo-100 flex items-center justify-center">
                        <i class="fas fa-mouse-pointer text-indigo-600 text-sm"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($customStats['clicks']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Clicks</div>
                </div>
                <div class="stat-card text-center p-4 rounded-xl bg-green-50 border border-green-100">
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="fas fa-coins text-green-600 text-sm"></i>
                    </div>
                    <div class="text-2xl font-bold text-green-600">{{ number_format($customStats['coins_earned'], 2) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Coins Earned</div>
                </div>
                <div class="stat-card text-center p-4 rounded-xl bg-blue-50 border border-blue-100">
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-blue-600 text-sm"></i>
                    </div>
                    <div class="text-2xl font-bold text-blue-600">{{ number_format($customStats['offers_claimed']) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Offers Claimed</div>
                </div>
            </div>
            <div class="section-divider my-4"></div>
            <div class="grid grid-cols-4 gap-4">
                <div class="text-center p-3">
                    <div class="text-lg font-bold text-orange-600">Rs. {{ number_format($customStats['offers_value'], 2) }}</div>
                    <div class="text-xs text-gray-500 mt-1"><i class="fas fa-tag mr-1"></i>Offer Value</div>
                </div>
                <div class="text-center p-3">
                    <div class="text-lg font-bold text-green-700">Rs. {{ number_format($customStats['offers_commission'], 2) }}</div>
                    <div class="text-xs text-gray-500 mt-1"><i class="fas fa-building mr-1"></i>Admin Commission</div>
                </div>
                <div class="text-center p-3">
                    <div class="text-lg font-bold text-purple-600">Rs. {{ number_format($customStats['offers_partner'], 2) }}</div>
                    <div class="text-xs text-gray-500 mt-1"><i class="fas fa-handshake mr-1"></i>Partner Earnings</div>
                </div>
                <div class="text-center p-3">
                    <div class="text-lg font-bold text-red-600">Rs. {{ number_format($customStats['ad_revenue'], 2) }}</div>
                    <div class="text-xs text-gray-500 mt-1"><i class="fas fa-ad mr-1"></i>Ad Revenue</div>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4 mt-2">
                <div class="text-center p-3">
                    <div class="text-sm font-bold text-green-700">Rs. {{ number_format($customStats['ad_admin_share'], 2) }}</div>
                    <div class="text-xs text-gray-500">Ad Admin Share</div>
                </div>
                <div class="text-center p-3">
                    <div class="text-sm font-bold text-blue-600">{{ number_format($customStats['ad_impressions']) }} / {{ number_format($customStats['ad_clicks']) }}</div>
                    <div class="text-xs text-gray-500">Ad Impressions / Clicks</div>
                </div>
                <div class="text-center p-3">
                    <div class="text-sm font-bold text-red-600">Rs. {{ number_format($customStats['ad_payments'], 2) }}</div>
                    <div class="text-xs text-gray-500">Ad Payments Received</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Stats Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        {{-- Today --}}
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                        <i class="fas fa-sun text-amber-600 text-xs"></i>
                    </span>
                    आज / Today
                </h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Impressions</span>
                    <span class="font-semibold text-sm">{{ number_format($stats['today']['impressions']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Clicks</span>
                    <span class="font-semibold text-sm">{{ number_format($stats['today']['clicks']) }}</span>
                </div>
                <div class="section-divider my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Coins Earned</span>
                    <span class="text-green-600 font-bold text-sm">{{ number_format($stats['today']['coins_earned'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Offers Claimed</span>
                    <span class="text-blue-600 font-bold text-sm">{{ number_format($stats['today']['offers_claimed_today']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Offer Value</span>
                    <span class="text-orange-600 font-bold text-sm">Rs. {{ number_format($stats['today']['offers_value_today'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Admin Commission</span>
                    <span class="text-green-700 font-bold text-sm">Rs. {{ number_format($stats['today']['offers_commission_today'], 2) }}</span>
                </div>
                <div class="section-divider my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500"><i class="fas fa-ad mr-1 text-red-400"></i>Ad Revenue</span>
                    <span class="font-semibold text-sm">Rs. {{ number_format($stats['today']['ad_revenue_today'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Ad Admin Share</span>
                    <span class="text-green-700 font-bold text-sm">Rs. {{ number_format($stats['today']['ad_admin_share_today'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Ad Payments</span>
                    <span class="font-semibold text-sm">Rs. {{ number_format($stats['today']['ad_payments_today'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Active Campaigns</span>
                    <span class="font-semibold text-sm text-red-600">{{ number_format($stats['today']['active_campaigns']) }}</span>
                </div>
            </div>
        </div>

        {{-- This Month --}}
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-blue-100">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="fas fa-calendar text-blue-600 text-xs"></i>
                    </span>
                    यो महिना / This Month
                </h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Impressions</span>
                    <span class="font-semibold text-sm">{{ number_format($stats['this_month']['impressions']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Clicks</span>
                    <span class="font-semibold text-sm">{{ number_format($stats['this_month']['clicks']) }}</span>
                </div>
                <div class="section-divider my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Coins Earned</span>
                    <span class="text-green-600 font-bold text-sm">{{ number_format($stats['this_month']['coins_earned'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Offers Claimed</span>
                    <span class="text-blue-600 font-bold text-sm">{{ number_format($stats['this_month']['offers_claimed_month']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Offer Value</span>
                    <span class="text-orange-600 font-bold text-sm">Rs. {{ number_format($stats['this_month']['offers_value_month'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Admin Commission</span>
                    <span class="text-green-700 font-bold text-sm">Rs. {{ number_format($stats['this_month']['offers_commission_month'], 2) }}</span>
                </div>
                <div class="section-divider my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500"><i class="fas fa-ad mr-1 text-red-400"></i>Ad Revenue</span>
                    <span class="font-semibold text-sm">Rs. {{ number_format($stats['this_month']['ad_revenue_month'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Ad Admin Share</span>
                    <span class="text-green-700 font-bold text-sm">Rs. {{ number_format($stats['this_month']['ad_admin_share_month'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Ad Payments</span>
                    <span class="font-semibold text-sm">Rs. {{ number_format($stats['this_month']['ad_payments_month'], 2) }}</span>
                </div>
            </div>
        </div>

        {{-- All Time --}}
        <div class="stat-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 bg-gradient-to-r from-purple-50 to-pink-50 border-b border-purple-100">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center">
                        <i class="fas fa-infinity text-purple-600 text-xs"></i>
                    </span>
                    सबै / All Time
                </h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Impressions</span>
                    <span class="font-semibold text-sm">{{ number_format($stats['total']['impressions']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Clicks</span>
                    <span class="font-semibold text-sm">{{ number_format($stats['total']['clicks']) }}</span>
                </div>
                <div class="section-divider my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Coins Earned</span>
                    <span class="text-green-600 font-bold text-sm">{{ number_format($stats['total']['coins_earned'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Total Offers</span>
                    <span class="text-blue-600 font-bold text-sm">{{ number_format($stats['total']['offers_total']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Total Claimed</span>
                    <span class="text-blue-600 font-bold text-sm">{{ number_format($stats['total']['offers_claimed_total']) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Total Offer Value</span>
                    <span class="text-orange-600 font-bold text-sm">Rs. {{ number_format($stats['total']['offers_value_total'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Admin Commission</span>
                    <span class="text-green-700 font-bold text-sm">Rs. {{ number_format($stats['total']['offers_commission_total'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 font-medium">Partner Earnings</span>
                    <span class="text-purple-600 font-bold text-sm">Rs. {{ number_format($stats['total']['offers_partner_total'], 2) }}</span>
                </div>
                <div class="section-divider my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500"><i class="fas fa-ad mr-1 text-red-400"></i>Ad Revenue (Gross)</span>
                    <span class="font-semibold text-sm">Rs. {{ number_format($stats['total']['ad_revenue_total'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Ad Admin Share</span>
                    <span class="text-green-700 font-bold text-sm">Rs. {{ number_format($stats['total']['ad_admin_share_total'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Ad User Share (Paid)</span>
                    <span class="text-blue-600 font-bold text-sm">Rs. {{ number_format($stats['total']['ad_user_share_total'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Ad Payments Received</span>
                    <span class="font-semibold text-sm">Rs. {{ number_format($stats['total']['ad_payments_total'], 2) }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-500">Total Campaigns</span>
                    <span class="font-semibold text-sm text-red-600">{{ number_format($stats['total']['ad_campaigns_total']) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Earners --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <span class="w-8 h-8 rounded-full bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-trophy text-yellow-600 text-xs"></i>
                </span>
                शीर्ष कमाउने / Top Earners
            </h2>
        </div>
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50/80">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Rank</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Earned</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Wallet Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($topEarners as $index => $earner)
                <tr class="hover:bg-gray-50/50 transition-colors">
                    <td class="px-6 py-3.5 text-sm font-medium text-gray-900">
                        @if($index === 0)
                            <span class="w-7 h-7 flex items-center justify-center rounded-full bg-yellow-400 text-white text-xs font-bold shadow-sm">1</span>
                        @elseif($index === 1)
                            <span class="w-7 h-7 flex items-center justify-center rounded-full bg-gray-300 text-white text-xs font-bold shadow-sm">2</span>
                        @elseif($index === 2)
                            <span class="w-7 h-7 flex items-center justify-center rounded-full bg-amber-600 text-white text-xs font-bold shadow-sm">3</span>
                        @else
                            <span class="text-gray-500 text-sm ml-1">{{ $index + 1 }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3.5">
                        <div class="text-sm font-medium text-gray-900">{{ $earner->user->name ?? 'Deleted User' }}</div>
                    </td>
                    <td class="px-6 py-3.5 text-sm font-bold text-green-600">
                        {{ number_format($earner->total_earned, 2) }} Coins
                    </td>
                    <td class="px-6 py-3.5 text-sm text-gray-700">
                        @php
                            $wallet = \App\Models\OriporiCoinWallet::where('user_id', $earner->user_id)->first();
                        @endphp
                        Rs. {{ number_format($wallet?->balance ?? 0, 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-2 block"></i>
                        No earnings data yet.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize flatpickr
    const fromPicker = flatpickr('#dateFrom', {
        dateFormat: 'Y-m-d',
        maxDate: 'today',
        onChange: function(selectedDates, dateStr) {
            updateBSDate('dateFrom', 'dateFromBS', dateStr);
            if (dateStr && !document.getElementById('dateTo').value) {
                document.getElementById('dateTo')._flatpickr.setDate(selectedDates[0], true);
            }
        }
    });

    const toPicker = flatpickr('#dateTo', {
        dateFormat: 'Y-m-d',
        maxDate: 'today',
        onChange: function(selectedDates, dateStr) {
            updateBSDate('dateTo', 'dateToBS', dateStr);
        }
    });

    // Show initial BS dates if values exist
    const fromVal = document.getElementById('dateFrom').value;
    const toVal = document.getElementById('dateTo').value;
    if (fromVal) updateBSDate('dateFrom', 'dateFromBS', fromVal);
    if (toVal) updateBSDate('dateTo', 'dateToBS', toVal);
});

// AD to BS converter (Bikram Sambat)
// Simplified conversion using lookup table for years 2070-2090 BS
const bsMonthDays = [
    [31,31,32,31,31,31,30,29,30,29,30,30], // 2070
    [31,31,32,31,32,30,30,29,30,29,30,30], // 2071
    [31,32,31,32,31,30,30,30,29,29,30,31], // 2072
    [30,32,31,32,31,30,30,30,29,30,29,31], // 2073
    [31,31,32,31,31,31,30,29,30,29,30,30], // 2074
    [31,31,32,31,32,30,30,29,30,29,30,30], // 2075
    [31,32,31,32,31,30,30,30,29,29,30,31], // 2076
    [31,31,32,31,31,31,30,29,30,29,30,30], // 2077
    [31,32,31,32,31,30,30,29,30,29,30,30], // 2078
    [31,32,31,32,31,30,30,30,29,29,30,31], // 2079
    [31,31,32,31,31,31,30,29,30,29,30,30], // 2080
    [31,31,32,31,32,30,30,29,30,29,30,30], // 2081
    [31,32,31,32,31,30,30,30,29,29,30,31], // 2082
    [30,32,31,32,31,30,30,30,29,30,29,31], // 2083
    [31,31,32,31,31,31,30,29,30,29,30,30], // 2084
    [31,31,32,31,32,30,30,29,30,29,30,30], // 2085
    [31,32,31,32,31,30,30,30,29,29,30,31], // 2086
    [31,31,32,31,31,31,30,29,30,29,30,30], // 2087
    [31,32,31,32,31,30,30,29,30,29,30,30], // 2088
    [31,32,31,32,31,30,30,30,29,29,30,31], // 2089
    [31,31,32,31,31,31,30,29,30,29,30,30], // 2090
];

const bsMonths = ['बैशाख','जेठ','असार','श्रावण','भाद्र','आश्विन','कार्तिक','मंसिर','पौष','माघ','फाल्गुन','चैत्र'];
const bsYearStartAD = [2013,2014,2015,2016,2017,2018,2019,2020,2021,2022,2023,2024,2025,2026,2027,2028,2029,2030,2031,2032,2033];

function adToBS(adDateStr) {
    if (!adDateStr) return '';
    const parts = adDateStr.split('-');
    const y = parseInt(parts[0]);
    const m = parseInt(parts[1]);
    const d = parseInt(parts[2]);

    // Find BS year
    let bsYear = 0;
    for (let i = 0; i < bsYearStartAD.length; i++) {
        if (y === bsYearStartAD[i] || (y === bsYearStartAD[i] + 1 && m >= 4)) {
            bsYear = 2070 + i;
            break;
        }
    }
    if (bsYear === 0 && y >= 2013 && y <= 2033) {
        bsYear = 2070 + (y - 2013);
    }
    if (bsYear === 0) return '';

    // Calculate days from BS year start (Baisakh 1)
    const bsYearIdx = bsYear - 2070;
    if (bsYearIdx < 0 || bsYearIdx >= bsMonthDays.length) return '';

    // Approximate: BS year starts ~April 13-14 of AD year
    const bsStartAD = new Date(y, 3, 14); // April 14
    const targetAD = new Date(y, m - 1, d);
    let dayDiff = Math.floor((targetAD - bsStartAD) / (1000 * 60 * 60 * 24));
    if (dayDiff < 0) {
        // Before Baisakh 1, go back to previous BS year
        const prevIdx = bsYearIdx - 1;
        if (prevIdx >= 0) {
            const prevYearDays = bsMonthDays[prevIdx].reduce((a, b) => a + b, 0);
            dayDiff += prevYearDays;
            return adToBSWithYear(adDateStr, bsYear - 1);
        }
        return '';
    }

    let bsMonth = 0;
    const yearDays = bsMonthDays[bsYearIdx];
    for (let i = 0; i < 12; i++) {
        if (dayDiff < yearDays[i]) {
            bsMonth = i;
            break;
        }
        dayDiff -= yearDays[i];
        if (i === 11) bsMonth = 11;
    }

    const bsDay = dayDiff + 1;
    return bsYear + ' ' + bsMonths[bsMonth] + ' ' + bsDay;
}

function adToBSWithYear(adDateStr, bsYear) {
    const parts = adDateStr.split('-');
    const y = parseInt(parts[0]);
    const m = parseInt(parts[1]);
    const d = parseInt(parts[2]);
    const bsYearIdx = bsYear - 2070;
    if (bsYearIdx < 0 || bsYearIdx >= bsMonthDays.length) return '';

    const bsStartAD = new Date(bsYear - 2070 + 2013, 3, 14);
    const targetAD = new Date(y, m - 1, d);
    let dayDiff = Math.floor((targetAD - bsStartAD) / (1000 * 60 * 60 * 24));
    if (dayDiff < 0) return '';

    let bsMonth = 0;
    const yearDays = bsMonthDays[bsYearIdx];
    for (let i = 0; i < 12; i++) {
        if (dayDiff < yearDays[i]) { bsMonth = i; break; }
        dayDiff -= yearDays[i];
        if (i === 11) bsMonth = 11;
    }
    return bsYear + ' ' + bsMonths[bsMonth] + ' ' + (dayDiff + 1);
}

function updateBSDate(inputId, displayId, adDate) {
    const el = document.getElementById(displayId);
    if (!adDate) { el.textContent = ''; return; }
    const bs = adToBS(adDate);
    el.textContent = bs ? '📅 ' + bs + ' BS' : '';
}
</script>
@endsection
