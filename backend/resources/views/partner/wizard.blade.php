@extends('partner.layout')

@section('title', 'Verify Your Email')

@section('content')
<div class="max-w-lg mx-auto mt-6 mb-12">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-br from-primary-600 to-primary-800 p-8 text-center">
            <div class="w-20 h-20 mx-auto rounded-2xl bg-white/20 backdrop-blur grid place-items-center text-3xl text-white mb-4">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h2 class="text-2xl font-bold text-white">Verify Your Email</h2>
            <p class="text-primary-100 text-sm mt-1">We sent a 6-digit code to</p>
            <p class="text-white font-semibold text-lg mt-1">{{ $user->email }}</p>
        </div>
        <div class="p-6">
            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl px-4 py-3 mb-4 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-4">
                    @foreach($errors->all() as $error)
                        <p class="flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> {{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('partner.verify-email-otp') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Enter 6-digit code from your email</label>
                    <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" required autofocus
                           class="w-full text-center text-2xl tracking-[0.5em] font-mono font-bold border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none"
                           placeholder="000000">
                </div>
                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl py-3 transition">
                    <i class="fas fa-paper-plane mr-1"></i> Verify Email
                </button>
            </form>
            <div class="mt-4 text-center">
                <form method="POST" action="{{ route('partner.send-email-otp') }}">
                    @csrf
                    <button type="submit" class="text-sm text-primary-600 hover:underline font-medium">
                        <i class="fas fa-redo text-xs mr-1"></i> Resend Code
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
