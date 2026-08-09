@extends('partner.layout')

@section('title', 'Business Registration')

@section('content')
<div class="max-w-lg mx-auto mt-6">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-accent-500 text-white grid place-items-center text-2xl mb-3">
                <i class="fas fa-rocket"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Join as a Business Partner</h2>
            <p class="text-sm text-slate-500 mt-1">Create your account and submit offers after verification</p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                @foreach($errors->all() as $error)
                    <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('partner.register.post') }}" class="space-y-4">
            @csrf

            <div class="border-b border-slate-200 pb-4">
                <h3 class="text-sm font-semibold text-slate-700 mb-3"><i class="fas fa-user text-primary-600"></i> Account Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Your Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password <span class="text-xs text-slate-400">(min 8 chars, letters + numbers)</span></label>
                    <input type="password" name="password" required
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-slate-700 mb-3"><i class="fas fa-store text-primary-600"></i> Business Details</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Business Name</label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}" required
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Business Type</label>
                        <select name="type" required class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                            @foreach($types as $t)
                                <option value="{{ $t }}" @selected(old('type') === $t)>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                        <input type="text" name="address" value="{{ old('address') }}"
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">District</label>
                        <input type="text" name="district" value="{{ old('district') }}"
                               class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Website</label>
                    <input type="url" name="website" value="{{ old('website') }}"
                           class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 outline-none">{{ old('description') }}</textarea>
                </div>
            </div>

            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl py-2.5 transition">
                Create Business Account
            </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
            Already registered? <a href="{{ route('partner.login') }}" class="text-primary-600 font-semibold hover:underline">Sign in</a>
        </p>
    </div>
</div>
@endsection
