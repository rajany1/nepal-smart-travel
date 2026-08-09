@extends('partner.layout')

@section('title', 'Awaiting Verification')

@section('content')
<div class="max-w-md mx-auto mt-16 text-center">
    <div class="bg-white rounded-2xl shadow-xl p-10">
        <div class="w-20 h-20 mx-auto rounded-full bg-amber-100 text-amber-500 grid place-items-center text-4xl mb-5">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-800 mb-2">Verification in Progress</h2>
        <p class="text-slate-500 text-sm leading-relaxed">
            Your business profile
            @if(isset($partner) && $partner)
                <strong class="text-slate-700">{{ $partner->name }}</strong>
            @endif
            has been submitted. Our team will review it shortly.
        </p>
        <p class="text-slate-400 text-xs mt-3">Once verified, you'll be able to create reward offers immediately.</p>
        <div class="mt-8 flex items-center justify-center gap-4 text-sm">
            <a href="{{ route('partner.logout') }}"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
               class="text-slate-500 hover:text-slate-700">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
        <form id="logout-form" method="POST" action="{{ route('partner.logout') }}" class="hidden">@csrf</form>
    </div>
</div>
@endsection
