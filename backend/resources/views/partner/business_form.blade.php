@extends('partner.layout')

@section('title', 'Complete Business Profile')

@section('content')
<div class="max-w-lg mx-auto mt-4 sm:mt-6">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-5 sm:p-8">
        <div class="mb-5">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800">Business Profile</h2>
            <p class="text-sm text-slate-500 mt-1">
                @if(isset($partner) && $partner && $partner->verification_status === 'rejected')
                    <span class="inline-flex items-center gap-1.5 text-red-600 font-medium"><i class="fas fa-times-circle"></i> Your previous application was rejected: {{ $partner->rejected_reason ?? 'No reason given' }}</span>
                    <br>Please correct the details below and resubmit.
                @else
                    Complete your business details to proceed.
                @endif
            </p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                @foreach($errors->all() as $error)
                    <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('partner.business-form.post') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Business Name</label>
                    <input type="text" name="business_name" value="{{ old('business_name', $partner->name ?? '') }}" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Business Type</label>
                    <select name="type" id="bizTypeSelect" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                        @foreach($types as $t)
                            <option value="{{ $t }}" @selected(old('type', $partner->type ?? '') === $t)>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="otherTypeWrap" class="hidden">
                <label class="block text-sm font-medium text-slate-700 mb-1">Specify Business Type</label>
                <input type="text" name="other_type" id="otherTypeInput" maxlength="60"
                       value="{{ old('other_type', $partner->type_label ?? '') }}"
                       placeholder="e.g. Bakery, Cyber cafe, Photography studio"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                <p class="text-xs text-slate-400 mt-1">Shown as your business category across the platform.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $partner->phone ?? '') }}" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                    <input type="text" name="address" value="{{ old('address', $partner->address ?? '') }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">District</label>
                    <input type="text" name="district" value="{{ old('district', $partner->district ?? '') }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Website</label>
                <input type="url" name="website" value="{{ old('website', $partner->website ?? '') }}"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="3"
                          class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">{{ old('description', $partner->description ?? '') }}</textarea>
            </div>
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl py-2.5 transition">
                Submit for Verification
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var sel = document.getElementById('bizTypeSelect');
    var wrap = document.getElementById('otherTypeWrap');
    var input = document.getElementById('otherTypeInput');
    if (!sel || !wrap || !input) return;
    function sync() {
        var show = sel.value === 'other';
        wrap.classList.toggle('hidden', !show);
        input.required = show;
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
