@extends('partner.layout')

@section('title', 'My Wallet')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

    {{-- Wallet Card --}}
    <div class="bg-gradient-to-br from-primary-600 via-primary-700 to-primary-900 rounded-2xl shadow-xl p-6 sm:p-8 text-white relative overflow-hidden">
        <div class="absolute top-0 right-0 w-40 h-40 bg-white/5 rounded-full -mr-10 -mt-10"></div>
        <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/5 rounded-full -ml-10 -mb-10"></div>
        <div class="relative">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-primary-200 text-sm font-medium">Available Balance</p>
                    <p class="text-3xl sm:text-4xl font-extrabold mt-1 tracking-tight">Rs. {{ number_format($wallet->balance, 2) }}</p>
                    @if($totalPending > 0)
                        <p class="text-xs text-amber-300 mt-1"><i class="fas fa-clock mr-1"></i>Rs. {{ number_format($totalPending, 2) }} pending withdrawal</p>
                    @endif
                </div>
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white/15 backdrop-blur grid place-items-center text-2xl sm:text-3xl">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 mt-6">
                <div class="bg-white/10 backdrop-blur rounded-xl p-3">
                    <p class="text-primary-200 text-[11px] font-medium">Total Earned</p>
                    <p class="font-bold text-sm sm:text-base">Rs. {{ number_format($wallet->total_earned, 2) }}</p>
                </div>
                <div class="bg-white/10 backdrop-blur rounded-xl p-3">
                    <p class="text-primary-200 text-[11px] font-medium">Total Withdrawn</p>
                    <p class="font-bold text-sm sm:text-base">Rs. {{ number_format($wallet->total_withdrawn, 2) }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <a href="{{ route('partner.wallet.topup') }}" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 hover:shadow-md transition group text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-blue-50 text-blue-600 grid place-items-center text-xl mb-2 group-hover:bg-blue-100 transition">
                <i class="fas fa-plus-circle"></i>
            </div>
            <p class="font-semibold text-slate-800 text-sm">Load Money</p>
            <p class="text-xs text-slate-500 mt-0.5">Add funds to wallet</p>
        </a>
        <a href="{{ route('partner.payments.scan') }}" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 hover:shadow-md transition group text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-600 grid place-items-center text-xl mb-2 group-hover:bg-amber-100 transition">
                <i class="fas fa-qrcode"></i>
            </div>
            <p class="font-semibold text-slate-800 text-sm">Scan / Redeem</p>
            <p class="text-xs text-slate-500 mt-0.5">Scan QR or enter code</p>
        </a>
        <button onclick="document.getElementById('withdraw-modal').classList.remove('hidden');document.getElementById('withdraw-modal').classList.add('flex');" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 hover:shadow-md transition group text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-emerald-50 text-emerald-600 grid place-items-center text-xl mb-2 group-hover:bg-emerald-100 transition">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <p class="font-semibold text-slate-800 text-sm">Withdraw</p>
            <p class="text-xs text-slate-500 mt-0.5">To eSewa / Khalti / Bank</p>
        </button>
        <a href="{{ route('partner.payments.history') }}" class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 hover:shadow-md transition group text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-blue-50 text-blue-600 grid place-items-center text-xl mb-2 group-hover:bg-blue-100 transition">
                <i class="fas fa-history"></i>
            </div>
            <p class="font-semibold text-slate-800 text-sm">History</p>
            <p class="text-xs text-slate-500 mt-0.5">Payment transactions</p>
        </a>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl px-4 py-3 flex items-center gap-2 shadow-sm">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 shadow-sm">
            @foreach($errors->all() as $error)
                <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Recent Payments --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800 text-sm sm:text-base"><i class="fas fa-receipt text-slate-400 mr-2"></i>Recent Payments</h2>
            <a href="{{ route('partner.payments.history') }}" class="text-xs text-primary-600 hover:underline font-medium">View all <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($payments as $payment)
            <div class="px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between hover:bg-slate-50/60 transition">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 grid place-items-center text-emerald-600 shrink-0">
                        <i class="fas fa-plus-circle text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="font-medium text-slate-800 text-sm truncate">{{ $payment->user->name ?? 'User' }}</p>
                        <p class="text-[11px] text-slate-500 truncate">{{ $payment->redeem_code }} &middot; {{ $payment->paid_at?->diffForHumans() }}</p>
                    </div>
                </div>
                <p class="font-bold text-emerald-600 text-sm shrink-0 ml-3">+Rs. {{ number_format($payment->partner_amount, 2) }}</p>
            </div>
            @empty
            <div class="px-6 py-10 text-center text-slate-400">
                <i class="fas fa-receipt text-4xl mb-3 block text-slate-300"></i>
                <p class="text-sm">No payments yet</p>
            </div>
            @endforelse
        </div>
        <div class="px-6 py-3 border-t border-slate-100">{{ $payments->links() }}</div>
    </div>

    {{-- Withdrawal History --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-100">
            <h2 class="font-semibold text-slate-800 text-sm sm:text-base"><i class="fas fa-arrow-up text-slate-400 mr-2"></i>Withdrawal History</h2>
        </div>

        {{-- Desktop Table --}}
        <div class="hidden lg:block">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="text-left px-6 py-3 font-semibold">Date</th>
                        <th class="text-left px-4 py-3 font-semibold">Amount</th>
                        <th class="text-left px-4 py-3 font-semibold">Method</th>
                        <th class="text-left px-4 py-3 font-semibold">Account</th>
                        <th class="text-left px-4 py-3 font-semibold">Status</th>
                        <th class="text-right px-6 py-3 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($withdrawals as $w)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="px-6 py-4 text-slate-600">{{ $w->created_at->format('M j, Y H:i') }}</td>
                            <td class="px-4 py-4 font-semibold text-slate-800">Rs. {{ number_format($w->amount, 2) }}</td>
                            <td class="px-4 py-4 text-slate-600 capitalize">{{ $w->method }}</td>
                            <td class="px-4 py-4 text-slate-500 text-xs">{{ $w->account_detail ?: '—' }}</td>
                            <td class="px-4 py-4">
                                @if($w->status === 'pending')
                                    <span class="text-xs px-3 py-1 rounded-full border bg-amber-100 text-amber-700 border-amber-200">Pending</span>
                                @elseif($w->status === 'completed' || $w->status === 'paid')
                                    <span class="text-xs px-3 py-1 rounded-full border bg-emerald-100 text-emerald-700 border-emerald-200">Paid</span>
                                @elseif($w->status === 'rejected')
                                    <span class="text-xs px-3 py-1 rounded-full border bg-red-100 text-red-700 border-red-200">Rejected</span>
                                @else
                                    <span class="text-xs px-3 py-1 rounded-full border bg-slate-100 text-slate-600 border-slate-200">{{ ucfirst($w->status) }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($w->status === 'pending')
                                    <form method="POST" action="{{ route('partner.withdraw.cancel', $w) }}" onsubmit="return confirm('Cancel this withdrawal?');">
                                        @csrf
                                        <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium transition">Cancel</button>
                                    </form>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-400 text-sm">
                                <i class="fas fa-arrow-up text-4xl mb-3 block text-slate-300"></i>
                                No withdrawals yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile Cards --}}
        <div class="lg:hidden divide-y divide-slate-50">
            @forelse($withdrawals as $w)
                <div class="px-4 py-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold text-sm text-slate-800">Rs. {{ number_format($w->amount, 2) }}</div>
                            <div class="text-[11px] text-slate-500 mt-0.5 capitalize">{{ $w->method }} @if($w->account_detail) &middot; {{ $w->account_detail }} @endif</div>
                        </div>
                        <div class="text-right shrink-0">
                            @if($w->status === 'pending')
                                <span class="text-[10px] px-2 py-0.5 rounded-full border bg-amber-100 text-amber-700 border-amber-200">Pending</span>
                            @elseif($w->status === 'completed' || $w->status === 'paid')
                                <span class="text-[10px] px-2 py-0.5 rounded-full border bg-emerald-100 text-emerald-700 border-emerald-200">Paid</span>
                            @elseif($w->status === 'rejected')
                                <span class="text-[10px] px-2 py-0.5 rounded-full border bg-red-100 text-red-700 border-red-200">Rejected</span>
                            @else
                                <span class="text-[10px] px-2 py-0.5 rounded-full border bg-slate-100 text-slate-600 border-slate-200">{{ ucfirst($w->status) }}</span>
                            @endif
                            <div class="text-[10px] text-slate-400 mt-0.5">{{ $w->created_at->format('M j') }}</div>
                        </div>
                    </div>
                    @if($w->status === 'pending')
                        <div class="mt-2">
                            <form method="POST" action="{{ route('partner.withdraw.cancel', $w) }}" onsubmit="return confirm('Cancel this withdrawal?');">
                                @csrf
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Cancel</button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-6 py-10 text-center text-slate-400 text-sm">
                    <i class="fas fa-arrow-up text-4xl mb-3 block text-slate-300"></i>
                    No withdrawals yet.
                </div>
            @endforelse
        </div>

        @if($withdrawals->hasPages())
            <div class="px-6 py-4 border-t border-slate-100">{{ $withdrawals->links() }}</div>
        @endif
    </div>

    {{-- Withdraw Modal --}}
    <div id="withdraw-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md animate-slide-up">
            <div class="flex items-center justify-between p-5 border-b border-slate-100">
                <h3 class="text-lg font-bold text-slate-900">Withdraw Funds</h3>
                <button onclick="document.getElementById('withdraw-modal').classList.add('hidden');document.getElementById('withdraw-modal').classList.remove('flex');" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 grid place-items-center transition">
                    <i class="fas fa-times text-slate-500"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('partner.withdraw') }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Amount (Rs.)</label>
                    <input type="number" name="amount" min="100" max="{{ $wallet->balance }}" step="1" required
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm" placeholder="e.g. 5000">
                    <p class="text-xs text-slate-400 mt-1">Min Rs. 100 &middot; Available: Rs. {{ number_format($wallet->balance, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Method</label>
                    <select name="method" required class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                        <option value="esewa">eSewa</option>
                        <option value="khalti">Khalti</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Account Detail</label>
                    <input type="text" name="account_detail" required maxlength="100"
                           class="w-full border border-slate-300 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm" placeholder="eSewa/Khalti number or bank account">
                </div>
                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl py-2.5 transition shadow-sm">
                    <i class="fas fa-paper-plane mr-1"></i> Submit Withdrawal
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
