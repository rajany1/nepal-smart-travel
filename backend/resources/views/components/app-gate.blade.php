@php
    $unlocked = request()->cookie('nst_app') === '1' || request()->query('app') == '1';
@endphp

<div class="relative">
    <div @class([
        'transition duration-300',
        'pointer-events-none select-none blur-md' => !$unlocked,
    ])>
        {{ $slot }}
    </div>

    @if(!$unlocked)
        <div class="absolute inset-0 z-20 flex items-center justify-center p-4">
            <div class="max-w-sm w-full bg-white rounded-2xl shadow-2xl p-6 text-center border-2 border-primary-500/20">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-primary-600 text-white grid place-items-center text-2xl mb-3">
                    <i class="fas fa-lock"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">Unlock with the App</h3>
                <p class="text-sm text-slate-500 mb-1">{{ $feature ?? 'This feature' }} is available exclusively in the Nepal Smart Travel app.</p>
                <p class="text-xs text-slate-400 mb-4">Scan with your phone camera or install the app.</p>

                <div class="flex items-center justify-center gap-4">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={{ urlencode(env('PLAY_STORE_URL', url('/'))) }}" alt="Scan to install the app" class="w-28 h-28 rounded-xl border border-slate-200">
                    <div class="text-left space-y-2">
                        <a href="{{ env('PLAY_STORE_URL', '#') }}" class="flex items-center gap-2 bg-black text-white rounded-xl px-3 py-2 transition hover:bg-slate-800">
                            <i class="fab fa-google-play text-lg"></i>
                            <span class="text-[11px] leading-tight"><span class="block opacity-70">Get it on</span><span class="font-semibold">Google Play</span></span>
                        </a>
                        <a href="{{ env('APP_STORE_URL', '#') }}" class="flex items-center gap-2 bg-black text-white rounded-xl px-3 py-2 transition hover:bg-slate-800">
                            <i class="fab fa-apple text-lg"></i>
                            <span class="text-[11px] leading-tight"><span class="block opacity-70">Download on the</span><span class="font-semibold">App Store</span></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
