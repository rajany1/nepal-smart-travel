@extends('partner.layout')

@section('title', 'Scan / Redeem')

@section('content')
<div class="max-w-lg mx-auto mt-4 sm:mt-6">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-br from-accent-500 to-accent-600 p-6 sm:p-8 text-center text-white">
            <div class="w-16 h-16 sm:w-20 sm:h-20 mx-auto rounded-2xl bg-white/20 backdrop-blur grid place-items-center text-2xl sm:text-3xl mb-4">
                <i class="fas fa-qrcode"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold">Scan / Redeem</h2>
            <p class="text-amber-100 text-sm mt-1">Enter the code shown on user's phone</p>
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

            {{-- Code Input --}}
            <form method="POST" action="{{ route('partner.payments.verify') }}" id="redeem-form">
                @csrf
                <div class="text-center mb-4">
                    <div class="w-20 h-20 mx-auto rounded-full bg-primary-50 grid place-items-center mb-3">
                        <i class="fas fa-ticket-alt text-3xl text-primary-600"></i>
                    </div>
                    <h3 class="font-semibold text-slate-800 text-lg">Enter Redeem Code</h3>
                    <p class="text-sm text-slate-500 mt-1">Ask user to show code, then type it below</p>
                </div>

                <div>
                    <input type="text" name="redeem_code" id="redeem-code-input" required autofocus
                           class="w-full text-center text-2xl tracking-widest font-mono font-bold border-2 border-slate-200 rounded-2xl px-4 py-5 focus:border-primary-500 focus:ring-0 outline-none uppercase transition"
                           placeholder="OFFER-XXXXXX" maxlength="20">
                </div>

                <button type="submit" class="w-full mt-5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl py-4 text-lg transition shadow-lg active:scale-[0.98]">
                    <i class="fas fa-check-circle mr-2"></i> Redeem
                </button>
            </form>

            {{-- Quick tip --}}
            <div class="mt-5 bg-blue-50 rounded-xl p-4 border border-blue-100">
                <div class="flex items-start gap-3">
                    <i class="fas fa-lightbulb text-blue-500 mt-0.5"></i>
                    <div class="text-sm text-blue-700">
                        <p class="font-semibold">How to redeem:</p>
                        <ol class="mt-1 space-y-1 list-decimal list-inside text-blue-600">
                            <li>Ask user to open ORIPORI app</li>
                            <li>Go to My Codes → tap the offer</li>
                            <li>Show the QR code or read the code</li>
                            <li>Type the code above and tap Redeem</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('redeem-code-input').addEventListener('input', function(e) {
    e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
});
</script>
@endsection
