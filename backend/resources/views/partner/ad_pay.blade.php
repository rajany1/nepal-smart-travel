@extends('partner.layout')
@section('title', 'Pay for Campaign')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <a href="{{ route('partner.ads') }}" class="text-sm text-primary-600 hover:underline mb-4 inline-flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Back to campaigns
        </a>
        <h2 class="text-2xl font-bold text-slate-800 mb-1">Pay for Ad Campaign</h2>
        <p class="text-sm text-slate-500 mb-6">Pay the budget via eSewa or Khalti. Your campaign goes live after admin approval once payment is received.</p>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                @foreach($errors->all() as $error)
                    <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-slate-50 rounded-xl border border-slate-200 p-5 mb-6">
            <div class="flex items-center justify-between text-sm">
                <span class="text-slate-500">Campaign</span>
                <span class="font-semibold text-slate-800">{{ $adCampaign->name }}</span>
            </div>
            <div class="flex items-center justify-between text-sm mt-3">
                <span class="text-slate-500">Budget (amount to pay)</span>
                <span class="font-semibold text-primary-600 text-lg">Rs. {{ number_format($adCampaign->budget, 2) }}</span>
            </div>
            <div class="flex items-center justify-between text-sm mt-2">
                <span class="text-slate-500">Billing</span>
                <span class="text-slate-600">Rs. {{ number_format($adCampaign->calculateSpend(), 2) }} spent so far Ã‚Â· campaign runs until budget is used</span>
            </div>
        </div>

        <form method="POST" action="{{ route('partner.ads.pay.initiate', $adCampaign) }}" id="pay-form">
            @csrf
            <label class="block text-sm font-medium text-slate-700 mb-3">Choose payment method</label>
            <div class="space-y-3">
                <label class="flex items-center gap-4 border-2 rounded-xl p-4 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 transition">
                    <input type="radio" name="gateway" value="esewa" class="accent-emerald-600" required onchange="setGateway('esewa')">
                    <span class="w-10 h-10 grid place-items-center bg-emerald-100 text-emerald-600 rounded-lg font-bold text-sm">e</span>
                    <span>
                        <span class="block font-semibold text-slate-800">eSewa</span>
                        <span class="block text-xs text-slate-500">Pay with eSewa wallet (sandbox test mode)</span>
                    </span>
                </label>
                <label class="flex items-center gap-4 border-2 rounded-xl p-4 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 transition">
                    <input type="radio" name="gateway" value="khalti" class="accent-emerald-600">
                    <div class="w-10 h-10 grid place-items-center bg-violet-100 text-violet-600 rounded-xl font-bold text-sm">K</div>
                    <span>
                        <span class="block font-semibold text-slate-800">Khalti</span>
                        <span class="block text-xs text-slate-500">Pay with Khalti wallet (sandbox test mode)</span>
                    </span>
                </label>
            </div>

            <input type="hidden" name="gateway" id="gateway-input" value="esewa">

            <button type="submit" class="mt-6 w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl px-6 py-3 text-sm transition">
                <i class="fas fa-credit-card"></i> Pay Now
            </button>
        </form>
    </div>
</div>

<script>
function setGateway(val) {
    document.getElementById('gateway-input').value = val;
}
document.querySelectorAll('input[name="gateway"]').forEach(r => {
    r.addEventListener('change', () => setGateway(r.value));
});
</script>
@endsection