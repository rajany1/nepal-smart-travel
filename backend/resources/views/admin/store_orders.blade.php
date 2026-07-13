@extends('admin.layout')
@section('title', 'Store Orders')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Purchase Orders</h3>
            <p class="text-sm text-slate-500 mt-1">View and manage user purchases from the XP store.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.store.orders', ['status' => '']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$status ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">All</a>
            <a href="{{ route('admin.store.orders', ['status' => 'pending']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Pending</a>
            <a href="{{ route('admin.store.orders', ['status' => 'completed']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'completed' ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Completed</a>
            <a href="{{ route('admin.store.orders', ['status' => 'cancelled']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'cancelled' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Cancelled</a>
            <a href="{{ route('admin.store.orders', ['status' => 'refunded']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'refunded' ? 'bg-purple-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Refunded</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">XP Spent</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Fulfilled By</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($purchases as $purchase)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 text-sm text-slate-500">#{{ $purchase->id }}</td>
                        <td class="px-6 py-4">
                            <p class="font-semibold text-slate-900">{{ $purchase->user->name }}</p>
                            <p class="text-xs text-slate-400">{{ $purchase->user->email }}</p>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-{{ $purchase->shopItem->icon }} text-primary-600"></i>
                                <span class="text-sm font-medium">{{ $purchase->shopItem->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center font-semibold text-amber-600">{{ number_format($purchase->xp_spent) }}</td>
                        <td class="px-6 py-4 text-center">
                            @switch($purchase->status)
                                @case('pending')
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">Pending</span>
                                @break
                                @case('completed')
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Completed</span>
                                @break
                                @case('cancelled')
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Cancelled</span>
                                @break
                                @case('refunded')
                                <span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">Refunded</span>
                                @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500">{{ $purchase->fulfiller?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-slate-500">{{ $purchase->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($purchase->isPending())
                                <button onclick="fulfillOrder({{ $purchase->id }})" class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition">
                                    <i class="fas fa-check"></i> Fulfill
                                </button>
                                <button onclick="cancelOrder({{ $purchase->id }})" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                @endif
                                @if($purchase->isCompleted())
                                <button onclick="refundOrder({{ $purchase->id }})" class="px-3 py-1.5 text-xs font-medium bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100 transition">
                                    <i class="fas fa-undo"></i> Refund
                                </button>
                                @endif
                                @if($purchase->shop_code_id)
                                <button onclick="alert('{{ $purchase->shopCode?->code }}')" class="px-3 py-1.5 text-xs font-medium bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-100 transition">
                                    <i class="fas fa-key"></i> View Code
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-box-open text-3xl mb-3"></i>
                            <p class="text-sm">No purchases found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $purchases->appends(['status' => $status])->links() }}
    </div>
</div>

{{-- Fulfill Modal --}}
<div id="fulfillModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h4 class="text-lg font-bold text-slate-900 mb-4">Fulfill Purchase</h4>
        <form method="POST" id="fulfillForm" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Fulfillment Note (optional)</label>
                <textarea name="note" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Enter delivery details..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('fulfillModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-green-600 text-white rounded-lg hover:bg-green-700">Fulfill</button>
            </div>
        </form>
    </div>
</div>

{{-- Cancel Modal --}}
<div id="cancelModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h4 class="text-lg font-bold text-slate-900 mb-2">Cancel Purchase</h4>
        <p class="text-sm text-slate-500 mb-4">XP will be refunded to the user.</p>
        <form method="POST" id="cancelForm" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Cancellation Reason</label>
                <textarea name="reason" rows="3" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Why is this being cancelled?"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('cancelModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-red-600 text-white rounded-lg hover:bg-red-700">Cancel Purchase</button>
            </div>
        </form>
    </div>
</div>

{{-- Refund Modal --}}
<div id="refundModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <h4 class="text-lg font-bold text-slate-900 mb-2">Refund Purchase</h4>
        <p class="text-sm text-slate-500 mb-4">XP will be returned to the user and the code recycled.</p>
        <form method="POST" id="refundForm" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Refund Reason</label>
                <textarea name="reason" rows="3" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="Why is this being refunded?"></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('refundModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-purple-600 text-white rounded-lg hover:bg-purple-700">Refund</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
function fulfillOrder(id) {
    document.getElementById('fulfillForm').action = '/admin/store/orders/' + id + '/fulfill';
    document.getElementById('fulfillModal').classList.remove('hidden');
}

function cancelOrder(id) {
    document.getElementById('cancelForm').action = '/admin/store/orders/' + id + '/cancel';
    document.getElementById('cancelModal').classList.remove('hidden');
}

function refundOrder(id) {
    document.getElementById('refundForm').action = '/admin/store/orders/' + id + '/refund';
    document.getElementById('refundModal').classList.remove('hidden');
}
</script>
@endsection
