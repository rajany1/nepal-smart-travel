@extends('partner.layout')

@section('title', 'Dashboard')

@section('content')
@php
    $statusColors = [
        'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
        'approved' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-red-100 text-red-700 border-red-200',
        'paused' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
@endphp

<div class="grid grid-cols-1 lg:grid-cols-4 gap-4 sm:gap-5">
    {{-- Main Content --}}
    <div class="lg:col-span-3 space-y-4 sm:space-y-5">
        {{-- Welcome Banner --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-800 leading-tight">Welcome, {{ auth()->user()->name }}</h2>
                    <p class="text-slate-500 text-sm mt-1 truncate">{{ $partner->name }} &middot; {{ ucwords(str_replace('_', ' ', $partner->type)) }}</p>
                    <span class="inline-flex items-center gap-1.5 mt-2 text-xs px-3 py-1 rounded-full border {{ $statusColors[$partner->verification_status] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                        <i class="fas fa-shield-alt"></i> {{ ucfirst($partner->verification_status) }}
                    </span>
                </div>
                <a href="{{ route('partner.offers.create') }}"
                   class="inline-flex items-center justify-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shadow shrink-0">
                    <i class="fas fa-plus"></i> New Offer
                </a>
            </div>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-primary-50 grid place-items-center text-primary-600 group-hover:bg-primary-100 transition"><i class="fas fa-gift"></i></div>
                    <div>
                        <div class="text-2xl font-bold text-primary-600">{{ $stats['total_offers'] }}</div>
                        <div class="text-[11px] text-slate-500">Total Offers</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 grid place-items-center text-emerald-600 group-hover:bg-emerald-100 transition"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="text-2xl font-bold text-emerald-600">{{ $stats['active_offers'] }}</div>
                        <div class="text-[11px] text-slate-500">Active</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 grid place-items-center text-amber-600 group-hover:bg-amber-100 transition"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="text-2xl font-bold text-amber-500">{{ $stats['pending_offers'] }}</div>
                        <div class="text-[11px] text-slate-500">Pending</div>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 group hover:shadow-md transition">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-accent-50 grid place-items-center text-accent-600 group-hover:bg-accent-100 transition"><i class="fas fa-ticket-alt"></i></div>
                    <div>
                        <div class="text-2xl font-bold text-accent-500">{{ $stats['total_claims'] }}</div>
                        <div class="text-[11px] text-slate-500">Codes Claimed</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Balance Banner --}}
        <a href="{{ route('partner.wallet') }}"
           class="block bg-gradient-to-r from-primary-700 to-primary-900 text-white rounded-2xl shadow-lg p-5 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between hover:shadow-xl transition group">
            <div>
                <div class="text-xs text-teal-200 uppercase tracking-wide font-medium"><i class="fas fa-wallet mr-1.5"></i> Available Balance</div>
                <div class="text-3xl sm:text-4xl font-extrabold mt-1 tracking-tight">Rs. {{ number_format($stats['wallet_balance'], 2) }}</div>
                <div class="text-xs text-teal-300 mt-1.5">Earned Rs. {{ number_format($stats['offer_earned'], 0) }} &middot; Paid Rs. {{ number_format($stats['payout_paid'], 0) }}</div>
            </div>
            <span class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shadow shrink-0 mt-3 sm:mt-0">
                <i class="fas fa-hand-holding-usd"></i> Withdraw
            </span>
        </a>

        {{-- Ad Campaigns Section --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 text-sm sm:text-base"><i class="fas fa-bullhorn text-primary-600 mr-2"></i>Ad Campaigns</h3>
                <a href="{{ route('partner.ads') }}" class="text-xs sm:text-sm text-primary-600 hover:underline font-medium">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="px-4 sm:px-6 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="text-center sm:text-left">
                    <div class="text-xl sm:text-2xl font-bold text-emerald-600">Rs. {{ number_format($stats['ads_paid'], 0) }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Paid Budget</div>
                </div>
                <div class="text-center sm:text-left">
                    <div class="text-xl sm:text-2xl font-bold text-amber-600">Rs. {{ number_format($stats['ads_spent'], 1) }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Spent</div>
                </div>
                <div class="text-center sm:text-left">
                    <div class="text-xl sm:text-2xl font-bold text-primary-600">{{ number_format($stats['ads_impressions']) }} <span class="text-xs text-slate-400 font-normal">/ {{ number_format($stats['ads_clicks']) }}</span></div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Views / Clicks</div>
                </div>
                <div class="text-center sm:text-left">
                    <div class="text-xl sm:text-2xl font-bold text-emerald-600">Rs. {{ number_format($stats['offer_earned'], 0) }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Earned ({{ $stats['offer_used'] }} used)</div>
                </div>
            </div>
        </div>

        {{-- Recent Offers --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800 text-sm sm:text-base">Recent Offers</h3>
                <a href="{{ route('partner.offers') }}" class="text-xs sm:text-sm text-primary-600 hover:underline font-medium">View all <i class="fas fa-arrow-right"></i></a>
            </div>
            {{-- Desktop Table --}}
            <div class="hidden sm:block">
                @forelse($offers as $offer)
                    <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between last:border-0 hover:bg-slate-50/60 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            @if($offer->image)
                                <img src="{{ asset('storage/' . $offer->image) }}" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                            @endif
                            <div class="min-w-0">
                                <div class="font-medium text-slate-800 truncate">{{ $offer->title }}</div>
                                <div class="text-xs text-slate-500 mt-0.5">
                                    {{ $offer->label() }} &middot; {{ $offer->redemptions_count ?? 0 }} claims
                                    @if($offer->ends_at) &middot; expires {{ $offer->ends_at->format('M j, Y') }} @endif
                                </div>
                            </div>
                        </div>
                        <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$offer->status] ?? '' }} shrink-0 ml-3">{{ ucfirst($offer->status) }}</span>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center text-slate-400 text-sm">
                        <i class="fas fa-gift text-3xl mb-3 block"></i>
                        No offers yet. <a href="{{ route('partner.offers.create') }}" class="text-primary-600 font-semibold">Create your first offer</a> to attract customers!
                    </div>
                @endforelse
            </div>
            {{-- Mobile Cards --}}
            <div class="sm:hidden divide-y divide-slate-50">
                @forelse($offers as $offer)
                    <div class="px-4 py-3 hover:bg-slate-50/60 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                @if($offer->image)
                                    <img src="{{ asset('storage/' . $offer->image) }}" alt="" class="w-9 h-9 rounded-lg object-cover flex-shrink-0">
                                @endif
                                <div class="min-w-0">
                                    <div class="font-medium text-sm text-slate-800 truncate">{{ $offer->title }}</div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">{{ $offer->label() }} &middot; {{ $offer->redemptions_count ?? 0 }} claims</div>
                                </div>
                            </div>
                            <span class="text-[10px] px-2 py-0.5 rounded-full border {{ $statusColors[$offer->status] ?? '' }} shrink-0">{{ ucfirst($offer->status) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-10 text-center text-slate-400 text-sm">
                        <i class="fas fa-gift text-3xl mb-3 block"></i>
                        No offers yet. <a href="{{ route('partner.offers.create') }}" class="text-primary-600 font-semibold">Create your first offer</a>!
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-4 sm:space-y-5">
        {{-- How It Works --}}
        <div class="bg-gradient-to-br from-primary-800 to-primary-950 text-white rounded-2xl shadow-lg p-5 sm:p-6">
            <h3 class="font-semibold text-base mb-4"><i class="fas fa-lightbulb text-accent-400 mr-2"></i>How it works</h3>
            <ol class="space-y-3.5 text-sm text-teal-100">
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-white/10 backdrop-blur grid place-items-center text-xs font-bold shrink-0">1</span> <span>Create an offer — it goes to admin for approval. Each offer has a value (Rs.) — you earn it when a user uses it.</span></li>
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-white/10 backdrop-blur grid place-items-center text-xs font-bold shrink-0">2</span> <span>Approved offers appear in the app's Rewards section.</span></li>
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-white/10 backdrop-blur grid place-items-center text-xs font-bold shrink-0">3</span> <span>Users claim a unique code (RWD-XXXXXX).</span></li>
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-white/10 backdrop-blur grid place-items-center text-xs font-bold shrink-0">4</span> <span>User shows the code at your business — verify it in <a href="{{ route('partner.offers') }}" class="text-accent-400 underline font-medium">your offers</a>.</span></li>
            </ol>
        </div>

        {{-- Business Info --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 sm:p-6">
            <h3 class="font-semibold text-slate-800 mb-3 text-sm sm:text-base"><i class="fas fa-store text-primary-600 mr-2"></i>Business Info</h3>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between items-center py-1 border-b border-slate-50">
                    <dt class="text-slate-500">Phone</dt>
                    <dd class="text-slate-800 font-medium text-right">{{ $partner->phone ?? '—' }}</dd>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-slate-50">
                    <dt class="text-slate-500">Address</dt>
                    <dd class="text-slate-800 font-medium text-right truncate max-w-[150px]">{{ $partner->address ?? '—' }}</dd>
                </div>
                <div class="flex justify-between items-center py-1 border-b border-slate-50">
                    <dt class="text-slate-500">District</dt>
                    <dd class="text-slate-800 font-medium text-right">{{ $partner->district ?? '—' }}</dd>
                </div>
                <div class="flex justify-between items-center py-1">
                    <dt class="text-slate-500">Website</dt>
                    <dd class="text-slate-800 font-medium text-right truncate max-w-[150px]">{{ $partner->website ?? '—' }}</dd>
                </div>
            </dl>
            <a href="{{ route('partner.business-form') }}" class="inline-flex items-center gap-1.5 mt-4 text-sm text-primary-600 hover:text-primary-700 font-medium transition">
                <i class="fas fa-edit"></i> Edit profile
            </a>
        </div>

        {{-- Phone Verification --}}
        @if(!$user->phone_verified_at)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 sm:p-6">
            <h3 class="font-semibold text-amber-800 mb-2 text-sm"><i class="fas fa-phone-alt text-amber-600 mr-2"></i>Phone Verification</h3>
            <p class="text-xs text-amber-700 mb-3">Verify your phone number to build trust with customers.</p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('partner.send-phone-otp') }}">
                    @csrf
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-xl px-3 py-2 transition shadow-sm">
                        <i class="fas fa-paper-plane mr-1"></i> Send Code
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('otpModal').classList.remove('hidden')" class="bg-white border border-amber-600 text-amber-700 text-xs font-semibold rounded-xl px-3 py-2 hover:bg-amber-50 transition">
                    <i class="fas fa-key mr-1"></i> Enter Code
                </button>
            </div>
        </div>

        <div id="otpModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 animate-slide-up">
                <h3 class="text-lg font-bold text-slate-800 mb-2">Enter Verification Code</h3>
                <p class="text-sm text-slate-500 mb-4">Enter the 6-digit code sent to your phone via app notification.</p>
                <form method="POST" action="{{ route('partner.verify-phone') }}">
                    @csrf
                    <input type="text" name="otp" maxlength="6" required placeholder="------"
                           class="w-full text-center text-2xl tracking-[0.5em] font-mono border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-amber-500 outline-none mb-4">
                    @error('otp')
                        <p class="text-red-500 text-sm mb-2">{{ $message }}</p>
                    @enderror
                    <div class="flex gap-2">
                        <button type="button" onclick="document.getElementById('otpModal').classList.add('hidden')" class="flex-1 border border-slate-300 text-slate-600 rounded-xl py-2.5 hover:bg-slate-50 transition text-sm font-medium">Cancel</button>
                        <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl py-2.5 transition text-sm">Verify</button>
                    </div>
                </form>
            </div>
        </div>
        @else
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-5 sm:p-6">
            <h3 class="font-semibold text-emerald-800 mb-1 text-sm"><i class="fas fa-check-circle text-emerald-600 mr-2"></i>Phone Verified</h3>
            <p class="text-xs text-emerald-700">Your phone number is verified. Customers can trust your business.</p>
        </div>
        @endif
    </div>
</div>
@endsection
