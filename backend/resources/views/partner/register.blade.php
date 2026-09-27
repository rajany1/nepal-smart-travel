@extends('partner.layout')

@section('title', 'Business Registration')

@section('content')
<div class="max-w-lg mx-auto mt-4 sm:mt-6">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-6 sm:p-8">
        <div class="text-center mb-6">
            <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-2xl bg-gradient-to-br from-accent-500 to-accent-600 text-white grid place-items-center text-2xl mb-3 shadow-lg shadow-accent-500/20">
                <i class="fas fa-rocket"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800">Join as a Business Partner</h2>
            <p class="text-sm text-slate-500 mt-1">Create your account and submit offers after verification</p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                @foreach($errors->all() as $error)
                    <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('partner.register.post') }}" class="space-y-5">
            @csrf

            <div class="border-b border-slate-100 pb-5">
                <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-primary-50 grid place-items-center text-primary-600"><i class="fas fa-user text-[10px]"></i></span> Account Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Your Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Password <span class="text-xs text-slate-400 font-normal">(min 8 chars, letters + numbers)</span></label>
                    <input type="password" name="password" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2"><span class="w-6 h-6 rounded-full bg-accent-50 grid place-items-center text-accent-600"><i class="fas fa-store text-[10px]"></i></span> Business Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Business Name</label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Business Type</label>
                        <select name="type" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                            @foreach($types as $t)
                                <option value="{{ $t }}" @selected(old('type') === $t)>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div id="otherTypeWrap" class="mt-4 hidden">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Specify Business Type</label>
                    <input type="text" name="other_type" id="otherTypeInput" maxlength="60" value="{{ old('other_type') }}"
                           placeholder="e.g. Bakery, Cyber cafe, Photography studio"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                    <p class="text-xs text-slate-400 mt-1">Shown as your business category across the platform.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Address</label>
                        <input type="text" name="address" value="{{ old('address') }}"
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">District</label>
                        <input type="text" name="district" value="{{ old('district') }}"
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Website</label>
                    <input type="url" name="website" value="{{ old('website') }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm"
                           placeholder="https://your-site.com">
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none transition text-sm">{{ old('description') }}</textarea>
                </div>
            </div>

            <label class="flex items-start gap-2.5 text-sm text-slate-600 cursor-pointer">
                <input type="checkbox" name="agree_terms" value="1" required
                       class="mt-0.5 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                <span>
                    I agree to the
                    <a href="{{ route('web.legal.show', 'terms') }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 font-semibold hover:underline">Terms &amp; Conditions</a>
                    and
                    <a href="{{ route('web.legal.show', 'business-advertising') }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 font-semibold hover:underline">Business &amp; Advertising Terms</a>
                </span>
            </label>

            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl py-2.5 transition shadow-sm">
                Create Business Account
            </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
            Already registered? <a href="{{ route('partner.login') }}" class="text-primary-600 font-semibold hover:underline">Sign in</a>
        </p>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var sel = document.querySelector('form select[name="type"]');
    var wrap = document.getElementById('otherTypeWrap');
    var input = document.getElementById('otherTypeInput');
    if (!sel || !wrap || !input) return;
    function sync() {
        var show = sel.value === 'other';
        wrap.classList.toggle('hidden', !show);
        input.required = show;
        if (!show) input.value = '';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
