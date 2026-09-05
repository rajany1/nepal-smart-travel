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
            <p class="text-amber-100 text-sm mt-1">Scan QR code or enter code manually</p>
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

            {{-- Camera Scan Area --}}
            <div class="mb-5">
                <div class="relative bg-slate-900 rounded-2xl overflow-hidden" style="min-height: 320px;">
                    {{-- Live video feed --}}
                    <video id="scanner-video" autoplay playsinline muted class="w-full rounded-2xl" style="display:none;"></video>

                    {{-- Captured photo preview --}}
                    <img id="captured-photo" class="w-full rounded-2xl object-cover" style="display:none; height:320px;">

                    {{-- Scan frame overlay --}}
                    <div id="scan-overlay" class="hidden absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="relative">
                            <div class="w-60 h-60">
                                <div class="absolute top-0 left-0 w-10 h-10 border-t-4 border-l-4 border-emerald-400 rounded-tl-xl"></div>
                                <div class="absolute top-0 right-0 w-10 h-10 border-t-4 border-r-4 border-emerald-400 rounded-tr-xl"></div>
                                <div class="absolute bottom-0 left-0 w-10 h-10 border-b-4 border-l-4 border-emerald-400 rounded-bl-xl"></div>
                                <div class="absolute bottom-0 right-0 w-10 h-10 border-b-4 border-r-4 border-emerald-400 rounded-br-xl"></div>
                                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-emerald-400 to-transparent animate-scan"></div>
                            </div>
                        </div>
                        <p class="absolute bottom-6 text-white text-sm font-semibold bg-black/60 px-4 py-1.5 rounded-full">Point camera at QR code</p>
                    </div>

                    {{-- Idle state --}}
                    <div id="camera-idle" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 py-10">
                        <div class="w-24 h-24 rounded-full bg-slate-800 grid place-items-center mb-4">
                            <i class="fas fa-camera text-4xl text-slate-500"></i>
                        </div>
                        <p class="text-slate-400 font-medium">Tap "Open Camera" to scan</p>
                        <p class="text-slate-500 text-sm mt-1">or take a photo of the QR code</p>
                    </div>

                    {{-- Loading state --}}
                    <div id="camera-loading" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-black/60 text-white">
                        <i class="fas fa-spinner fa-spin text-4xl mb-3"></i>
                        <p class="font-medium">Starting camera...</p>
                    </div>

                    {{-- Processing state --}}
                    <div id="processing-overlay" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-black/60 text-white">
                        <i class="fas fa-cog fa-spin text-4xl mb-3"></i>
                        <p class="font-medium">Reading QR code...</p>
                    </div>

                    {{-- Scan result badge --}}
                    <div id="scan-success" class="hidden absolute top-4 left-0 right-0 flex justify-center">
                        <div class="bg-emerald-500 text-white px-4 py-2 rounded-full font-bold text-sm flex items-center gap-2 shadow-lg">
                            <i class="fas fa-check-circle"></i> <span id="scan-result-text">Code detected!</span>
                        </div>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="flex gap-3 mt-4">
                    <button onclick="openLiveScanner()" id="btn-live" class="flex-1 bg-slate-800 hover:bg-slate-900 text-white font-semibold rounded-xl py-3.5 transition flex items-center justify-center gap-2">
                        <i class="fas fa-video"></i> Live Scan
                    </button>
                    <label for="photo-input" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl py-3.5 transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fas fa-camera"></i> Take Photo
                    </label>
                    <input type="file" id="photo-input" accept="image/*" capture="environment" class="hidden" onchange="handlePhoto(event)">
                </div>
            </div>

            {{-- Divider --}}
            <div class="relative my-5">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
                <div class="relative flex justify-center text-sm"><span class="bg-white px-4 text-slate-400 font-medium">or enter manually</span></div>
            </div>

            {{-- Manual Entry --}}
            <form method="POST" action="{{ route('partner.payments.verify') }}" id="redeem-form">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Redeem Code</label>
                    <input type="text" name="redeem_code" id="redeem-code-input" required
                           class="w-full text-center text-2xl tracking-widest font-mono font-bold border-2 border-slate-200 rounded-2xl px-4 py-4 focus:border-emerald-500 focus:ring-0 outline-none uppercase transition"
                           placeholder="RWD-XXXXXX" maxlength="20">
                </div>
                <button type="submit" class="w-full mt-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-2xl py-4 text-lg transition shadow-lg active:scale-[0.98]">
                    <i class="fas fa-check-circle mr-2"></i> Redeem
                </button>
            </form>
        </div>
    </div>
</div>

<style>
@keyframes scan {
    0%, 100% { top: 0; opacity: 0; }
    10% { opacity: 1; }
    90% { opacity: 1; }
    50% { top: calc(100% - 4px); }
}
.animate-scan {
    animation: scan 2.5s ease-in-out infinite;
}
</style>

<script>
var liveStream = null;
var scanAnimFrame = null;
var barcodeDetector = null;

if ('BarcodeDetector' in window) {
    BarcodeDetector.getSupportedFormats().then(function(formats) {
        barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
    }).catch(function() {
        barcodeDetector = null;
    });
}

function openLiveScanner() {
    var idle = document.getElementById('camera-idle');
    var loading = document.getElementById('camera-loading');
    var btn = document.getElementById('btn-live');

    idle.style.display = 'none';
    loading.classList.remove('hidden');
    loading.style.display = 'flex';
    btn.disabled = true;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        loading.style.display = 'none';
        idle.style.display = 'flex';
        btn.disabled = false;
        alert('Camera not supported. Use "Take Photo" instead.');
        return;
    }

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
    }).then(function(stream) {
        liveStream = stream;
        var video = document.getElementById('scanner-video');
        video.srcObject = stream;
        video.style.display = 'block';
        video.play();

        loading.style.display = 'none';
        document.getElementById('scan-overlay').classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-stop"></i> Stop';
        btn.disabled = false;
        btn.onclick = stopLiveScanner;

        if (barcodeDetector) {
            liveScanLoop();
        } else {
            alert('QR scanning API not available on this browser. Use "Take Photo" or enter code manually.');
            stopLiveScanner();
        }
    }).catch(function(err) {
        console.error('Camera error:', err);
        loading.style.display = 'none';
        idle.style.display = 'flex';
        btn.disabled = false;
        alert('Camera access denied. Use "Take Photo" or enter code manually.');
    });
}

function liveScanLoop() {
    if (!liveStream) return;
    var video = document.getElementById('scanner-video');
    if (video.readyState < 2) {
        scanAnimFrame = requestAnimationFrame(liveScanLoop);
        return;
    }
    barcodeDetector.detect(video).then(function(barcodes) {
        if (barcodes && barcodes.length > 0) {
            onCodeDetected(barcodes[0].rawValue, 'live');
            return;
        }
        scanAnimFrame = requestAnimationFrame(liveScanLoop);
    }).catch(function() {
        scanAnimFrame = requestAnimationFrame(liveScanLoop);
    });
}

function stopLiveScanner() {
    if (scanAnimFrame) { cancelAnimationFrame(scanAnimFrame); scanAnimFrame = null; }
    if (liveStream) { liveStream.getTracks().forEach(function(t) { t.stop(); }); liveStream = null; }
    var video = document.getElementById('scanner-video');
    video.srcObject = null;
    video.style.display = 'none';
    document.getElementById('scan-overlay').classList.add('hidden');
    document.getElementById('camera-idle').style.display = 'flex';
    var btn = document.getElementById('btn-live');
    btn.innerHTML = '<i class="fas fa-video"></i> Live Scan';
    btn.onclick = openLiveScanner;
}

function handlePhoto(event) {
    var file = event.target.files[0];
    if (!file) return;

    var overlay = document.getElementById('processing-overlay');
    overlay.classList.remove('hidden');
    overlay.style.display = 'flex';

    var img = document.getElementById('captured-photo');
    var reader = new FileReader();
    reader.onload = function(e) {
        img.onload = function() {
            img.style.display = 'block';
            document.getElementById('camera-idle').style.display = 'none';
            document.getElementById('scanner-video').style.display = 'none';

            if (barcodeDetector) {
                barcodeDetector.detect(img).then(function(barcodes) {
                    overlay.style.display = 'none';
                    if (barcodes && barcodes.length > 0) {
                        onCodeDetected(barcodes[0].rawValue, 'photo');
                    } else {
                        alert('No QR code found in the photo. Try again or enter code manually.');
                    }
                }).catch(function(err) {
                    overlay.style.display = 'none';
                    alert('Could not read QR code. Try again or enter code manually.');
                });
            } else {
                overlay.style.display = 'none';
                alert('QR scanning not supported in this browser. Use Chrome on Android, or enter code manually.');
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    event.target.value = '';
}

function onCodeDetected(text, source) {
    var code = text;
    try {
        var data = JSON.parse(text);
        code = data.code || data.redeem_code || data;
    } catch(e) {}

    if (source === 'live') stopLiveScanner();

    document.getElementById('scan-success').classList.remove('hidden');
    document.getElementById('scan-result-text').textContent = 'Detected: ' + code;
    document.getElementById('redeem-code-input').value = code;

    setTimeout(function() {
        document.getElementById('redeem-form').submit();
    }, 800);
}

document.getElementById('redeem-code-input').addEventListener('input', function(e) {
    e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9\-]/g, '');
});
</script>
@endsection
