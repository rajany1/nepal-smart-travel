@extends('partner.layout')

@section('title', 'Awaiting Verification')

@section('content')
<div class="max-w-md mx-auto mt-10 sm:mt-16 text-center">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8 sm:p-10">
        <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto rounded-full bg-amber-100 text-amber-500 grid place-items-center text-3xl sm:text-4xl mb-5">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <h2 class="text-xl sm:text-2xl font-bold text-slate-800 mb-2">Verification in Progress</h2>
        <p class="text-slate-500 text-sm leading-relaxed">
            Your business profile
            @if(isset($partner) && $partner)
                <strong class="text-slate-700">{{ $partner->name }}</strong>
            @endif
            has been submitted. Our team will review it shortly.
        </p>
        <p class="text-slate-400 text-xs mt-3">Once verified, you'll be able to create reward offers immediately.</p>
        <div class="mt-8">
            <form method="POST" action="{{ route('partner.logout') }}">
                @csrf
                <button type="submit" class="text-sm text-slate-500 hover:text-slate-700 font-medium transition">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
