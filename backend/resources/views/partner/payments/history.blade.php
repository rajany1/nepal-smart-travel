@extends('partner.layout')

@section('title', 'Payment History')

@section('content')
<div class="max-w-4xl mx-auto mt-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-semibold text-gray-800"><i class="fas fa-history text-gray-400 mr-2"></i>Payment History</h2>
            <a href="{{ route('partner.wallet') }}" class="text-sm text-primary-600 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commission</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">You Get</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-800">{{ $payment->user->name ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm font-mono text-gray-600">{{ $payment->redeem_code }}</td>
                        <td class="px-6 py-4 text-sm text-gray-800">Rs. {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm text-red-600">-Rs. {{ number_format($payment->commission_amount, 2) }}</td>
                        <td class="px-6 py-4 text-sm font-semibold text-emerald-600">Rs. {{ number_format($payment->partner_amount, 2) }}</td>
                        <td class="px-6 py-4">
                            @if($payment->status === 'completed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Completed</span>
                            @elseif($payment->status === 'pending')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                            @elseif($payment->status === 'expired')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Expired</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $payment->created_at->format('M d, Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                            <i class="fas fa-receipt text-3xl mb-2 block"></i>
                            No payments yet
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3">{{ $payments->links() }}</div>
    </div>
</div>
@endsection
