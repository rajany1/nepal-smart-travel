@extends('admin.layout')

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

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Partner Payouts</h2>
    <div class="flex rounded-xl border border-slate-200 bg-white overflow-hidden text-sm">
        <a href="{{ route('admin.payouts') }}" class="px-4 py-2 {{ !$status ? 'bg-accent-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">All</a>
        @foreach(['pending', 'paid', 'rejected'] as $s)
            <a href="{{ route('admin.payouts', ['status' => $s]) }}" class="px-4 py-2 {{ $status === $s ? 'bg-accent-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">{{ ucfirst($s) }} {{ $s === 'pending' && $stats['pending'] ? "($stats[pending])" : '' }}</a>
        @endforeach
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-amber-600">{{ $stats['pending'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Pending Requests</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-slate-800">Rs. {{ number_format($stats['pending_total'], 0) }}</div>
        <div class="text-xs text-slate-500 mt-1">Pending Amount</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-emerald-600">Rs. {{ number_format($stats['paid_total'], 0) }}</div>
        <div class="text-xs text-slate-500 mt-1">Paid Out ({{ $stats['paid'] }} payouts)</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-red-500">{{ $stats['rejected'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Rejected</div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
<div id="liveTable">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-6 py-3">Partner</th>
                    <th class="text-left px-4 py-3">Amount</th>
                    <th class="text-left px-4 py-3">Method</th>
                    <th class="text-left px-4 py-3">Requested</th>
                    <th class="text-left px-4 py-3">Note</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-right px-6 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($payouts as $payout)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800">{{ $payout->partner?->name ?? 'â€”' }}</div>
                            @if($payout->partner?->phone)
                                <div class="text-xs text-slate-500">{{ $payout->partner->phone }}</div>
                            @endif
                            @if($payout->partner?->district)
                                <div class="text-xs text-slate-400">{{ $payout->partner->district }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-4 font-semibold text-slate-800">Rs. {{ number_format($payout->amount, 2) }}</td>
                        <td class="px-4 py-4 text-slate-600">
                            {{ $methodLabels[$payout->payment_method] ?? ucfirst($payout->payment_method ?? 'â€”') }}
                            @if($payout->payment_detail)
                                <span class="block text-xs text-slate-400">{{ $payout->payment_detail }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-slate-600">{{ $payout->requested_at?->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-4 text-slate-500 max-w-[200px] truncate" title="{{ $payout->note }}">{{ $payout->note ?: 'â€”' }}</td>
                        <td class="px-4 py-4">
                            <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$payout->status] ?? '' }}">{{ ucfirst($payout->status) }}</span>
                            @if($payout->processed_at)
                                <span class="block text-[10px] text-slate-400 mt-0.5">{{ $payout->processed_at->format('M j, Y') }} by {{ $payout->processor?->name ?? 'â€”' }}</span>
                            @endif
                            @if($payout->admin_note)
                                <span class="block text-[10px] text-slate-500 mt-0.5"><i class="fas fa-comment"></i> {{ $payout->admin_note }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($payout->status === 'pending')
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" onclick="showPaid({{ $payout->id }})"
                                            class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                        <i class="fas fa-check"></i> Mark Paid
                                    </button>
                                    <button type="button" onclick="showReject({{ $payout->id }}, {{ json_encode($payout->partner?->name) }})"
                                            class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            @else
                                <span class="text-slate-300 text-right block">â€”</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-hand-holding-usd text-3xl mb-3 block"></i>
                            @if($status) No {{ $status }} payouts yet. @else No payout requests yet. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4">{{ $payouts->links() }}</div>
</div>
</div>

<!-- Mark Paid modal -->
<div id="paidModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Mark Payout as Paid</h3>
        <p class="text-sm text-slate-500 mb-4">Confirm the money has been transferred to the partner.</p>
        <form method="POST" action="" id="paidForm">
            @csrf
            <label class="block text-sm font-medium text-slate-700 mb-1">Admin Note <span class="text-xs text-slate-400">(optional)</span></label>
            <input type="text" name="admin_note" maxlength="255"
                   class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none"
                   placeholder="e.g. Paid via eSewa">
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closePaid()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg px-5 py-2">Confirm Paid</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject modal -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Reject Payout</h3>
        <p class="text-sm text-slate-500 mb-4" id="rejectTitle"></p>
        <form method="POST" action="" id="rejectForm">
            @csrf
            <label class="block text-sm font-medium text-slate-700 mb-1">Reason (visible to business) <span class="text-red-500">*</span></label>
            <textarea name="admin_note" rows="3" required
                      class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-red-500 outline-none"
                      placeholder="e.g. Invalid payment detail, please update and request again"></textarea>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeReject()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg px-5 py-2">Reject Payout</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showPaid(id) {
        document.getElementById('paidForm').action = '/admin/payouts/' + id + '/paid';
        document.getElementById('paidModal').classList.remove('hidden');
    }
    function closePaid() {
        document.getElementById('paidModal').classList.add('hidden');
    }
    function showReject(id, name) {
        document.getElementById('rejectForm').action = '/admin/payouts/' + id + '/reject';
        document.getElementById('rejectTitle').textContent = name ? (name + ' - payout #' + id) : ('Payout #' + id);
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    function closeReject() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
</script>
@endsection
