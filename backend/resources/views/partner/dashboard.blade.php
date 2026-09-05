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

<div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
    <div class="lg:col-span-3 space-y-6">
        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">Welcome, {{ auth()->user()->name }} - Business Partner Portal</h2>
<p class="text-slate-500 text-sm mt-1">{{ $partner->name }} - {{ ucwords(str_replace('_', ' ', $partner->type)) }}</p>
                    <span class="inline-flex items-center gap-1.5 mt-3 text-xs px-3 py-1 rounded-full border {{ $statusColors[$partner->verification_status] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                        <i class="fas fa-shield-alt"></i> {{ ucfirst($partner->verification_status) }}
                    </span>
                </div>
                <a href="{{ route('partner.offers.create') }}"
                   class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shadow">
                    <i class="fas fa-plus"></i> New Offer
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl shadow p-5">
                <div class="text-3xl font-bold text-primary-600">{{ $stats['total_offers'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Total Offers</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <div class="text-3xl font-bold text-emerald-600">{{ $stats['active_offers'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Active</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <div class="text-3xl font-bold text-amber-500">{{ $stats['pending_offers'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Pending Approval</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <div class="text-3xl font-bold text-accent-500">{{ $stats['total_claims'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Codes Claimed</div>
            </div>
        </div>

        <a href="{{ route('partner.payouts') }}"
           class="block bg-gradient-to-r from-primary-700 to-primary-900 text-white rounded-2xl shadow p-5 flex items-center justify-between hover:opacity-95 transition">
            <div>
                <div class="text-xs text-teal-200 uppercase tracking-wide"><i class="fas fa-wallet mr-1"></i> Available Balance</div>
                <div class="text-3xl font-bold mt-1">Rs. {{ number_format($stats['payout_balance'], 2) }}</div>
                <div class="text-xs text-teal-300 mt-1">Earned Rs. {{ number_format($stats['offer_earned'], 0) }} - Paid out Rs. {{ number_format($stats['payout_paid'], 0) }}</div>
            </div>
            <span class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition shrink-0">
                <i class="fas fa-hand-holding-usd"></i> Request Payout
            </span>
        </a>

        <div class="bg-white rounded-2xl shadow border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800"><i class="fas fa-bullhorn text-primary-600 mr-2"></i> Ad Campaigns</h3>
                <a href="{{ route('partner.ads') }}" class="text-sm text-primary-600 hover:underline">Manage <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                <div>
                    <div class="text-2xl font-bold text-emerald-600">Rs. {{ number_format($stats['ads_paid'], 0) }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">Paid (budget loaded)</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-amber-600">Rs. {{ number_format($stats['ads_spent'], 1) }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">Spent on ads</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-primary-600">{{ number_format($stats['ads_impressions']) }} <span class="text-xs text-slate-400">/ {{ number_format($stats['ads_clicks']) }} clicks</span></div>
                    <div class="text-xs text-slate-500 mt-0.5">Impressions</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-emerald-600">Rs. {{ number_format($stats['offer_earned'], 0) }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">Earned from offers ({{ $stats['offer_used'] }} used, Rs. {{ number_format($stats['offer_commission'], 0) }} admin commission)</div>
                </div>
        </div>

        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-800">Recent Offers</h3>
                <a href="{{ route('partner.offers') }}" class="text-sm text-primary-600 hover:underline">View all <i class="fas fa-arrow-right"></i></a>
            </div>
            @forelse($offers as $offer)
                <div class="px-6 py-4 border-b border-slate-50 flex items-center justify-between">
                    <div>
                        <div class="font-medium text-slate-800">{{ $offer->title }}</div>
                        <div class="text-xs text-slate-500 mt-0.5">
{{ $offer->label() }} - {{ $offer->redemptions_count ?? 0 }} claims
@if($offer->ends_at) - expires {{ $offer->ends_at->format('M j, Y') }} @endif
                        </div>
                    </div>
                    <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$offer->status] ?? '' }}">{{ ucfirst($offer->status) }}</span>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-slate-400 text-sm">
                    <i class="fas fa-gift text-3xl mb-3 block"></i>
                    No offers yet. <a href="{{ route('partner.offers.create') }}" class="text-primary-600 font-semibold">Create your first offer</a> to attract customers!
                </div>
            @endforelse
        </div>
    </div>

    <div class="space-y-5">
        <div class="bg-primary-900 text-white rounded-2xl shadow p-6">
            <h3 class="font-semibold text-lg mb-3"><i class="fas fa-bullhorn text-accent-400"></i> How it works</h3>
            <ol class="space-y-3 text-sm text-teal-100">
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">1</span> Create an offer — it goes to admin for approval. Each offer has a value (Rs.) - you earn it when a user uses it.
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">2</span> Approved offers appear in the app's Rewards section.</li>
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">3</span> Users claim a unique code (RWD-XXXXXX).</li>
<li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">4</span> User shows the code at your business - verify it in <a href="{{ route('partner.offers') }}" class="text-accent-400 underline">your offers</a>.</li>
            </ol>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="font-semibold text-slate-800 mb-3"><i class="fas fa-store text-primary-600"></i> Business Info</h3>
            <dl class="space-y-2 text-sm">
<div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd class="text-slate-800 font-medium">{{ $partner->phone ?? '- N/A' }}</dd></div>
<div class="flex justify-between"><dt class="text-slate-500">Address</dt><dd class="text-slate-800 font-medium">{{ $partner->address ?? '- N/A' }}</dd></div>
<div class="flex justify-between"><dt class="text-slate-500">District</dt><dd class="text-slate-800 font-medium">{{ $partner->district ?? '- N/A' }}</dd></div>
<div class="flex justify-between"><dt class="text-slate-500">Website</dt><dd class="text-slate-800 font-medium">{{ $partner->website ?? '- N/A' }}</dd></div>
            </dl>
            <a href="{{ route('partner.business-form') }}" class="inline-block mt-4 text-sm text-primary-600 hover:underline">Edit profile <i class="fas fa-edit"></i></a>
        </div>

        @if(!$user->phone_verified_at)
        <div class="bg-amber-50 border border-amber-200 rounded-2xl shadow p-6 mt-4">
            <h3 class="font-semibold text-amber-800 mb-2"><i class="fas fa-phone-alt text-amber-600"></i> Phone Verification Required</h3>
            <p class="text-sm text-amber-700 mb-3">Verify your phone number to build trust with customers and unlock all partner features.</p>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('partner.send-phone-otp') }}">
                    @csrf
                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-xl px-4 py-2 transition">
                        <i class="fas fa-paper-plane mr-1"></i> Send Code
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('otpModal').classList.remove('hidden')" class="bg-white border border-amber-600 text-amber-700 text-sm font-semibold rounded-xl px-4 py-2 hover:bg-amber-50 transition">
                    <i class="fas fa-key mr-1"></i> Enter Code
                </button>
            </div>
        </div>

        <!-- OTP Modal -->
        <div id="otpModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
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
                        <button type="button" onclick="document.getElementById('otpModal').classList.add('hidden')" class="flex-1 border border-slate-300 text-slate-600 rounded-xl py-2.5 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-semibold rounded-xl py-2.5 transition">Verify</button>
                    </div>
                </form>
            </div>
        </div>
        @else
        <div class="bg-green-50 border border-green-200 rounded-2xl shadow p-6 mt-4">
            <h3 class="font-semibold text-green-800 mb-2"><i class="fas fa-check-circle text-green-600"></i> Phone Verified</h3>
            <p class="text-sm text-green-700">Your phone number is verified. Customers can trust your business.</p>
        </div>
        @endif
    </div>
</div>
@endsection
