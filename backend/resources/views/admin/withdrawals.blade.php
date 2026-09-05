@extends('admin.layout')

@section('title', 'Withdrawal Requests - Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Withdrawal Requests</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.coin-settings') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-cog mr-1"></i> Coin Settings
            </a>
            <a href="{{ route('admin.earnings-report') }}" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <i class="fas fa-chart-bar mr-1"></i> Earnings Report
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Pending</div>
            <div class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</div>
            <div class="text-xs text-gray-400">{{ number_format($stats['pending_amount'], 2) }} Coins</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Processing</div>
            <div class="text-2xl font-bold text-blue-600">{{ $stats['processing'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Completed</div>
            <div class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</div>
            <div class="text-xs text-gray-400">Rs. {{ number_format($stats['total_amount'], 2) }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Rejected</div>
            <div class="text-2xl font-bold text-red-600">{{ $stats['rejected'] }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="rounded border-gray-300">
                    <option value="all">All</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Method</label>
                <select name="method" class="rounded border-gray-300">
                    <option value="all">All</option>
                    <option value="esewa" {{ request('method') === 'esewa' ? 'selected' : '' }}>eSewa</option>
                    <option value="khalti" {{ request('method') === 'khalti' ? 'selected' : '' }}>Khalti</option>
                    <option value="bank" {{ request('method') === 'bank' ? 'selected' : '' }}>Bank</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="User name/email" class="rounded border-gray-300">
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">Filter</button>
        </form>
    </div>

    <!-- Withdrawals Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($withdrawals as $withdrawal)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900">#{{ $withdrawal->id }}</td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $withdrawal->user->name }}</div>
                        <div class="text-xs text-gray-500">{{ $withdrawal->user->email }}</div>
                    </td>
                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ number_format($withdrawal->amount, 0) }} Coins</td>
                    <td class="px-6 py-4">
                        @php
                            $methodColors = ['esewa' => 'green', 'khalti' => 'purple', 'bank' => 'blue'];
                            $color = $methodColors[$withdrawal->method] ?? 'gray';
                        @endphp
                        <span class="px-2 py-1 text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-800 rounded">
                            {{ ucfirst($withdrawal->method) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-500">
                        @if($withdrawal->method === 'bank')
                            {{ $withdrawal->account_details['bank_name'] ?? '' }}<br>
                            {{ $withdrawal->account_details['account_number'] ?? '' }}
                        @else
                            {{ $withdrawal->account_details['phone'] ?? '' }}
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $statusColors = ['pending' => 'yellow', 'processing' => 'blue', 'completed' => 'green', 'rejected' => 'red', 'cancelled' => 'gray'];
                            $statusColor = $statusColors[$withdrawal->status] ?? 'gray';
                        @endphp
                        <span class="px-2 py-1 text-xs font-medium bg-{{ $statusColor }}-100 text-{{ $statusColor }}-800 rounded">
                            {{ ucfirst($withdrawal->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        {{ $withdrawal->created_at->format('M d, Y') }}<br>
                        <span class="text-xs">{{ $withdrawal->created_at->format('h:i A') }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        @if($withdrawal->status === 'pending')
                        <div class="flex gap-1">
                            <form method="POST" action="{{ route('admin.withdrawals.approve', $withdrawal->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.withdrawals.complete', $withdrawal->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                    <i class="fas fa-money-bill"></i> Pay
                                </button>
                            </form>
                            <button onclick="showRejectModal({{ $withdrawal->id }})" class="px-2 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        @elseif($withdrawal->status === 'processing')
                        <form method="POST" action="{{ route('admin.withdrawals.complete', $withdrawal->id) }}" class="inline">
                            @csrf
                            <button type="submit" class="px-2 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                                <i class="fas fa-money-bill"></i> Mark Paid
                            </button>
                        </form>
                        @else
                        <span class="text-gray-400 text-xs">No actions</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">No withdrawal requests found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $withdrawals->links() }}
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Reject Withdrawal</h3>
        <form id="rejectForm" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason *</label>
                <textarea name="admin_note" rows="3" required class="w-full rounded border-gray-300" placeholder="Why is this withdrawal being rejected?"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="hideRejectModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Reject & Refund</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function showRejectModal(id) {
    document.getElementById('rejectForm').action = `/${window.adminPrefix}/withdrawals/${id}/reject`;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectModal').classList.add('flex');
}
function hideRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.remove('flex');
}
</script>
@endpush
@endsection
