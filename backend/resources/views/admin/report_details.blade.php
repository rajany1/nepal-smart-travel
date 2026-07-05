@extends('admin.layout')
@section('title', 'Report Details')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-start justify-between">
        <div>
            <h3 class="text-lg font-semibold">Report #{{ $report->id }} — {{ $report->title }}</h3>
            <p class="text-sm text-gray-600">Submitted by: {{ $report->user?->name ?? 'Anonymous' }} — {{ $report->created_at->diffForHumans() }}</p>
        </div>
        <div class="text-right">
            <a href="{{ route('admin.reports') }}" class="px-3 py-2 bg-gray-100 rounded">Back to reports</a>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h4 class="font-medium text-gray-800">Details</h4>
            <dl class="mt-2 text-sm text-gray-700">
                <div class="mt-2"><strong>Category:</strong> {{ $report->category?->name ?? 'N/A' }}</div>
                <div class="mt-2"><strong>Priority:</strong> {{ ucfirst($report->priority) }}</div>
                <div class="mt-2"><strong>Status:</strong> {{ ucfirst($report->status) }}</div>
                <div class="mt-2"><strong>Device Location:</strong>
                    @if($report->latitude && $report->longitude)
                        {{ $report->latitude }}, {{ $report->longitude }}
                    @else
                        N/A
                    @endif
                </div>
                <div class="mt-2"><strong>Photo EXIF Location:</strong>
                    @if($report->photo_gps_lat && $report->photo_gps_lng)
                        {{ $report->photo_gps_lat }}, {{ $report->photo_gps_lng }}
                    @else
                        No GPS data
                    @endif
                </div>
                <div class="mt-2"><strong>GPS Verification:</strong>
                    @php
                        $status = $report->gps_verification_status ?? 'none';
                    @endphp
                    @if($status === 'verified')
                        <span class="text-green-700">Verified ({{ $report->gps_distance_km }} km)</span>
                    @elseif($status === 'mismatched')
                        <span class="text-red-700">Mismatch ({{ $report->gps_distance_km }} km) — No GPS verified</span>
                    @elseif($status === 'no_gps_data')
                        <span class="text-yellow-700">Photo had no GPS EXIF data — No GPS verified</span>
                    @else
                        <span class="text-gray-600">Not verified</span>
                    @endif
                </div>
                <div class="mt-2"><strong>Photo captured at:</strong> {{ $report->photo_captured_at ?? 'N/A' }}</div>
                @isset($queueItem)
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h4 class="font-medium text-gray-800">Moderation Queue</h4>
                    <div class="mt-2 space-y-2">
                        <div><strong>Queue Status:</strong> 
                            <span class="text-xs font-medium px-2 py-1 rounded-full 
                                {{ $queueItem->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $queueItem->status === 'pending' ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $queueItem->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}">
                                {{ ucfirst($queueItem->status) }}
                            </span>
                        </div>
                        <div class="mt-1"><strong>Priority:</strong> {{ ucfirst($queueItem->priority) }}</div>
                        @if($queueItem->ai_spam_score > 0)
                            <div class="mt-1"><strong>AI Spam Score:</strong> {{ $queueItem->ai_spam_score }}</div>
                        @endif
                        @if($queueItem->reviewed_by)
                            <div class="mt-1"><strong>Reviewed by:</strong> {{ $queueItem->reviewer?->name ?? 'Unknown' }} ({{ $queueItem->reviewed_at?->diffForHumans() }})</div>
                        @endif
                        @if($queueItem->rejection_reason)
                            <div class="mt-1"><strong>Rejection Reason:</strong> {{ $queueItem->rejection_reason }}</div>
                        @endif
                    </div>
                </div>
                @endif
            </dl>

            <div class="mt-4">
                <h4 class="font-medium text-gray-800">Image</h4>
                <div class="mt-2">
                        @php use Illuminate\Support\Facades\Storage; @endphp
                        @if($report->media && $report->media->count())
                            @foreach($report->media as $m)
                                @if($m->type === 'image')
                                    @php
                                        // Prefer Storage URL for the configured 'public' disk, fallback to asset
                                        $url = null;
                                        try {
                                            if (Storage::disk('public')->exists($m->media_url)) {
                                                $url = Storage::disk('public')->url($m->media_url);
                                            }
                                        } catch (\Throwable $e) {
                                            $url = null;
                                        }
                                        if (! $url) {
                                            $url = asset('storage/'.$m->media_url);
                                        }
                                    @endphp
                                    <img src="{{ $url }}" class="max-w-full rounded shadow-sm" alt="report image">
                                @endif
                            @endforeach
                        @else
                            <div class="text-gray-500">No media attached</div>
                        @endif
                </div>
            </div>
        </div>

        <div>
            <h4 class="font-medium text-gray-800">Map</h4>
            <div id="map" style="height: 420px;" class="mt-2 rounded"></div>
            <p class="text-xs text-gray-500 mt-2">Blue = Device location (report), Red = Photo EXIF location</p>
        </div>
    </div>
</div>

@if(true)
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var map = L.map('map').setView([27.7, 85.3], 7);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
            }).addTo(map);

            var deviceLat = {{ $report->latitude ?? 'null' }};
            var deviceLng = {{ $report->longitude ?? 'null' }};
            var photoLat = {{ $report->photo_gps_lat ?? 'null' }};
            var photoLng = {{ $report->photo_gps_lng ?? 'null' }};

            var bounds = [];
            if (deviceLat && deviceLng) {
                var d = L.marker([deviceLat, deviceLng], {icon: L.icon({iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png', iconSize: [25,41]})}).addTo(map).bindPopup('Device location');
                bounds.push([deviceLat, deviceLng]);
            }
            if (photoLat && photoLng) {
                var p = L.marker([photoLat, photoLng], {icon: L.icon({iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-red.png', iconSize: [25,41]})}).addTo(map).bindPopup('Photo EXIF location');
                bounds.push([photoLat, photoLng]);
            }

            if (bounds.length) {
                map.fitBounds(bounds, {padding: [40,40]});
            }
        });
    </script>
@endif

@endsection
