@extends('admin.layout')
@section('title', 'Bookings')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Booking & Commission Tracking</h3>
            <p class="text-sm text-slate-500 mt-1">Track bookings through travel partners and earned commissions.</p>
        </div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Booking
        </button>
    </div>

    <div class="flex gap-2">
        <a href="{{ route('admin.bookings') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$status ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600' }}">All</a>
        <a href="{{ route('admin.bookings', ['status' => 'pending']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600' }}">Pending</a>
        <a href="{{ route('admin.bookings', ['status' => 'confirmed']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'confirmed' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' }}">Confirmed</a>
        <a href="{{ route('admin.bookings', ['status' => 'completed']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'completed' ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-600' }}">Completed</a>
        <a href="{{ route('admin.bookings', ['status' => 'cancelled']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'cancelled' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600' }}">Cancelled</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Partner</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Commission</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Reward Pool</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $b)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <p class="font-semibold text-sm">{{ $b->customer_name }}</p>
                            @if($b->customer_phone)<p class="text-xs text-slate-400">{{ $b->customer_phone }}</p>@endif
                        </td>
                        <td class="px-6 py-4 text-sm">{{ $b->travelPartner->name }}</td>
                        <td class="px-6 py-4 text-right font-semibold">Rs. {{ number_format($b->amount, 2) }}</td>
                        <td class="px-6 py-4 text-right text-green-600 font-semibold">Rs. {{ number_format($b->commission_earned, 2) }}</td>
                        <td class="px-6 py-4 text-right text-amber-600 font-semibold">Rs. {{ number_format($b->reward_pool_share, 2) }}</td>
                        <td class="px-6 py-4 text-center">
                            @switch($b->status)
                                @case('pending')<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Pending</span>@break
                                @case('confirmed')<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Confirmed</span>@break
                                @case('completed')<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Completed</span>@break
                                @case('cancelled')<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Cancelled</span>@break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500">{{ $b->booked_at->format('Y-m-d') }}</td>
                        <td class="px-6 py-4 text-right">
                            @if($b->isPending())
                            <form method="POST" action="{{ route('admin.bookings.confirm', $b) }}" class="inline">
                                @csrf
                                <button class="px-2 py-1 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100"><i class="fas fa-check"></i> Confirm</button>
                            </form>
                            <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" class="inline" onsubmit="return confirm('Cancel this booking?')">
                                @csrf
                                <button class="px-2 py-1 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"><i class="fas fa-times"></i></button>
                            </form>
                            @endif
                            @if($b->isConfirmed())
                            <form method="POST" action="{{ route('admin.bookings.complete', $b) }}" class="inline">
                                @csrf
                                <button class="px-2 py-1 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100"><i class="fas fa-check-double"></i> Complete</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-calendar-check text-3xl mb-3"></i><p class="text-sm">No bookings yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $bookings->appends(['status' => $status])->links() }}</div>
</div>

<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold">New Booking</h4>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.bookings.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Partner</label>
                <select name="travel_partner_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    @foreach($partners as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->commission_rate }}%)</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Customer Name</label><input type="text" name="customer_name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Amount (Rs.)</label><input type="number" name="amount" step="0.01" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Phone</label><input type="text" name="customer_phone" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Email</label><input type="email" name="customer_email" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div><label>Notes</label><textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Create Booking</button>
            </div>
        </form>
    </div>
</div>
@endsection
