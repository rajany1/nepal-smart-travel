@extends('partner.layout')

@section('title', 'Partner Login')

@section('content')
<div class="max-w-md mx-auto mt-6 sm:mt-10">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-6 sm:p-8">
        <div class="text-center mb-6">
            <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 text-white grid place-items-center text-2xl mb-3 shadow-lg shadow-primary-600/20">
                <i class="fas fa-store"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-800">Business Partner Login</h2>
            <p class="text-sm text-slate-500 mt-1">Sign in to manage your reward offers</p>
        </div>

        @if(session('error'))
            <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl px-4 py-3 mb-4 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                @foreach($errors->all() as $error)
                    <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('partner.login.post') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition text-sm"
                       placeholder="your@email.com">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                <input type="password" name="password" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition text-sm"
                       placeholder="Enter password">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"> Remember me
            </label>
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl py-2.5 transition shadow-sm">
                Sign In
            </button>
        </form>

        <div class="mt-6 text-center space-y-2">
            <p class="text-sm text-slate-500">
                New business? <a href="{{ route('partner.register') }}" class="text-primary-600 font-semibold hover:underline">Create an account</a>
            </p>
            <p class="text-sm text-slate-400">
                <a href="/" class="hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to site</a>
            </p>
        </div>
    </div>
</div>
@endsection
