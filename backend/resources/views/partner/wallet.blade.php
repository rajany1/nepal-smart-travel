@extends('partner.layout')

@section('title', 'My Wallet')

@section('content')
<div class="max-w-4xl mx-auto mt-6 space-y-6">

    {{-- Wallet Card --}}
    <div class="bg-gradient-to-br from-primary-600 to-primary-800 rounded-2xl shadow-xl p-8 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-primary-200 text-sm">Available Balance</p>
                <p class="text-4xl font-bold mt-1">Rs. {{ number_format($wallet->balance, 2) }}</p>
            </div>
            <div class="w-16 h-16 rounded-2xl bg-white/20 grid place-items-center text-3xl">
                <i class="fas fa-wallet"></i>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 mt-6">
            <div class="bg-white/10 rounded-xl p-3">
                <p class="text-primary-200 text-xs">Total Earned</p>
                <p class="font-semibold">Rs. {{ number_format($wallet->total_earned, 2) }}</p>
            </div>
            <div class="bg-white/10 rounded-xl p-3">
                <p class="text-primary-200 text-xs">Total Withdrawn</p>
                <p class="font-semibold">Rs. {{ number_format($wallet->total_withdrawn, 2) }}</p>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('partner.payments.scan') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-amber-100 text-amber-600 grid place-items-center text-xl mb-2">
                <i class="fas fa-qrcode"></i>
            </div>
            <p class="font-semibold text-gray-800">Scan / Redeem</p>
            <p class="text-xs text-gray-500 mt-1">Scan QR or enter code</p>
        </a>
        <button onclick="document.getElementById('withdraw-modal').classList.remove('hidden');document.getElementById('withdraw-modal').classList.add('flex');" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-emerald-100 text-emerald-600 grid place-items-center text-xl mb-2">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <p class="font-semibold text-gray-800">Withdraw</p>
            <p class="text-xs text-gray-500 mt-1">To eSewa / Khalti / Bank</p>
        </button>
        <a href="{{ route('partner.payments.history') }}" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-blue-100 text-blue-600 grid place-items-center text-xl mb-2">
                <i class="fas fa-history"></i>
            </div>
            <p class="font-semibold text-gray-800">History</p>
            <p class="text-xs text-gray-500 mt-1">Payment transactions</p>
        </a>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl px-4 py-3 flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3">
            @foreach($errors->all() as $error)
                <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Recent Payments --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="font-semibold text-gray-800"><i class="fas fa-receipt text-gray-400 mr-2"></i>Recent Payments</h2>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($payments as $payment)
            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="font-medium text-gray-800">{{ $payment->user->name ?? 'User' }}</p>
                    <p class="text-xs text-gray-500">{{ $payment->redeem_code }} &middot; {{ $payment->paid_at?->diffForHumans() }}</p>
                </div>
                <p class="font-semibold text-emerald-600">+Rs. {{ number_format($payment->partner_amount, 2) }}</p>
            </div>
            @empty
            <div class="px-6 py-8 text-center text-gray-400">
                <i class="fas fa-receipt text-3xl mb-2 block"></i>
                <p>No payments yet</p>
            </div>
            @endforelse
        </div>
        <div class="px-6 py-3">{{ $payments->links() }}</div>
    </div>

    {{-- Withdraw Modal --}}
    <div id="withdraw-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <h3 class="text-lg font-bold text-gray-900">Withdraw Funds</h3>
                <button onclick="document.getElementById('withdraw-modal').classList.add('hidden');document.getElementById('withdraw-modal').classList.remove('flex');" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('partner.withdraw') }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs.)</label>
                    <input type="number" name="amount" min="100" max="{{ $wallet->balance }}" step="1" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="100">
                    <p class="text-xs text-gray-500 mt-1">Available: Rs. {{ number_format($wallet->balance, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Method</label>
                    <select name="method" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="esewa">eSewa</option>
                        <option value="khalti">Khalti</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Account Detail</label>
                    <input type="text" name="account_detail" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="eSewa/Khalti number or bank account">
                </div>
                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg py-2.5 transition">
                    <i class="fas fa-paper-plane mr-1"></i> Submit Withdrawal
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
