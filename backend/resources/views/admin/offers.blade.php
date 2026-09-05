@extends('admin.layout')

@section('title', 'Reward Offers')

@section('content')
@php
    $statusColors = [
        'pending' => 'bg-amber-100 text-amber-700 border-amber-200',
        'approved' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-red-100 text-red-700 border-red-200',
        'paused' => 'bg-slate-100 text-slate-600 border-slate-200',
    ];
    $typeLabels = [
        'percentage_off' => '% Off',
        'fixed_off' => 'Rs. Off',
        'free_item' => 'Free Item',
        'buy_one_get_one' => 'BOGO',
    ];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-bold text-slate-800">Reward Offers</h2>
    <div class="flex rounded-xl border border-slate-200 bg-white overflow-hidden text-sm">
        <a href="{{ route('admin.offers') }}" class="px-4 py-2 {{ !$status && !$deleted ? 'bg-accent-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">All</a>
        @foreach(['approved', 'paused'] as $s)
            <a href="{{ route('admin.offers', ['status' => $s]) }}" class="px-4 py-2 {{ $status === $s ? 'bg-accent-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">{{ ucfirst($s) }}</a>
        @endforeach
        <a href="{{ route('admin.offers', ['deleted' => 1]) }}" class="px-4 py-2 {{ $deleted ? 'bg-red-500 text-white' : 'text-slate-600 hover:bg-slate-50' }}">Deleted {{ $stats['deleted'] ? "($stats[deleted])" : '' }}</a>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-slate-800">{{ $stats['total'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Total</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-emerald-600">{{ $stats['approved'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Live</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-accent-500">{{ $stats['claims'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Claims</div>
    </div>
    <div class="bg-white rounded-2xl shadow p-4">
        <div class="text-2xl font-bold text-red-500">{{ $stats['deleted'] }}</div>
        <div class="text-xs text-slate-500 mt-1">Deleted</div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
<div id="liveTable">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-6 py-3">Offer</th>
                    <th class="text-left px-4 py-3">Business</th>
                    <th class="text-left px-4 py-3">Type</th>
                    <th class="text-left px-4 py-3">Value (Rs.)</th>
                    <th class="text-left px-4 py-3">XP Price</th>
                    <th class="text-left px-4 py-3">Claims</th>
                    <th class="text-left px-4 py-3">Valid Until</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-right px-6 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($offers as $offer)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if($offer->image)
                                    <img src="{{ asset('storage/' . $offer->image) }}" alt="" class="w-10 h-10 rounded-lg object-cover flex-shrink-0">
                                @endif
                                <div>
                                    <div class="font-medium text-slate-800">{{ $offer->title }}</div>
                            @if($offer->trashed() && $offer->admin_removed_reason)
                                <div class="text-xs text-red-500 mt-0.5"><i class="fas fa-exclamation-triangle"></i> Removed: {{ $offer->admin_removed_reason }}</div>
                            @elseif($offer->status === 'rejected' && $offer->rejection_reason)
                                <div class="text-xs text-red-500 mt-0.5"><i class="fas fa-times-circle"></i> {{ $offer->rejection_reason }}</div>
                            @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <div class="text-slate-700">{{ $offer->business?->name ?? 'â€”' }}</div>
                            @if($offer->business && $offer->business->verification_status === 'verified')
                                <span class="text-xs text-emerald-600"><i class="fas fa-check-circle"></i> verified</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-slate-600">
                            {{ $typeLabels[$offer->offer_type] ?? $offer->offer_type }}
                            @if(in_array($offer->offer_type, ['percentage_off', 'fixed_off']) && $offer->discount_value !== null)
                                <span class="text-slate-800 font-semibold">{{ $offer->offer_type === 'percentage_off' ? $offer->discount_value . '%' : 'Rs. ' . number_format((float) $offer->discount_value) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            @if($offer->trashed())
                                <span class="text-slate-700 font-medium">Rs. {{ number_format((float) $offer->value_npr, 2) }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.offers.value', $offer) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <input type="number" step="0.01" min="0" name="value_npr" value="{{ number_format((float) $offer->value_npr, 2, '.', '') }}"
                                           class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:ring-2 focus:ring-primary-500 outline-none">
                                    <button type="submit" title="Save value"
                                            class="w-7 h-7 grid place-items-center rounded-lg text-slate-400 hover:bg-primary-50 hover:text-primary-600">
                                        <i class="fas fa-check text-xs"></i>
                                    </button>
                                </form>
                            @endif
                            @if($offer->value_npr_locked)
                                <span class="text-[10px] text-amber-600"><i class="fas fa-lock"></i> locked (partner can't change)</span>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            <span class="text-xs px-2.5 py-1 rounded-lg bg-primary-50 text-primary-700 font-semibold">{{ $offer->price_xp }} XP</span>
                        </td>
                        <td class="px-4 py-4 text-slate-700">{{ $offer->used_count }} / {{ $offer->usage_limit ?: 'âˆž' }}</td>
                        <td class="px-4 py-4 {{ $offer->ends_at && $offer->ends_at->lte(now()) ? 'text-red-500' : 'text-slate-600' }}">{{ $offer->ends_at ? $offer->ends_at->format('M j, Y') : 'No expiry' }}</td>
                        <td class="px-4 py-4">
                            @if($offer->trashed())
                                <span class="text-xs px-3 py-1 rounded-full border bg-red-100 text-red-700 border-red-200">Removed</span>
                            @else
                                <span class="text-xs px-3 py-1 rounded-full border {{ $statusColors[$offer->status] ?? '' }}">{{ ucfirst($offer->status) }}</span>
                                @if($offer->paused_by === 'system')
                                    <span class="block text-[10px] text-red-500 mt-0.5">Ended â€” locked by system</span>
                                @elseif($offer->paused_by === 'admin')
                                    <span class="block text-[10px] text-orange-500 mt-0.5">Paused by admin</span>
                                @elseif($offer->paused_by === 'partner')
                                    <span class="block text-[10px] text-orange-500 mt-0.5">Paused by partner</span>
                                @elseif($offer->ends_at && $offer->ends_at->lte(now()) && in_array($offer->status, ['approved', 'paused']))
                                    <span class="block text-[10px] text-red-500 mt-0.5">Ended</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                @if($offer->trashed())
                                    <form method="POST" action="{{ route('admin.offers.restore', $offer) }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                            <i class="fas fa-undo"></i> Restore
                                        </button>
                                    </form>
                                @else
                                    @if($offer->status === 'approved')
                                        <form method="POST" action="{{ route('admin.offers.pause', $offer) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                                <i class="fas fa-pause"></i> Pause
                                            </button>
                                        </form>
                                    @elseif($offer->status === 'paused' && $offer->paused_by !== 'system')
                                        <form method="POST" action="{{ route('admin.offers.resume', $offer) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                                <i class="fas fa-play"></i> Resume
                                            </button>
                                        </form>
                                    @elseif($offer->status === 'paused' && $offer->paused_by === 'system')
                                        <span class="text-[10px] text-red-500 font-medium px-2">Locked</span>
                                    @endif
                                    <button type="button" onclick="showDelete({{ $offer->id }}, '{{ addslashes($offer->title) }}')" title="Delete offer"
                                            class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg px-3 py-2">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-gift text-3xl mb-3 block"></i>
                            @if($deleted) No deleted offers. @elseif($status) No {{ $status }} offers yet. @else No offers submitted yet. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4">{{ $offers->links() }}</div>
</div>
</div>

<!-- Delete modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">Delete Offer</h3>
        <p class="text-sm text-slate-500 mb-1" id="deleteTitle"></p>
        <p class="text-sm text-amber-600 mb-4"><i class="fas fa-exclamation-triangle"></i> The offer will be removed from the app. This message will be shown to the partner.</p>
        <form method="POST" action="" id="deleteForm">
            @csrf
            <label class="block text-sm font-medium text-slate-700 mb-1">Message for the partner (required)</label>
            <textarea name="reason" rows="3" required
                      class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-red-500 outline-none"
                      placeholder="e.g. This offer violates our terms, please contact support."></textarea>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeDelete()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg px-5 py-2">Delete Offer</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showDelete(id, title) {
        document.getElementById('deleteForm').action = '/' + window.adminPrefix + '/offers/' + id + '/delete';
        document.getElementById('deleteTitle').textContent = title;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeDelete() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
</script>
@endsection
