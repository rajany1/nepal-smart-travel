@extends('partner.layout')

@section('title', 'Payouts')

@section('content')
@php
    $statusColors = [
        'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
        'paid' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-red-100 text-red-700 border-red-200',
    ];
    $methodLabels = ['esewa' => 'eSewa', 'khalti' => 'Khalti', 'bank' => 'Bank Transfer'];
@endphp
<script>
    var MIN_THRESHOLDS = { eSewa: {{ $minThresholds['esewa'] }}, khalti: {{ $minThresholds['khalti'] }}, bank: {{ $minThresholds['bank'] }} };
</script>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">Payouts</h2>
                    <p class="text-sm text-slate-500 mt-1">Withdraw the money you earn from offer redemptions.</p>
                </div>
                <span class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full bg-primary-50 text-primary-700 font-semibold">
                    <i class="fas fa-lock"></i> Approved by admin
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-primary-900 text-white rounded-2xl shadow p-5">
                <div class="text-xs text-teal-200 uppercase tracking-wide">Available Balance</div>
                <div class="text-3xl font-bold mt-1">Rs. {{ number_format($balance, 2) }}</div>
                <div class="text-xs text-teal-300 mt-1">Ready to request</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <div class="text-xs text-slate-500 uppercase tracking-wide">Total Earned</div>
                <div class="text-2xl font-bold text-emerald-600 mt-1">Rs. {{ number_format($earned, 2) }}</div>
                <div class="text-xs text-slate-400 mt-1">Used codes only</div>
            </div>
            <div class="bg-white rounded-2xl shadow p-5">
                <div class="text-xs text-slate-500 uppercase tracking-wide">Paid Out</div>
                <div class="text-2xl font-bold text-slate-700 mt-1">Rs. {{ number_format($paid, 2) }}</div>
                @if($pending > 0)
                    <div class="text-xs text-amber-600 mt-1">Rs. {{ number_format($pending, 2) }} pending review</div>
                @else
                    <div class="text-xs text-slate-400 mt-1">None yet</div>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="font-semibold text-slate-800 mb-4"><i class="fas fa-hand-holding-usd text-primary-600 mr-2"></i> Request Payout</h3>
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                    @foreach($errors->all() as $error)
                        <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                    @endforeach
                </div>
            @endif
            @if($balance > 0)
                <form method="POST" action="{{ route('partner.payouts.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Amount (Rs.) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" min="{{ $minThresholds['esewa'] }}" max="{{ $balance }}" name="amount" id="payout_amount" value="{{ old('amount') }}" required
                                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none"
                                   placeholder="e.g. 5000">
                            <p class="text-xs text-slate-400 mt-1">Minimum: <span id="min_hint">Rs. {{ number_format($minThresholds['esewa'], 0) }}</span> · Max available: Rs. {{ number_format($balance, 2) }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
                            <select name="payment_method" id="payment_method" required onchange="updateMin()" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                                <option value="">Select method...</option>
                                @foreach($methodLabels as $val => $label)
                                    <option value="{{ $val }}" @selected(old('payment_method') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Minimums — eSewa Rs. {{ number_format($minThresholds['esewa'], 0) }} · Khalti Rs. {{ number_format($minThresholds['khalti'], 0) }} · Bank Rs. {{ number_format($minThresholds['bank'], 0) }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account / ID Detail <span class="text-xs text-slate-400">(eSewa ID, Khalti number, or bank account)</span></label>
                        <input type="text" name="payment_detail" value="{{ old('payment_detail') }}" maxlength="255"
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none"
                               placeholder="e.g. 98XXXXXXXX">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Note <span class="text-xs text-slate-400">(optional, for admin)</span></label>
                        <textarea name="note" rows="2" maxlength="1000" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">{{ old('note') }}</textarea>
                    </div>
                    <div class="flex items-center justify-end">
                        <button type="submit" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition">
                            <i class="fas fa-paper-plane"></i> Request Payout
                        </button>
                    </div>
                </form>
            @else
                <div class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-6 text-center text-slate-500 text-sm">
                    <i class="fas fa-info-circle text-xl mb-2 block text-slate-400"></i>
                    Your available balance is Rs. 0. You earn from offers when customers use the codes at your business.
                </div>
            @endif
        </div>

        <div class="bg-white rounded-2xl shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-800"><i class="fas fa-history text-primary-600 mr-2"></i> Payout History</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="text-left px-6 py-3">Requested</th>
                            <th class="text-left px-4 py-3">Amount</th>
                            <th class="text-left px-4 py-3">Method</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-left px-4 py-3">Admin Note</th>
                            <th class="text-right px-6 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($payouts as $payout)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-6 py-4 text-slate-600">{{ $payout->requested_at?->format('M j, Y H:i') }}</td>
                                <td class="px-4 py-4 font-semibold text-slate-800">Rs. {{ number_format($payout->amount, 2) }}</td>
                                <td class="px-4 py-4 text-slate-600">
                                    {{ $methodLabels[$payout->payment_method] ?? ucfirst($payout->payment_method) }}
                                    @if($payout->payment_detail)
                                        <span class="block text-xs text-slate-400">{{ $payout->payment_detail }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$payout->status] ?? '' }}">{{ ucfirst($payout->status) }}</span>
                                    @if($payout->status === 'paid' && $payout->processed_at)
                                        <span class="block text-[10px] text-slate-400 mt-0.5">{{ $payout->processed_at->format('M j, Y') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-slate-500">{{ $payout->admin_note ?: '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if($payout->status === 'pending')
                                        <form method="POST" action="{{ route('partner.payouts.cancel', $payout) }}" onsubmit="return confirm('Cancel this payout request?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Cancel</button>
                                        </form>
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-slate-400 text-sm">
                                    <i class="fas fa-hand-holding-usd text-3xl mb-3 block"></i>
                                    No payout requests yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $payouts->links() }}</div>
        </div>
    </div>

    <div class="space-y-5">
        <div class="bg-primary-900 text-white rounded-2xl shadow p-6">
            <h3 class="font-semibold text-lg mb-3"><i class="fas fa-money-bill-wave text-accent-400"></i> How payouts work</h3>
            <ol class="space-y-3 text-sm text-teal-100">
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">1</span> You earn Rs. when a customer uses your offer code at your business.</li>
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">2</span> Request a payout for any amount up to your available balance.</li>
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">3</span> Admin reviews and transfers the money to your chosen method (eSewa / Khalti / bank).</li>
                <li class="flex gap-3"><span class="w-6 h-6 rounded-full bg-teal-800 grid place-items-center text-xs font-bold shrink-0">4</span> Rejected requests return the amount to your available balance.</li>
            </ol>
        </div>
    </div>
</div>

<script>
function updateMin() {
    var method = document.getElementById('payment_method').value;
    var amount = document.getElementById('payout_amount');
    var hint = document.getElementById('min_hint');
    var min = MIN_THRESHOLDS[method];
    if (method && min !== undefined) {
        amount.min = min;
        hint.textContent = 'Rs. ' + Number(min).toLocaleString('en-US', {minimumFractionDigits: 0});
    } else {
        amount.min = MIN_THRESHOLDS.esewa;
        hint.textContent = 'Rs. ' + Number(MIN_THRESHOLDS.esewa).toLocaleString('en-US');
    }
}
updateMin();
</script>
@endsection
