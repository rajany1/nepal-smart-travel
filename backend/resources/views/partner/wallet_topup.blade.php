@extends('partner.layout')
@section('title', 'Load Money to Wallet')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-6 sm:p-8">
        <a href="{{ route('partner.wallet') }}" class="text-sm text-primary-600 hover:underline mb-4 inline-flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Back to wallet
        </a>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mb-1">Load Money to Wallet</h2>
        <p class="text-sm text-slate-500 mb-5">Add funds to your ORIPORI wallet to pay for ads and services.</p>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                @foreach($errors->all() as $error)
                    <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 rounded-2xl p-5 text-white mb-5">
            <p class="text-primary-200 text-sm font-medium">Current Balance</p>
            <p class="text-2xl sm:text-3xl font-extrabold mt-1">Rs. {{ number_format($wallet->balance, 2) }}</p>
            <p class="text-xs text-primary-300 mt-1">1 ORIPORI Coin = Rs. 1</p>
        </div>

        <form method="POST" action="{{ route('partner.wallet.topup.initiate') }}">
            @csrf

            <label class="block text-sm font-medium text-slate-700 mb-2">Amount (Rs.)</label>
            <input type="number" name="amount" min="100" max="100000" step="1" value="500" required
                class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 text-sm focus:border-primary-500 focus:ring-0 transition mb-4">
            <p class="text-xs text-slate-400 -mt-2 mb-4">Minimum Rs. 100, Maximum Rs. 1,00,000</p>

            <div class="flex gap-2 mb-4">
                <button type="button" onclick="setAmount(100)" class="px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 rounded-lg transition">Rs. 100</button>
                <button type="button" onclick="setAmount(500)" class="px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 rounded-lg transition">Rs. 500</button>
                <button type="button" onclick="setAmount(1000)" class="px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 rounded-lg transition">Rs. 1000</button>
                <button type="button" onclick="setAmount(2000)" class="px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 rounded-lg transition">Rs. 2000</button>
                <button type="button" onclick="setAmount(5000)" class="px-3 py-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 rounded-lg transition">Rs. 5000</button>
            </div>

            <label class="block text-sm font-medium text-slate-700 mb-3">Payment Method</label>
            <div class="space-y-3 mb-5">
                <label class="flex items-center gap-4 border-2 rounded-xl p-4 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50 transition">
                    <input type="radio" name="gateway" value="esewa" class="accent-emerald-600" checked onchange="setGateway('esewa')">
                    <span class="w-10 h-10 grid place-items-center bg-emerald-100 text-emerald-600 rounded-lg font-bold text-sm">e</span>
                    <span>
                        <span class="block font-semibold text-slate-800 text-sm">eSewa</span>
                        <span class="block text-xs text-slate-500">Pay with eSewa wallet</span>
                    </span>
                </label>
                <label class="flex items-center gap-4 border-2 rounded-xl p-4 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50/50 transition">
                    <input type="radio" name="gateway" value="khalti" class="accent-emerald-600">
                    <div class="w-10 h-10 grid place-items-center bg-violet-100 text-violet-600 rounded-xl font-bold text-sm">K</div>
                    <span>
                        <span class="block font-semibold text-slate-800 text-sm">Khalti</span>
                        <span class="block text-xs text-slate-500">Pay with Khalti wallet</span>
                    </span>
                </label>
            </div>

            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl px-6 py-3 text-sm transition shadow-sm">
                <i class="fas fa-plus-circle"></i> Load Money
            </button>
        </form>
    </div>
</div>

<script>
function setAmount(val) {
    document.querySelector('input[name="amount"]').value = val;
}
function setGateway(val) {
    document.getElementById('gateway-input').value = val;
}
</script>
@endsection
