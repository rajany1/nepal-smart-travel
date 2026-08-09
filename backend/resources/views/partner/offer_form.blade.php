@extends('partner.layout')

@section('title', $offer->exists ? 'Edit Offer' : 'New Offer')

@section('content')
@php
    $types = [
        'percentage_off' => 'Percentage Off (e.g. 10% off)',
        'fixed_off' => 'Fixed Amount Off (e.g. Rs. 500 off)',
        'free_item' => 'Free Item (e.g. free coffee)',
        'buy_one_get_one' => 'Buy One Get One',
    ];
@endphp

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <h2 class="text-2xl font-bold text-slate-800 mb-1">{{ $offer->exists ? 'Edit Offer' : 'Create New Offer' }}</h2>
        <p class="text-sm text-slate-500 mb-6">New and edited offers require admin approval before going live.</p>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                @foreach($errors->all() as $error)
                    <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ $offer->exists ? route('partner.offers.update', $offer) : route('partner.offers.store') }}" class="space-y-5">
            @csrf
            @if($offer->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Offer Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $offer->title) }}" required maxlength="255"
                       placeholder="e.g. 20% off on breakfast buffet"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Offer Type <span class="text-red-500">*</span></label>
                    <select name="offer_type" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                        @foreach($types as $val => $label)
                            <option value="{{ $val }}" @selected(old('offer_type', $offer->offer_type) === $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Discount Value <span class="text-xs text-slate-400">(only for % / Rs.)</span></label>
                    <input type="number" step="0.01" min="0" name="discount_value" id="discount_value" value="{{ old('discount_value', $offer->discount_value) }}"
                           oninput="updateXpPrice()"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Offer Value (Rs.) <span class="text-xs text-slate-400">(what the offer is worth to the customer)</span></label>
                <input type="number" step="0.01" min="0" name="value_npr" value="{{ old('value_npr', $offer->value_npr ?? 0) }}"
                       @if($offer->value_npr_locked) disabled @endif
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none @if($offer->value_npr_locked) bg-slate-50 text-slate-500 @endif">
                <p class="text-xs text-slate-400 mt-1">When a user uses this offer, you earn the value minus the admin commission (set in admin Settings).</p>
                @if($offer->value_npr_locked)
                    <p class="text-xs text-amber-600 mt-1"><i class="fas fa-lock"></i> Value is locked after admin approval. Contact the admin to change it.</p>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">XP Price <span class="text-xs text-slate-400">(auto: Rs. 1 = 1 XP)</span></label>
                <input type="number" name="price_xp_preview" id="price_xp_preview" value="{{ old('price_xp_preview', $offer->price_xp ?: 0) }}" disabled
                       class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-500">
                <p class="text-xs text-slate-400 mt-1">Users spend this much XP to redeem. Admin can change the Rs.-to-XP ratio in Settings.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="3"
                          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">{{ old('description', $offer->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Terms & Conditions</label>
                <textarea name="terms" rows="2" placeholder="e.g. Valid on dine-in only. Cannot be combined with other offers."
                          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">{{ old('terms', $offer->terms) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Starts At</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $offer->starts_at?->format('Y-m-d\TH:i')) }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ends At</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $offer->ends_at?->format('Y-m-d\TH:i')) }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Usage Limit <span class="text-red-500">*</span></label>
                    <input type="number" min="0" name="usage_limit" value="{{ old('usage_limit', $offer->usage_limit ?? 0) }}" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                    <p class="text-xs text-slate-400 mt-1">0 = unlimited</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('partner.offers') }}" class="px-5 py-2.5 text-sm text-slate-600 hover:text-slate-800">Cancel</a>
                <button type="submit" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition">
                    <i class="fas fa-paper-plane"></i> {{ $offer->exists ? 'Save Changes' : 'Submit for Approval' }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateXpPrice() {
    var v = parseFloat(document.getElementById('discount_value').value) || 0;
    var p = document.getElementById('price_xp_preview');
    if (p) p.value = Math.max(1, Math.round(v));
}
updateXpPrice();
</script>
@endsection
