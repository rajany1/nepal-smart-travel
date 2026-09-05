@extends('partner.layout')

@section('title', 'Scan / Redeem Payment')

@section('content')
<div class="max-w-lg mx-auto mt-6">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-br from-accent-500 to-accent-600 p-8 text-center text-white">
            <div class="w-20 h-20 mx-auto rounded-2xl bg-white/20 backdrop-blur grid place-items-center text-3xl mb-4">
                <i class="fas fa-qrcode"></i>
            </div>
            <h2 class="text-2xl font-bold">Scan / Redeem Payment</h2>
            <p class="text-amber-100 text-sm mt-1">Scan QR code or enter the redeem code</p>
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

            {{-- Camera Scan --}}
            <div id="camera-section" class="mb-6">
                <div class="bg-gray-100 rounded-xl overflow-hidden" style="height: 300px;">
                    <video id="camera-preview" autoplay playsinline class="w-full h-full object-cover"></video>
                </div>
                <button onclick="startCamera()" id="btn-camera" class="mt-3 w-full bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded-xl py-3 transition">
                    <i class="fas fa-camera mr-1"></i> Open Camera
                </button>
            </div>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center text-sm"><span class="bg-white px-4 text-gray-500">or enter manually</span></div>
            </div>

            {{-- Manual Entry --}}
            <form method="POST" action="{{ route('partner.payments.verify') }}">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Redeem Code</label>
                    <input type="text" name="redeem_code" id="redeem-code-input" required
                           class="w-full text-center text-xl tracking-widest font-mono font-bold border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 outline-none uppercase"
                           placeholder="PAY-XXXXXX" maxlength="20">
                </div>
                <button type="submit" class="w-full mt-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl py-3 transition">
                    <i class="fas fa-check-circle mr-1"></i> Redeem Payment
                </button>
            </form>
        </div>
    </div>
</div>

{{-- QR Code Scanner JS --}}
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode = null;

function startCamera() {
    const btn = document.getElementById('btn-camera');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Starting camera...';

    html5QrCode = new Html5Qrcode('camera-preview');
    html5QrCode.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        onScanSuccess,
        () => {}
    ).then(() => {
        btn.innerHTML = '<i class="fas fa-stop mr-1"></i> Stop Camera';
        btn.onclick = stopCamera;
    }).catch(err => {
        btn.innerHTML = '<i class="fas fa-camera mr-1"></i> Open Camera';
        alert('Camera access denied or not available. Use manual entry.');
    });
}

function stopCamera() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            html5QrCode = null;
            document.getElementById('btn-camera').innerHTML = '<i class="fas fa-camera mr-1"></i> Open Camera';
            document.getElementById('btn-camera').onclick = startCamera;
        });
    }
}

function onScanSuccess(decodedText) {
    stopCamera();
    // Extract code from QR data
    try {
        const data = JSON.parse(decodedText);
        if (data.code) {
            document.getElementById('redeem-code-input').value = data.code;
            // Auto-submit
            document.getElementById('redeem-code-input').closest('form').submit();
        }
    } catch(e) {
        // Plain text code
        document.getElementById('redeem-code-input').value = decodedText;
        document.getElementById('redeem-code-input').closest('form').submit();
    }
}
</script>
@endsection
