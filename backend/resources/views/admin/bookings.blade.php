@extends('admin.layout')
@section('title', 'Bookings & Commissions')

@section('content')
<div class="space-y-6">
    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary-100 grid place-items-center text-primary-600"><i class="fas fa-calendar-check"></i></div>
                <div><p class="text-xs text-slate-500 font-medium">Total Bookings</p><p class="text-xl font-bold text-slate-900">{{ $stats['total'] }}</p></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-amber-100 grid place-items-center text-amber-600"><i class="fas fa-clock"></i></div>
                <div><p class="text-xs text-slate-500 font-medium">Pending</p><p class="text-xl font-bold text-amber-600">{{ $stats['pending'] }}</p></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 grid place-items-center text-green-600"><i class="fas fa-check-circle"></i></div>
                <div><p class="text-xs text-slate-500 font-medium">Completed</p><p class="text-xl font-bold text-green-600">{{ $stats['completed'] }}</p></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 grid place-items-center text-blue-600"><i class="fas fa-rupee-sign"></i></div>
                <div><p class="text-xs text-slate-500 font-medium">Revenue</p><p class="text-xl font-bold text-slate-900">Rs. {{ number_format($stats['total_revenue'], 0) }}</p></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-purple-100 grid place-items-center text-purple-600"><i class="fas fa-hand-holding-usd"></i></div>
                <div><p class="text-xs text-slate-500 font-medium">Commission</p><p class="text-xl font-bold text-purple-600">Rs. {{ number_format($stats['total_commission'], 0) }}</p></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-rose-100 grid place-items-center text-rose-600"><i class="fas fa-piggy-bank"></i></div>
                <div><p class="text-xs text-slate-500 font-medium">Platform Earned</p><p class="text-xl font-bold text-rose-600">Rs. {{ number_format($stats['total_platform_revenue'], 0) }}</p></div>
            </div>
        </div>
    </div>

    {{-- Search + Header --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Booking & Commission Tracking</h3>
            <p class="text-sm text-slate-500 mt-1">Track bookings through travel partners and earned commissions.</p>
        </div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2 self-start">
            <i class="fas fa-plus"></i> New Booking
        </button>
    </div>

    {{-- Search + Filter Bar --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <form method="GET" action="{{ route('admin.bookings') }}" class="flex-1 flex gap-2">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search customer, partner, phone..." class="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm outline-none focus:border-primary-400">
            </div>
            @if($status)
            <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700">Search</button>
            @if($search)
            <a href="{{ route('admin.bookings', ['status' => $status]) }}" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700"><i class="fas fa-times"></i></a>
            @endif
        </form>
    </div>

    {{-- Status Tabs --}}
    <div class="flex gap-2 flex-wrap">
        <a href="{{ route('admin.bookings', array_filter(['search' => $search])) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$status ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">All ({{ $stats['total'] }})</a>
        <a href="{{ route('admin.bookings', array_filter(['status' => 'pending', 'search' => $search])) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Pending ({{ $stats['pending'] }})</a>
        <a href="{{ route('admin.bookings', array_filter(['status' => 'confirmed', 'search' => $search])) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'confirmed' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Confirmed ({{ $stats['confirmed'] }})</a>
        <a href="{{ route('admin.bookings', array_filter(['status' => 'completed', 'search' => $search])) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'completed' ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Completed ({{ $stats['completed'] }})</a>
        <a href="{{ route('admin.bookings', array_filter(['status' => 'cancelled', 'search' => $search])) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'cancelled' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Cancelled ({{ $stats['cancelled'] }})</a>
    </div>

    {{-- Bookings Table --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider w-12">#</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Partner</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Commission</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Coupon</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $b)
                    <tr class="hover:bg-slate-50 transition cursor-pointer booking-row" data-id="{{ $b->id }}">
                        <td class="px-4 py-3 text-xs text-slate-400 font-mono">#{{ $b->id }}</td>
                        <td class="px-4 py-3">
                            <p class="font-semibold text-sm text-slate-900">{{ $b->customer_name }}</p>
                            <p class="text-xs text-slate-400">
                                @if($b->customer_phone){{ $b->customer_phone }}@endif
                                @if($b->customer_email){{ $b->customer_phone ? ' • ' : '' }}{{ $b->customer_email }}@endif
                            </p>
                            @if($b->user)
                            <a href="#" class="text-xs text-primary-500 hover:underline"><i class="fas fa-user"></i> {{ $b->user->name }}</a>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-sm font-medium">{{ $b->travelPartner->name }}</p>
                            <p class="text-xs text-slate-400">
                                {{ str_replace('_', ' ', $b->travelPartner->type) }}
                                @if($b->travelPartner->district) • {{ $b->travelPartner->district }}@endif
                            </p>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <p class="font-semibold text-sm">Rs. {{ number_format($b->amount, 2) }}</p>
                            @if($b->discount_amount > 0)
                            <p class="text-xs text-green-600">-Rs. {{ number_format($b->discount_amount, 2) }}</p>
                            <p class="text-xs font-bold text-slate-700">= Rs. {{ number_format($b->amount - $b->discount_amount, 2) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <p class="text-xs text-green-600 font-medium">+Rs. {{ number_format($b->commission_earned, 2) }}</p>
                            <p class="text-xs text-amber-500">Pool: Rs. {{ number_format($b->reward_pool_share, 2) }}</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($b->shopCode)
                            <span class="inline-flex items-center gap-1 text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-medium">
                                <i class="fas fa-tag"></i>
                                {{ $b->shopCode->shopItem?->name ?? 'Coupon' }}
                            </span>
                            @if($b->shopCode->consumed_at)
                            <p class="text-[10px] text-green-600 mt-0.5"><i class="fas fa-check"></i> Consumed</p>
                            @elseif($b->shopCode->applied_at)
                            <p class="text-[10px] text-amber-500 mt-0.5">Applied</p>
                            @endif
                            @else
                            <span class="text-xs text-slate-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @switch($b->status)
                                @case('pending')<span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-0.5 rounded-full font-medium"><i class="fas fa-clock mr-1"></i>Pending</span>@break
                                @case('confirmed')<span class="text-xs bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-full font-medium"><i class="fas fa-check mr-1"></i>Confirmed</span>@break
                                @case('completed')<span class="text-xs bg-green-100 text-green-700 px-2.5 py-0.5 rounded-full font-medium"><i class="fas fa-check-double mr-1"></i>Completed</span>@break
                                @case('cancelled')<span class="text-xs bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full font-medium"><i class="fas fa-times mr-1"></i>Cancelled</span>@break
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500 whitespace-nowrap">{{ $b->booked_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                @if($b->isPending())
                                <form method="POST" action="{{ route('admin.bookings.confirm', $b) }}" class="inline">
                                    @csrf
                                    <button class="px-2 py-1.5 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition" title="Confirm"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="POST" action="{{ route('admin.bookings.cancel', $b) }}" class="inline" onsubmit="return confirm('Cancel this booking?')">
                                    @csrf
                                    <button class="px-2 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Cancel"><i class="fas fa-times"></i></button>
                                </form>
                                @endif
                                @if($b->isConfirmed())
                                <form method="POST" action="{{ route('admin.bookings.complete', $b) }}" class="inline">
                                    @csrf
                                    <button class="px-2 py-1.5 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition" title="Complete"><i class="fas fa-check-double"></i></button>
                                </form>
                                @endif
                                <button onclick="toggleDetail({{ $b->id }})" class="px-2 py-1.5 text-xs font-medium bg-slate-50 text-slate-500 rounded-lg hover:bg-slate-100 transition" title="Details">
                                    <i class="fas fa-chevron-down detail-icon-{{ $b->id }}"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr id="detail-{{ $b->id }}" class="hidden">
                        <td colspan="9" class="px-4 py-0 bg-slate-50">
                            <div class="border-t border-slate-200 px-6 py-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    {{-- Left: Customer & Notes --}}
                                    <div>
                                        <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-user mr-1"></i> Customer Details</h5>
                                        <div class="space-y-1 text-sm">
                                            @if($b->customer_email)<p><span class="text-slate-500">Email:</span> {{ $b->customer_email }}</p>@endif
                                            @if($b->customer_phone)<p><span class="text-slate-500">Phone:</span> {{ $b->customer_phone }}</p>@endif
                                            @if($b->user)<p><span class="text-slate-500">User:</span> {{ $b->user->name }} (ID: {{ $b->user->id }})</p>@endif
                                            @if($b->notes)<p><span class="text-slate-500">Notes:</span> {{ $b->notes }}</p>@endif
                                        </div>
                                    </div>
                                    {{-- Middle: Commission --}}
                                    <div>
                                        <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-chart-pie mr-1"></i> Commission Breakdown</h5>
                                        @php $ct = $b->commissionTransaction; @endphp
                                        <div class="space-y-1 text-sm">
                                            <p><span class="text-slate-500">Total:</span> <span class="font-semibold text-green-600">Rs. {{ number_format($b->commission_earned, 2) }}</span></p>
                                            <p><span class="text-slate-500">Reward Pool:</span> <span class="font-semibold text-amber-600">Rs. {{ number_format($b->reward_pool_share, 2) }}</span></p>
                                            @if($ct)
                                            <p><span class="text-slate-500">Platform:</span> <span class="font-semibold text-rose-600">Rs. {{ number_format($ct->platform_revenue, 2) }}</span></p>
                                            <p><span class="text-slate-500">Status:</span>
                                                @switch($ct->status)
                                                    @case('pending')<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Pending</span>@break
                                                    @case('paid')<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Paid{{ $ct->paid_at ? ' ('.$ct->paid_at->format('M d, Y').')' : '' }}</span>@break
                                                    @case('cancelled')<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Cancelled</span>@break
                                                @endswitch
                                            </p>
                                            @endif
                                        </div>
                                    </div>
                                    {{-- Right: Timeline & Coupon --}}
                                    <div>
                                        <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-clock mr-1"></i> Timeline</h5>
                                        <div class="space-y-1 text-sm">
                                            <p><span class="text-slate-500">Booked:</span> {{ $b->booked_at->format('M d, Y h:i A') }}</p>
                                            @if($b->confirmed_at)<p><span class="text-slate-500">Confirmed:</span> {{ $b->confirmed_at->format('M d, Y h:i A') }}</p>@endif
                                            @if($b->completed_at)<p><span class="text-slate-500">Completed:</span> {{ $b->completed_at->format('M d, Y h:i A') }}</p>@endif
                                        </div>
                                        @if($b->shopCode)
                                        <div class="mt-3 pt-3 border-t border-slate-200">
                                            <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2"><i class="fas fa-tag mr-1"></i> Coupon Details</h5>
                                            <div class="space-y-1 text-sm">
                                                <p><span class="text-slate-500">Code:</span> <code class="bg-slate-200 px-1.5 py-0.5 rounded text-xs font-mono">{{ $b->shopCode->code }}</code></p>
                                                @if($b->shopCode->shopItem)<p><span class="text-slate-500">Reward:</span> {{ $b->shopCode->shopItem->name }}</p>@endif
                                                @if($b->shopCode->applied_at)<p><span class="text-slate-500">Applied:</span> {{ $b->shopCode->applied_at->format('M d, Y') }}</p>@endif
                                                @if($b->shopCode->consumed_at)<p><span class="text-slate-500">Consumed:</span> {{ $b->shopCode->consumed_at->format('M d, Y') }}</p>@endif
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-calendar-check text-3xl mb-3"></i>
                            <p class="text-sm">No bookings found.</p>
                            @if($search || $status)
                            <a href="{{ route('admin.bookings') }}" class="text-xs text-primary-500 hover:underline mt-1 inline-block">Clear filters</a>
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($bookings->hasPages())
    <div class="mt-4">{{ $bookings->appends(['status' => $status, 'search' => $search])->links() }}</div>
    @endif
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold">New Booking</h4>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.bookings.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="user_id" id="userIdInput" value="">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Partner</label>
                <select name="travel_partner_id" id="partnerSelect" required onchange="autofillAmount(); previewCommission(); filterRewards()" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select Partner —</option>
                    @foreach($partners as $p)
                    <option value="{{ $p->id }}" data-rate="{{ $p->commission_rate }}" data-fixed="{{ $p->commission_fixed }}" data-district="{{ $p->district ?? '' }}" data-value-npr="{{ $p->value_npr }}">{{ $p->name }} ({{ $p->commission_rate }}% + Rs.{{ number_format($p->commission_fixed) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Select User</label>
                    <select id="userSelect" onchange="selectUser()" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Select User —</option>
                        @foreach($users as $u)
                        <option value="{{ $u->id }}" data-name="{{ $u->name }}" data-phone="{{ $u->phone ?? '' }}" data-email="{{ $u->email ?? '' }}">{{ $u->name }} — {{ $u->email ?? $u->phone ?? 'No contact' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Amount (Rs.)</label>
                    <input type="number" name="amount" id="amountInput" step="0.01" min="0" required oninput="previewCommission(); updateRewardDiscount()" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Customer Name</label>
                    <input type="text" name="customer_name" id="customerNameInput" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                    <input type="text" name="customer_phone" id="customerPhoneInput" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                <input type="email" name="customer_email" id="customerEmailInput" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Booking Date</label>
                <input type="date" name="booked_at" value="{{ date('Y-m-d') }}" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>

            {{-- Reward / Coupon Section --}}
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 space-y-3">
                <p class="text-xs font-semibold text-purple-700 uppercase tracking-wider"><i class="fas fa-tag mr-1"></i> Reward & Discount</p>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">User's Available Reward <span id="rewardFilterInfo" class="text-xs text-slate-400 font-normal"></span></label>
                    <select name="shop_code_id" id="rewardCodeSelect" onchange="applyRewardByCode()" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">— Select user & partner first —</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Discount Amount (Rs.)</label>
                        <input type="number" name="discount_amount" id="discountAmountInput" step="0.01" min="0" value="0" readonly class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Net Amount (Rs.)</label>
                        <input type="text" id="netAmountDisplay" readonly value="0.00" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 text-slate-700 font-semibold">
                    </div>
                </div>
            </div>

            {{-- Commission Preview --}}
            <div id="commissionPreview" class="hidden bg-slate-50 border border-slate-200 rounded-lg p-4 space-y-1 text-sm">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Commission Preview</p>
                <div class="flex justify-between"><span class="text-slate-600">Rate:</span><span id="previewRate" class="font-medium">—</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Total Commission:</span><span id="previewCommission" class="font-semibold text-green-600">—</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Reward Pool (25%):</span><span id="previewReward" class="font-semibold text-amber-600">—</span></div>
                <div class="flex justify-between"><span class="text-slate-600">Platform Revenue (75%):</span><span id="previewPlatform" class="font-semibold text-rose-600">—</span></div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700">Create Booking</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
let userCodes = @json($userCodes);

function autofillAmount() {
    const sel = document.getElementById('partnerSelect');
    const opt = sel.options[sel.selectedIndex];
    const npr = parseFloat(opt?.dataset?.valueNpr) || 0;
    if (npr > 0) {
        document.getElementById('amountInput').value = npr;
        document.getElementById('amountInput').dispatchEvent(new Event('input'));
    }
}

function previewCommission() {
    const sel = document.getElementById('partnerSelect');
    const amount = parseFloat(document.getElementById('amountInput').value) || 0;
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value || amount <= 0) {
        document.getElementById('commissionPreview').classList.add('hidden');
        return;
    }
    const rate = parseFloat(opt.dataset.rate) || 0;
    const fixed = parseFloat(opt.dataset.fixed) || 0;
    const commission = (amount * rate / 100) + fixed;
    const reward = commission * 0.25;
    const platform = commission - reward;

    document.getElementById('previewRate').textContent = rate + '%' + (fixed > 0 ? ' + Rs. ' + fixed.toFixed(2) : '');
    document.getElementById('previewCommission').textContent = 'Rs. ' + commission.toFixed(2);
    document.getElementById('previewReward').textContent = 'Rs. ' + reward.toFixed(2);
    document.getElementById('previewPlatform').textContent = 'Rs. ' + platform.toFixed(2);
    document.getElementById('commissionPreview').classList.remove('hidden');
}

function selectUser() {
    const sel = document.getElementById('userSelect');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('userIdInput').value = opt && opt.value ? opt.value : '';
    document.getElementById('customerNameInput').value = opt && opt.value ? (opt.dataset.name || '') : '';
    document.getElementById('customerPhoneInput').value = opt && opt.value ? (opt.dataset.phone || '') : '';
    document.getElementById('customerEmailInput').value = opt && opt.value ? (opt.dataset.email || '') : '';
    filterRewards();
}

function filterRewards() {
    const codeSelect = document.getElementById('rewardCodeSelect');
    const userSel = document.getElementById('userSelect');
    const userOpt = userSel.options[userSel.selectedIndex];
    const userId = userOpt?.value || '';

    codeSelect.innerHTML = '<option value="">— No Reward —</option>';

    if (!userId) {
        document.getElementById('rewardFilterInfo').textContent = '(select a user first)';
        return;
    }

    const codes = userCodes[userId] || [];
    if (!codes.length) {
        document.getElementById('rewardFilterInfo').textContent = '(no codes available)';
        return;
    }

    const partnerId = document.getElementById('partnerSelect').value;

    let filtered = codes;
    if (partnerId) {
        filtered = codes.filter(c => c.sponsor_travel_partner_id == partnerId);
    }

    document.getElementById('rewardFilterInfo').textContent = '(' + filtered.length + ' available' + ')';

    filtered.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.dataset.discountType = c.discount_type || '';
        opt.dataset.discountValue = c.discount_value;
        opt.dataset.valueNpr = c.value_npr;
        opt.textContent = c.shop_item_name + ' — Rs.' + c.value_npr.toLocaleString() + (c.discount_type ? ' [' + (c.discount_type === 'percentage' ? c.discount_value + '%' : 'Rs.' + c.discount_value + ' off') + ']' : '');
        codeSelect.appendChild(opt);
    });
}

function applyRewardByCode() {
    const codeSelect = document.getElementById('rewardCodeSelect');
    const amount = parseFloat(document.getElementById('amountInput').value) || 0;
    const opt = codeSelect.options[codeSelect.selectedIndex];
    const discountInput = document.getElementById('discountAmountInput');

    if (!opt || !opt.value) {
        discountInput.value = 0;
        discountInput.dispatchEvent(new Event('input'));
        return;
    }

    let discount = 0;
    if (opt.dataset.discountType === 'fixed') {
        discount = parseFloat(opt.dataset.discountValue) || 0;
    } else if (opt.dataset.discountType === 'percentage') {
        discount = amount * (parseFloat(opt.dataset.discountValue) || 0) / 100;
    } else {
        discount = parseFloat(opt.dataset.valueNpr) || 0;
    }

    discount = Math.min(discount, amount);
    discountInput.value = discount.toFixed(2);
    discountInput.dispatchEvent(new Event('input'));
}

function updateRewardDiscount() {
    const codeSelect = document.getElementById('rewardCodeSelect');
    if (codeSelect.value) applyRewardByCode();
}

document.getElementById('amountInput').addEventListener('input', function() {
    const amount = parseFloat(this.value) || 0;
    const discount = parseFloat(document.getElementById('discountAmountInput').value) || 0;
    document.getElementById('netAmountDisplay').value = Math.max(0, amount - discount).toFixed(2);
    if (document.getElementById('rewardCodeSelect').value) applyRewardByCode();
});



function toggleDetail(id) {
    const row = document.getElementById('detail-' + id);
    const icon = document.querySelector('.detail-icon-' + id);
    if (row.classList.contains('hidden')) {
        row.classList.remove('hidden');
        if (icon) icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
    } else {
        row.classList.add('hidden');
        if (icon) icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
    }
}
</script>
@endsection