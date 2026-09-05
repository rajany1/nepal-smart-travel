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
                <div class="relative bg-black rounded-xl overflow-hidden" style="height: 300px;">
                    <video id="camera-preview" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <div id="scan-overlay" class="hidden absolute inset-0 flex items-center justify-center">
                        <div class="w-56 h-56 border-2 border-emerald-400 rounded-2xl relative">
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-emerald-400 rounded-tl-lg"></div>
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-emerald-400 rounded-tr-lg"></div>
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-emerald-400 rounded-bl-lg"></div>
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-emerald-400 rounded-br-lg"></div>
                            <div class="absolute top-0 left-0 w-full h-0.5 bg-emerald-400 animate-scan"></div>
                        </div>
                        <p class="absolute bottom-4 text-white text-sm font-medium bg-black/50 px-3 py-1 rounded-full">Point camera at QR code</p>
                    </div>
                    <div id="camera-placeholder" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                        <i class="fas fa-camera text-4xl mb-3"></i>
                        <p class="text-sm">Tap button to start camera</p>
                    </div>
                </div>
                <button onclick="toggleCamera()" id="btn-camera" class="mt-3 w-full bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded-xl py-3 transition">
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
                           placeholder="OFFER-XXXXXX" maxlength="20">
                </div>
                <button type="submit" class="w-full mt-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl py-3 transition">
                    <i class="fas fa-check-circle mr-1"></i> Redeem
                </button>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes scan {
    0% { top: 0; }
    50% { top: calc(100% - 2px); }
    100% { top: 0; }
}
.animate-scan {
    animation: scan 2s ease-in-out infinite;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.min.js"></script>
<script>
let videoStream = null;
let scanInterval = null;
let isScanning = false;

function toggleCamera() {
    if (isScanning) {
        stopCamera();
    } else {
        startCamera();
    }
}

function startCamera() {
    const btn = document.getElementById('btn-camera');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Starting camera...';

    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        }).then(function(stream) {
            videoStream = stream;
            const video = document.getElementById('camera-preview');
            video.srcObject = stream;
            video.play();

            document.getElementById('camera-placeholder').classList.add('hidden');
            document.getElementById('scan-overlay').classList.remove('hidden');

            btn.innerHTML = '<i class="fas fa-stop mr-1"></i> Stop Camera';
            isScanning = true;

            scanInterval = setInterval(function() {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    scanQRCode(video);
                }
            }, 300);
        }).catch(function(err) {
            console.error('Camera error:', err);
            btn.innerHTML = '<i class="fas fa-camera mr-1"></i> Open Camera';
            alert('Camera access denied. Please allow camera access and try again, or enter the code manually.');
        });
    } else {
        alert('Camera not supported in this browser. Enter the code manually.');
    }
}

function stopCamera() {
    if (scanInterval) {
        clearInterval(scanInterval);
        scanInterval = null;
    }
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    const video = document.getElementById('camera-preview');
    video.srcObject = null;

    document.getElementById('camera-placeholder').classList.remove('hidden');
    document.getElementById('scan-overlay').classList.add('hidden');

    const btn = document.getElementById('btn-camera');
    btn.innerHTML = '<i class="fas fa-camera mr-1"></i> Open Camera';
    isScanning = false;
}

function scanQRCode(video) {
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    try {
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

        if (typeof jsQR !== 'undefined') {
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert'
            });

            if (code && code.data) {
                handleScanResult(code.data);
            }
        }
    } catch(e) {}
}

function handleScanResult(decodedText) {
    stopCamera();
    try {
        const data = JSON.parse(decodedText);
        const code = data.code || data.redeem_code || data;
        document.getElementById('redeem-code-input').value = typeof code === 'string' ? code : decodedText;
    } catch(e) {
        document.getElementById('redeem-code-input').value = decodedText;
    }
    document.querySelector('form').submit();
}
</script>
@endsection
