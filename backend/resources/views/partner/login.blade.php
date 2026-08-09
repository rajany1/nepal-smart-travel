@extends('partner.layout')

@section('title', 'Partner Login')

@section('content')
<div class="max-w-md mx-auto mt-10">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-primary-600 text-white grid place-items-center text-2xl mb-3">
                <i class="fas fa-store"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Business Partner Login</h2>
            <p class="text-sm text-slate-500 mt-1">Sign in to manage your reward offers</p>
        </div>

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
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                <input type="password" name="password" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300"> Remember me
            </label>
            <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl py-2.5 transition">
                Sign In
            </button>
        </form>

        <p class="text-center text-sm text-slate-500 mt-6">
            New business? <a href="{{ route('partner.register') }}" class="text-primary-600 font-semibold hover:underline">Create an account</a>
        </p>
        <p class="text-center text-sm text-slate-500 mt-2">
            <a href="/" class="text-slate-400 hover:underline"><i class="fas fa-arrow-left"></i> Back to site</a>
        </p>
    </div>
</div>
@endsection
