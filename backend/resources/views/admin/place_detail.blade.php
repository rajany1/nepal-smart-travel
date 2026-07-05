@extends('admin.layout')

@section('title', $place->name)

@section('content')
<style>
.gallery-modal {
    position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.92);
    display: flex; align-items: center; justify-content: center;
    opacity: 0; pointer-events: none; transition: opacity 0.3s;
}
.gallery-modal.open { opacity: 1; pointer-events: auto; }
.gallery-modal img { max-width: 90vw; max-height: 85vh; border-radius: 8px; }
.rating-bar { height: 8px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
.rating-bar-fill { height: 100%; border-radius: 999px; transition: width 0.4s ease; }
</style>

@php
    $images = $place->images;
    $totalReviews = $place->total_reviews ?: $place->reviews->count();
@endphp

<!-- Image Gallery Modal -->
<div id="galleryModal" class="gallery-modal" onclick="closeGallery()">
    <button onclick="closeGallery()" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 z-10"><i class="fas fa-times"></i></button>
    <button onclick="prevImage()" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-3xl hover:text-gray-300 z-10"><i class="fas fa-chevron-left"></i></button>
    <img id="galleryImg" src="" alt="">
    <button onclick="nextImage()" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-3xl hover:text-gray-300 z-10"><i class="fas fa-chevron-right"></i></button>
    <div id="galleryCounter" class="absolute bottom-6 text-white text-sm bg-black/50 px-3 py-1 rounded-full"></div>
</div>

<div class="max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('admin.places') }}" class="hover:text-indigo-600">Places</a>
        <span>/</span>
        <span class="text-gray-900 font-medium truncate max-w-[300px]">{{ $place->name }}</span>
        <a href="{{ route('admin.live-map') }}" class="ml-auto text-indigo-600 hover:text-indigo-800"><i class="fas fa-globe-asia mr-1"></i> Live Map</a>
    </div>

    <!-- Hero Image -->
    <div class="relative rounded-xl overflow-hidden mb-6 bg-gray-100" style="height:340px;">
        @if($images->count() > 0)
        <img src="{{ asset('storage/' . $images->first()->image_url) }}" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<div class=\"w-full h-full flex items-center justify-center text-gray-300 text-6xl\"><i class=\"fas fa-image\"></i></div>'">
        @else
        <div class="w-full h-full flex items-center justify-center text-gray-300 text-6xl"><i class="fas fa-image"></i></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent pointer-events-none"></div>
        <div class="absolute bottom-4 left-5 right-5 flex items-end justify-between">
            <div>
                <h1 class="text-3xl font-bold text-white drop-shadow-lg">{{ $place->name }}</h1>
                <div class="flex items-center gap-3 mt-2">
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-white/90 text-indigo-700">
                        <i class="fas fa-{{ $place->category?->icon ?? 'tag' }}"></i> {{ $place->category?->name ?? 'Uncategorized' }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-white/90 text-sm">
                        <i class="fas fa-star text-yellow-400"></i> {{ number_format($place->average_rating ?? 0, 1) }}
                    </span>
                    <span class="text-white/70 text-sm">{{ $totalReviews }} reviews</span>
                </div>
            </div>
            @if($images->count() > 1)
            <div class="flex gap-1">
                @foreach($images->skip(1)->take(4) as $img)
                <div class="w-14 h-14 rounded-lg overflow-hidden border-2 border-white/60 cursor-pointer hover:border-white transition" onclick="openGallery({{ $loop->index + 1 }})">
                    <img src="{{ asset('storage/' . $img->image_url) }}" class="w-full h-full object-cover" onerror="this.style.display='none'">
                </div>
                @endforeach
                @if($images->count() > 5)
                <div class="w-14 h-14 rounded-lg overflow-hidden border-2 border-white/60 bg-black/40 flex items-center justify-center text-white text-xs font-bold cursor-pointer hover:bg-black/60" onclick="openGallery(0)">
                    +{{ $images->count() - 5 }}
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    <!-- Two-column layout -->
    <div class="flex gap-6 items-start">
        <!-- Left Column (main content) -->
        <div class="flex-1 min-w-0 space-y-6">

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-wrap gap-3">
                @if($place->phone)
                <a href="tel:{{ $place->phone }}" class="flex items-center gap-2 px-4 py-2 bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition text-sm font-medium">
                    <i class="fas fa-phone"></i> Call
                </a>
                @endif
                @if($place->latitude && $place->longitude)
                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $place->latitude }},{{ $place->longitude }}" target="_blank" class="flex items-center gap-2 px-4 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                    <i class="fas fa-directions"></i> Directions
                </a>
                @endif
                @if($place->website)
                <a href="{{ $place->website }}" target="_blank" class="flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg hover:bg-indigo-100 transition text-sm font-medium">
                    <i class="fas fa-globe"></i> Website
                </a>
                @endif
                @if($place->email)
                <a href="mailto:{{ $place->email }}" class="flex items-center gap-2 px-4 py-2 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 transition text-sm font-medium">
                    <i class="fas fa-envelope"></i> Email
                </a>
                @endif
                <a href="{{ route('admin.places', ['edit_id' => $place->id]) }}" class="flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                    <i class="fas fa-edit"></i> Edit
                </a>
            </div>

            <!-- About -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-900">About</h3>
                    @if($place->is_verified)
                    <span class="text-xs bg-green-100 text-green-700 px-2.5 py-1 rounded-full font-medium"><i class="fas fa-check-circle mr-1"></i> Verified</span>
                    @endif
                </div>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $place->description ?? 'No description available.' }}</p>
                <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-100">
                    <div>
                        <span class="text-xs text-gray-400 uppercase font-medium">Address</span>
                        <p class="text-sm text-gray-800 mt-0.5">{{ $place->address ?? $place->district ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-400 uppercase font-medium">District</span>
                        <p class="text-sm text-gray-800 mt-0.5">{{ $place->district ?? 'N/A' }}</p>
                    </div>
                    @if($place->latitude && $place->longitude)
                    <div>
                        <span class="text-xs text-gray-400 uppercase font-medium">Coordinates</span>
                        <p class="text-xs font-mono text-gray-800 mt-0.5">{{ $place->latitude }}, {{ $place->longitude }}</p>
                    </div>
                    @endif
                    <div>
                        <span class="text-xs text-gray-400 uppercase font-medium">Source</span>
                        <p class="text-sm text-gray-800 mt-0.5">{{ $place->source ?? 'Manual' }}</p>
                    </div>
                </div>
            </div>

            <!-- Rating Distribution -->
            @if($totalReviews > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Rating Summary</h3>
                <div class="flex items-start gap-8">
                    <div class="text-center flex-shrink-0">
                        <p class="text-5xl font-bold text-gray-900">{{ number_format($place->average_rating ?? 0, 1) }}</p>
                        <div class="flex gap-0.5 justify-center mt-1">
                            @for($i = 1; $i <= 5; $i++)
                            <i class="fas fa-star text-sm {{ $i <= round($place->average_rating ?? 0) ? 'text-yellow-400' : 'text-gray-200' }}"></i>
                            @endfor
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ $totalReviews }} reviews</p>
                    </div>
                    <div class="flex-1 space-y-1.5">
                        @foreach([5,4,3,2,1] as $star)
                        @php $count = $ratingDist[$star] ?? 0; $pct = $totalReviews > 0 ? ($count / $totalReviews * 100) : 0; @endphp
                        <div class="flex items-center gap-2 text-sm">
                            <span class="w-8 text-right text-gray-500 text-xs">{{ $star }}<i class="fas fa-star ml-0.5 text-yellow-400" style="font-size:9px"></i></span>
                            <div class="rating-bar flex-1"><div class="rating-bar-fill bg-yellow-400" style="width:{{ $pct }}%"></div></div>
                            <span class="w-10 text-right text-xs text-gray-400">{{ $count }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Reviews -->
            @if($place->reviews->count() > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Reviews</h3>
                    <span class="text-sm text-gray-400">{{ $place->reviews->count() }} total</span>
                </div>
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
                    @foreach($place->reviews as $review)
                    <div class="border border-gray-100 rounded-lg p-4 hover:bg-gray-50 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 flex items-center justify-center text-sm font-bold text-white shadow-sm flex-shrink-0">
                                    {{ strtoupper(substr($review->user?->name ?? 'U', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $review->user?->name ?? 'Unknown' }}</p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <div class="flex gap-0.5">
                                            @for($i = 0; $i < 5; $i++)
                                            <i class="fas fa-star {{ $i < $review->rating ? 'text-yellow-400' : 'text-gray-200' }}" style="font-size:10px"></i>
                                            @endfor
                                        </div>
                                        <span class="text-xs text-gray-400">{{ $review->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($review->title)
                        <p class="text-sm font-medium text-gray-800 mt-3 mb-1">{{ $review->title }}</p>
                        @endif
                        <p class="text-sm text-gray-600 leading-relaxed">{{ $review->description }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Right Column (sidebar) -->
        <div class="w-80 flex-shrink-0 space-y-6 sticky top-6">
            <!-- Mini Map -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div id="detailMap" style="height:200px;"></div>
                <div class="p-3 border-t border-gray-100 flex items-center justify-between text-sm">
                    <span class="text-gray-500"><i class="fas fa-map-pin mr-1 text-indigo-500"></i> {{ number_format($place->latitude, 4) }}, {{ number_format($place->longitude, 4) }}</span>
                    <div class="flex gap-2">
                        <a href="https://www.google.com/maps?q={{ $place->latitude }},{{ $place->longitude }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs" title="Open in Google Maps"><i class="fas fa-external-link-alt"></i></a>
                        <a href="https://www.openstreetmap.org/?mlat={{ $place->latitude }}&mlon={{ $place->longitude }}" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs" title="Open in OSM"><i class="fas fa-map"></i></a>
                    </div>
                </div>
            </div>

            <!-- Status Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Status</h4>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Active</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $place->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $place->is_active ? 'Yes' : 'No' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Verified</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $place->is_verified ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $place->is_verified ? 'Yes' : 'No' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Featured</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $place->is_featured ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500' }}">{{ $place->is_featured ? 'Yes' : 'No' }}</span>
                    </div>
                    @if($place->featured_until)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Featured until</span>
                        <span class="text-xs text-gray-600">{{ $place->featured_until->format('M d, Y') }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">OSM ID</span>
                        <span class="text-xs font-mono text-gray-600">{{ $place->osm_id ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Created</span>
                        <span class="text-xs text-gray-600">{{ $place->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Updated</span>
                        <span class="text-xs text-gray-600">{{ $place->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Contact -->
            @if($place->phone || $place->email || $place->website)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Contact</h4>
                <div class="space-y-2 text-sm">
                    @if($place->phone)
                    <div class="flex items-center gap-2"><i class="fas fa-phone text-gray-400 w-4"></i> <a href="tel:{{ $place->phone }}" class="text-indigo-600 hover:underline">{{ $place->phone }}</a></div>
                    @endif
                    @if($place->email)
                    <div class="flex items-center gap-2"><i class="fas fa-envelope text-gray-400 w-4"></i> <a href="mailto:{{ $place->email }}" class="text-indigo-600 hover:underline truncate">{{ $place->email }}</a></div>
                    @endif
                    @if($place->website)
                    <div class="flex items-center gap-2"><i class="fas fa-globe text-gray-400 w-4"></i> <a href="{{ $place->website }}" target="_blank" class="text-indigo-600 hover:underline truncate">{{ parse_url($place->website, PHP_URL_HOST) ?: $place->website }}</a></div>
                    @endif
                </div>
            </div>
            @endif

            <!-- All Images -->
            @if($images->count() > 1)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h4 class="text-sm font-semibold text-gray-900 mb-3">Photos ({{ $images->count() }})</h4>
                <div class="grid grid-cols-3 gap-2">
                    @foreach($images as $i => $img)
                    <div class="aspect-square rounded-lg overflow-hidden cursor-pointer border border-gray-200 hover:border-indigo-400 transition" onclick="openGallery({{ $i }})">
                        <img src="{{ asset('storage/' . $img->image_url) }}" class="w-full h-full object-cover" onerror="this.style.display='none'">
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const images = {!! $images->map(function($img) { return asset('storage/' . $img->image_url); })->toJson() !!};
let currentIdx = 0;

function openGallery(idx) {
    currentIdx = idx;
    document.getElementById('galleryImg').src = images[currentIdx];
    updateCounter();
    document.getElementById('galleryModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeGallery() {
    document.getElementById('galleryModal').classList.remove('open');
    document.body.style.overflow = '';
}

function nextImage() { if (currentIdx < images.length - 1) { currentIdx++; updateGallery(); } }
function prevImage() { if (currentIdx > 0) { currentIdx--; updateGallery(); } }
function updateGallery() { document.getElementById('galleryImg').src = images[currentIdx]; updateCounter(); }
function updateCounter() { document.getElementById('galleryCounter').textContent = (currentIdx + 1) + ' / ' + images.length; }

document.addEventListener('keydown', function(e) {
    if (!document.getElementById('galleryModal').classList.contains('open')) return;
    if (e.key === 'Escape') closeGallery();
    if (e.key === 'ArrowRight') nextImage();
    if (e.key === 'ArrowLeft') prevImage();
});

document.addEventListener('DOMContentLoaded', function () {
    @if($place->latitude && $place->longitude)
    const map = L.map('detailMap', { zoomControl: false, attributionControl: false }).setView([{{ $place->latitude }}, {{ $place->longitude }}], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    L.marker([{{ $place->latitude }}, {{ $place->longitude }}]).addTo(map);
    @endif
});
</script>
@endsection
