@extends('partner.layout')

@section('title', 'Payment History')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-5">
        <a href="{{ route('partner.wallet') }}" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1"><i class="fas fa-arrow-left"></i> Back to wallet</a>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mt-2">Payment History</h2>
        <p class="text-sm text-slate-500 mt-0.5">All payment transactions from offer redemptions</p>
    </div>

    {{-- Desktop Table --}}
    <div class="hidden lg:block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-6 py-3 font-semibold">User</th>
                        <th class="text-left px-4 py-3 font-semibold">Code</th>
                        <th class="text-left px-4 py-3 font-semibold">Amount</th>
                        <th class="text-left px-4 py-3 font-semibold">Commission</th>
                        <th class="text-left px-4 py-3 font-semibold">You Get</th>
                        <th class="text-left px-4 py-3 font-semibold">Status</th>
                        <th class="text-left px-4 py-3 font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-slate-50/60 transition">
                        <td class="px-6 py-4 text-slate-800">{{ $payment->user->name ?? '-' }}</td>
                        <td class="px-6 py-4 font-mono text-slate-600 text-xs">{{ $payment->redeem_code }}</td>
                        <td class="px-6 py-4 text-slate-800 font-medium">Rs. {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-4 text-red-500">-Rs. {{ number_format($payment->commission_amount, 2) }}</td>
                        <td class="px-6 py-4 font-semibold text-emerald-600">Rs. {{ number_format($payment->partner_amount, 2) }}</td>
                        <td class="px-6 py-4">
                            @if($payment->status === 'completed')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 border border-emerald-200">Completed</span>
                            @elseif($payment->status === 'pending')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 border border-amber-200">Pending</span>
                            @elseif($payment->status === 'expired')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">Expired</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 border border-red-200">Failed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-slate-500">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-receipt text-4xl mb-3 block text-slate-300"></i>
                            No payments yet
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-4 border-t border-slate-100">{{ $payments->links() }}</div>
    </div>

    {{-- Mobile Cards --}}
    <div class="lg:hidden space-y-3">
        @forelse($payments as $payment)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-sm text-slate-800">{{ $payment->user->name ?? 'User' }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5 font-mono">{{ $payment->redeem_code }}</div>
                    </div>
                    @if($payment->status === 'completed')
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-emerald-100 text-emerald-700 border border-emerald-200 shrink-0">Completed</span>
                    @elseif($payment->status === 'pending')
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-amber-100 text-amber-700 border border-amber-200 shrink-0">Pending</span>
                    @elseif($payment->status === 'expired')
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-600 border border-slate-200 shrink-0">Expired</span>
                    @else
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium bg-red-100 text-red-700 border border-red-200 shrink-0">Failed</span>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-slate-100">
                    <div>
                        <div class="text-[10px] text-slate-400">Amount</div>
                        <div class="text-sm font-semibold text-slate-800">Rs. {{ number_format($payment->amount, 0) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">Commission</div>
                        <div class="text-sm font-semibold text-red-500">-Rs. {{ number_format($payment->commission_amount, 0) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] text-slate-400">You Get</div>
                        <div class="text-sm font-semibold text-emerald-600">Rs. {{ number_format($payment->partner_amount, 0) }}</div>
                    </div>
                </div>

                <div class="text-[11px] text-slate-400 mt-2">
                    <i class="fas fa-calendar-alt mr-1"></i>{{ $payment->created_at->format('M d, Y g:i A') }}
                </div>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-10 text-center text-slate-400">
            <i class="fas fa-receipt text-4xl mb-3 block text-slate-300"></i>
            No payments yet
        </div>
        @endforelse
        <div class="mt-4">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
