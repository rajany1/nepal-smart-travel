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

            {{-- Live Camera Scan --}}
            <div class="mb-6">
                <div class="relative bg-black rounded-xl overflow-hidden" style="height: 300px;">
                    <video id="live-video" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <canvas id="scan-canvas" class="hidden"></canvas>
                    <div id="scan-frame" class="hidden absolute inset-0 flex items-center justify-center pointer-events-none">
                        <div class="w-56 h-56 border-2 border-emerald-400 rounded-2xl relative">
                            <div class="absolute top-0 left-0 w-8 h-8 border-t-4 border-l-4 border-emerald-400 rounded-tl-lg"></div>
                            <div class="absolute top-0 right-0 w-8 h-8 border-t-4 border-r-4 border-emerald-400 rounded-tr-lg"></div>
                            <div class="absolute bottom-0 left-0 w-8 h-8 border-b-4 border-l-4 border-emerald-400 rounded-bl-lg"></div>
                            <div class="absolute bottom-0 right-0 w-8 h-8 border-b-4 border-r-4 border-emerald-400 rounded-br-lg"></div>
                            <div class="absolute top-0 left-0 w-full h-0.5 bg-emerald-400 animate-scan"></div>
                        </div>
                        <p class="absolute bottom-4 text-white text-sm font-medium bg-black/50 px-3 py-1 rounded-full">Point camera at QR code</p>
                    </div>
                    <div id="camera-idle" class="absolute inset-0 flex flex-col items-center justify-center text-slate-400">
                        <i class="fas fa-camera text-4xl mb-3"></i>
                        <p class="text-sm">Tap button to start camera</p>
                    </div>
                    <div id="camera-loading" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-black/50 text-white">
                        <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                        <p class="text-sm">Starting camera...</p>
                    </div>
                </div>
                <div class="flex gap-3 mt-3">
                    <button onclick="startLiveScan()" id="btn-live" class="flex-1 bg-gray-800 hover:bg-gray-900 text-white font-semibold rounded-xl py-3 transition">
                        <i class="fas fa-video mr-1"></i> Live Scan
                    </button>
                    <label for="camera-capture" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl py-3 transition text-center cursor-pointer">
                        <i class="fas fa-camera mr-1"></i> Take Photo
                    </label>
                    <input type="file" id="camera-capture" accept="image/*" capture="environment" class="hidden" onchange="handlePhotoCapture(event)">
                </div>
            </div>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                <div class="relative flex justify-center text-sm"><span class="bg-white px-4 text-gray-500">or enter manually</span></div>
            </div>

            {{-- Manual Entry --}}
            <form method="POST" action="{{ route('partner.payments.verify') }}" id="redeem-form">
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

<script>
let liveStream = null;
let scanRAF = null;
let barcodeDetector = null;

if ('BarcodeDetector' in window) {
    barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
}

function startLiveScan() {
    const btn = document.getElementById('btn-live');
    const idle = document.getElementById('camera-idle');
    const loading = document.getElementById('camera-loading');

    idle.classList.add('hidden');
    loading.classList.remove('hidden');
    btn.disabled = true;

    navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
    }).then(function(stream) {
        liveStream = stream;
        const video = document.getElementById('live-video');
        video.srcObject = stream;
        video.play();

        loading.classList.add('hidden');
        document.getElementById('scan-frame').classList.remove('hidden');
        btn.innerHTML = '<i class="fas fa-stop mr-1"></i> Stop';
        btn.disabled = false;
        btn.onclick = stopLiveScan;

        if (barcodeDetector) {
            scanWithBarcodeDetector(video);
        } else {
            scanWithCanvasFallback(video);
        }
    }).catch(function(err) {
        console.error('Camera error:', err);
        loading.classList.add('hidden');
        idle.classList.remove('hidden');
        btn.disabled = false;
        alert('Camera not available. Use "Take Photo" or enter code manually.');
    });
}

function scanWithBarcodeDetector(video) {
    async function scan() {
        if (!liveStream) return;
        try {
            const barcodes = await barcodeDetector.detect(video);
            if (barcodes.length > 0) {
                handleScanResult(barcodes[0].rawValue);
                return;
            }
        } catch(e) {}
        scanRAF = requestAnimationFrame(scan);
    }
    scan();
}

function scanWithCanvasFallback(video) {
    const canvas = document.getElementById('scan-canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    function scan() {
        if (!liveStream) return;
        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            try {
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const code = scanQRFromImageData(imageData);
                if (code) {
                    handleScanResult(code);
                    return;
                }
            } catch(e) {}
        }
        scanRAF = requestAnimationFrame(scan);
    }
    scan();
}

function scanQRFromImageData(imageData) {
    const data = imageData.data;
    const width = imageData.width;
    const height = imageData.height;

    const gray = new Uint8Array(width * height);
    for (let i = 0; i < gray.length; i++) {
        const offset = i * 4;
        gray[i] = (data[offset] * 77 + data[offset+1] * 150 + data[offset+2] * 29) >> 8;
    }

    const threshold = 128;
    const binary = new Uint8Array(width * height);
    for (let i = 0; i < gray.length; i++) {
        binary[i] = gray[i] < threshold ? 1 : 0;
    }

    const size = Math.min(width, height);
    const blockSize = Math.floor(size / 50);
    if (blockSize < 2) return null;

    const gridW = Math.floor(width / blockSize);
    const gridH = Math.floor(height / blockSize);
    const grid = new Uint8Array(gridW * gridH);

    for (let gy = 0; gy < gridH; gy++) {
        for (let gx = 0; gx < gridW; gx++) {
            let sum = 0;
            const startX = gx * blockSize;
            const startY = gy * blockSize;
            for (let by = 0; by < blockSize; by++) {
                for (let bx = 0; bx < blockSize; bx++) {
                    sum += binary[(startY + by) * width + (startX + bx)];
                }
            }
            grid[gy * gridW + gx] = sum > (blockSize * blockSize / 2) ? 1 : 0;
        }
    }

    return null;
}

function stopLiveScan() {
    if (scanRAF) {
        cancelAnimationFrame(scanRAF);
        scanRAF = null;
    }
    if (liveStream) {
        liveStream.getTracks().forEach(function(t) { t.stop(); });
        liveStream = null;
    }
    const video = document.getElementById('live-video');
    video.srcObject = null;

    document.getElementById('scan-frame').classList.add('hidden');
    document.getElementById('camera-idle').classList.remove('hidden');

    const btn = document.getElementById('btn-live');
    btn.innerHTML = '<i class="fas fa-video mr-1"></i> Live Scan';
    btn.onclick = startLiveScan;
}

function handlePhotoCapture(event) {
    const file = event.target.files[0];
    if (!file) return;

    const img = new Image();
    const reader = new FileReader();

    reader.onload = function(e) {
        img.onload = function() {
            const canvas = document.getElementById('scan-canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = img.width;
            canvas.height = img.height;
            ctx.drawImage(img, 0, 0);

            if (barcodeDetector) {
                barcodeDetector.detect(canvas).then(function(barcodes) {
                    if (barcodes.length > 0) {
                        handleScanResult(barcodes[0].rawValue);
                    } else {
                        alert('No QR code found in the photo. Try again or enter code manually.');
                    }
                }).catch(function() {
                    alert('Could not scan the photo. Enter code manually.');
                });
            } else {
                alert('QR scanning not supported in this browser. Enter code manually.');
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
    event.target.value = '';
}

function handleScanResult(text) {
    stopLiveScan();
    var code = text;
    try {
        var data = JSON.parse(text);
        code = data.code || data.redeem_code || text;
    } catch(e) {}
    document.getElementById('redeem-code-input').value = code;
    document.getElementById('redeem-form').submit();
}
</script>
@endsection
