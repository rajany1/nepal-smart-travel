@extends('admin.layout')
@section('title', 'Report Details')

@section('content')
{{-- Breadcrumb --}}
<div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
    <a href="{{ route('admin.reports', $listParams ?? []) }}" class="hover:text-primary-600 transition">Reports</a>
    <i class="fas fa-chevron-right text-[10px] text-gray-400"></i>
    <span class="text-gray-700">Report #{{ $report->id }}</span>
    @if($report->status === 'pending')
    <span class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Pending</span>
    @endif
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <h3 class="text-lg font-semibold">Report #{{ $report->id }} — {{ $report->title }}</h3>
                <button onclick="navigator.clipboard.writeText('#{{ $report->id }}').then(function(){showToast('ID copied')})" class="text-gray-400 hover:text-gray-600 transition" title="Copy Report ID"><i class="fas fa-copy text-xs"></i></button>
            </div>
            <p class="text-sm text-gray-600">Submitted by: {{ $report->user?->name ?? 'Anonymous' }} — {{ $report->created_at->diffForHumans() }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if($prevReportId)
            <a href="{{ route('admin.reports.view', array_merge(['id' => $prevReportId], $listParams ?? [])) }}" class="report-nav-prev px-3 py-2 bg-gray-100 rounded text-sm hover:bg-gray-200 transition" title="Previous report in current list (←)"><i class="fas fa-arrow-left mr-1"></i>Previous</a>
            @endif
            @if($nextReportId)
            <a href="{{ route('admin.reports.view', array_merge(['id' => $nextReportId], $listParams ?? [])) }}" class="report-nav-next px-3 py-2 bg-gray-100 rounded text-sm hover:bg-gray-200 transition" title="Next report in current list (→)">Next<i class="fas fa-arrow-right ml-1"></i></a>
            @endif
            <a href="{{ route('admin.reports', $listParams ?? []) }}" class="px-3 py-2 bg-gray-100 rounded text-sm hover:bg-gray-200 transition">Back to reports</a>
        </div>
    </div>

    {{-- Quick approve/reject bar for pending reports --}}
    @if($report->status === 'pending')
    <div class="mt-4 flex items-center gap-3 p-3 bg-amber-50 rounded-lg border border-amber-200">
        <span class="text-sm font-medium text-amber-800"><i class="fas fa-hourglass-half mr-1"></i>This report is pending review.</span>
        <div class="flex-1"></div>
        <form method="POST" action="{{ route('admin.reports.approve', $report->id) }}" class="inline" onsubmit="return confirm('Approve this report?\n\nThis will publish an alert and award XP to the reporter.')">
            @csrf
            <button type="submit" class="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-check mr-1"></i>Approve
            </button>
        </form>
        <form method="POST" action="{{ route('admin.reports.reject', $report->id) }}" class="inline" onsubmit="return confirm('Reject this report?')">
            @csrf
            <button type="submit" class="px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-times mr-1"></i>Reject
            </button>
        </form>
    </div>
    @endif

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
                @if($report->moderation_message)
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <h4 class="font-medium text-gray-800">AI Review</h4>
                    <div class="mt-2 rounded-lg p-3 text-sm
                        {{ $report->status === 'approved' ? 'bg-green-50 text-green-800' : '' }}
                        {{ $report->status === 'pending' ? 'bg-amber-50 text-amber-800' : '' }}
                        {{ $report->status === 'rejected' ? 'bg-red-50 text-red-800' : '' }}">
                        @php
                            $analysis = $report->ai_analysis ?? [];
                            $imageCheckVerdict = $analysis['image_check']['verdict'] ?? null;
                            $imageCheckEvaluated = in_array($imageCheckVerdict, ['clean', 'suspicious', 'violation', 'duplicate'], true);
                        @endphp
                        @if($report->authenticity_score !== null)
                            <p class="text-xs mb-1"><strong>Overall stored confidence: {{ round((float) $report->authenticity_score * 100) }}%</strong>
                            <span class="opacity-70">(text + location + image components — not a Vision AI image score)</span>
                            @if(!$imageCheckEvaluated)
                                <span class="opacity-70">— Vision AI image check was unavailable, so this reflects only the evaluated checks</span>
                            @endif</p>
                        @endif
                        <p>{{ $report->moderation_message }}</p>
                        @if(!empty($analysis['summary']))
                            <p class="mt-2 text-xs opacity-80"><strong>Summary:</strong> {{ $analysis['summary'] }}</p>
                        @endif
                        @if(!empty($analysis['location_check']['reason']))
                            <p class="mt-1 text-xs opacity-80"><strong>Location:</strong> {{ $analysis['location_check']['reason'] }}</p>
                        @endif
                        @if(!empty($analysis['image_check']['message']) && ($analysis['image_check']['reviewed'] ?? 0) > 0)
                            <p class="mt-1 text-xs opacity-80"><strong>Images ({{ $analysis['image_check']['reviewed'] }} reviewed):</strong> {{ $analysis['image_check']['message'] }}</p>
                        @endif
                        @if(isset($analysis['image_check']['images']) && count($analysis['image_check']['images']))
                            <ul class="mt-1 space-y-1">
                                @foreach($analysis['image_check']['images'] as $img)
                                    <li class="text-xs opacity-80">
                                        @if(isset($img['screen_probability']) || isset($img['report_match']))
                                            media #{{ $img['media_id'] ?? '?' }} — verdict {{ $img['verdict'] ?? '?' }}
                                            @if(isset($img['screen_probability'])) · screen {{ round((float) $img['screen_probability'] * 100) }}% @endif
                                            @if(isset($img['real_scene_probability'])) · real scene {{ round((float) $img['real_scene_probability'] * 100) }}% @endif
                                            @if(isset($img['report_match'])) · match {{ round((float) $img['report_match'] * 100) }}% @endif
                                        @endif
                                        @if(!empty($img['verdict_reason']) || !empty($img['reason']))
                                            — {{ $img['verdict_reason'] ?? $img['reason'] }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                @endif
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

{{-- Image Integrity: stored evidence indicators for moderators. Pure display of existing
     ai_analysis / GPS / media data — no AI call, no image read, no recomputation.
     Evidence, not conclusions: unknown is never rendered as a negative verdict. --}}
@isset($imageIntegrity)
@php
    $iiPillClass = [
        'ok' => 'bg-green-100 text-green-800',
        'suspicious' => 'bg-amber-100 text-amber-800',
        'unknown' => 'bg-gray-100 text-gray-600',
        'info' => 'bg-blue-100 text-blue-700',
    ];
    $iiBannerClass = [
        'ok' => 'bg-green-50 text-green-800 border-green-200',
        'suspicious' => 'bg-amber-50 text-amber-800 border-amber-200',
        'unknown' => 'bg-gray-50 text-gray-600 border-gray-200',
    ];
    $iiBannerIcon = [
        'ok' => 'fa-check-circle',
        'suspicious' => 'fa-exclamation-triangle',
        'unknown' => 'fa-circle-question',
    ];
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mt-6" id="image-integrity">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 class="text-lg font-semibold">Image Integrity</h3>
            <p class="text-xs text-gray-500 mt-1 max-w-3xl">
                Deterministic screening &amp; AI evidence read from this report's <strong>stored</strong> analysis only —
                opening this page runs no new AI, image, or fingerprint processing.
                Indicators are evidence for human review, <strong>not proof</strong> that an image is fake,
                downloaded, or a screenshot. A moderator makes the final decision.
            </p>
        </div>
        @if(!empty($imageIntegrity['has_analysis']))
        <div class="text-xs text-gray-500 text-right">
            <div><strong>Stored analysis:</strong> {{ $imageIntegrity['analyzed_at'] ?? '—' }}</div>
            @if($imageIntegrity['check_verdict'])
            <div><strong>Image check verdict:</strong> {{ $imageIntegrity['check_verdict'] }}</div>
            @endif
        </div>
        @endif
    </div>

    @php $iiStatus = $imageIntegrity['status']; @endphp
    <div class="mt-3 p-3 rounded-lg border text-sm {{ $iiBannerClass[$iiStatus['tone']] ?? $iiBannerClass['unknown'] }}">
        <i class="fas {{ $iiBannerIcon[$iiStatus['tone']] ?? $iiBannerIcon['unknown'] }} mr-1"></i>{{ $iiStatus['text'] }}
    </div>

    {{-- Report-level indicator table --}}
    <table class="w-full mt-4 text-sm border border-gray-100 rounded">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Indicator</th>
                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase" style="width: 14rem;">Result</th>
                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Meaning</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($imageIntegrity['indicators'] as $row)
            <tr class="align-top">
                <td class="px-4 py-2 font-medium text-gray-700">{{ $row['label'] }}</td>
                <td class="px-4 py-2">
                    <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold {{ $iiPillClass[$row['tone']] ?? $iiPillClass['unknown'] }}">{{ $row['result'] }}</span>
                </td>
                <td class="px-4 py-2 text-xs text-gray-500">{{ $row['meaning'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Per-image breakdown --}}
    @if(count($imageIntegrity['images']))
    <div class="mt-6">
        <h4 class="font-medium text-gray-800">Per-image results</h4>
        <p class="text-xs text-gray-500 mt-1">Stored result per attached image. "Not evaluated" means the check never ran or its result is not stored — it is not a negative verdict.</p>
        @foreach($imageIntegrity['images'] as $img)
        <div class="mt-3 p-4 bg-gray-50 rounded-lg border border-gray-100">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong class="text-sm text-gray-700"><i class="fas fa-image mr-1 text-gray-400"></i>Image media #{{ $img['media_id'] ?? '?' }}</strong>
                @if(count($img['technical']))
                <details class="text-xs text-gray-500">
                    <summary class="cursor-pointer select-none hover:text-primary-600"><i class="fas fa-microscope mr-1"></i>Technical details</summary>
                    <dl class="mt-2 space-y-1">
                        @foreach($img['technical'] as $t)
                        <div class="flex gap-2"><dt class="font-medium shrink-0">{{ $t['label'] }}:</dt><dd class="font-mono break-all">{{ $t['value'] }}</dd></div>
                        @endforeach
                    </dl>
                </details>
                @endif
            </div>
            <table class="w-full mt-2">
                <tbody class="divide-y divide-gray-200">
                    @foreach($img['rows'] as $row)
                    <tr class="align-top">
                        <td class="py-1.5 pr-3 font-medium text-gray-700" style="width: 13rem;">{{ $row['label'] }}</td>
                        <td class="py-1.5 pr-3" style="width: 12rem;">
                            <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold {{ $iiPillClass[$row['tone']] ?? $iiPillClass['unknown'] }}">{{ $row['result'] }}</span>
                        </td>
                        <td class="py-1.5 text-xs text-gray-500">{{ $row['meaning'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Collapsible report-level technical details (safe fields only) --}}
    @if(count($imageIntegrity['technical']))
    <div class="mt-6">
        <details>
            <summary class="cursor-pointer text-sm font-medium text-primary-600 select-none"><i class="fas fa-code mr-1"></i>Technical details (stored data)</summary>
            <dl class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600">
                @foreach($imageIntegrity['technical'] as $t)
                <div class="flex gap-2"><dt class="font-medium shrink-0">{{ $t['label'] }}:</dt><dd class="font-mono break-all">{{ $t['value'] }}</dd></div>
                @endforeach
            </dl>
        </details>
    </div>
    @endif
</div>
@endisset

@if(true)
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function() {
            var el = document.getElementById('map');
            if (!el || el._leaflet_map) return;
            var map = L.map(el).setView([27.7, 85.3], 7);

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
            if (bounds.length > 0) {
                map.fitBounds(bounds, {padding: [50,50]});
            }
            map.invalidateSize();
            el._leaflet_map = map;
        })();
    </script>
@endif

<script>
(function() {
    // Toast notification
    window.showToast = function(msg) {
        var t = document.createElement('div');
        t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;background:#00695C;color:#fff;padding:10px 18px;border-radius:9999px;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,.2);';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function(){ t.remove(); }, 2000);
    };

    // Keyboard shortcuts (only when not typing in inputs)
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT' || e.target.isContentEditable) return;
        if (e.metaKey || e.ctrlKey || e.altKey) return;

        @if($report->status === 'pending')
        if (e.key === 'a' || e.key === 'A') {
            e.preventDefault();
            var approveForm = document.querySelector('form[action*="approve"]');
            if (approveForm && confirm('Approve this report?\n\nThis will publish an alert and award XP to the reporter.')) {
                approveForm.submit();
            }
        }
        if (e.key === 'r' || e.key === 'R') {
            e.preventDefault();
            var rejectForm = document.querySelector('form[action*="reject"]');
            if (rejectForm && confirm('Reject this report?')) {
                rejectForm.submit();
            }
        }
        @endif

        @if($prevReportId)
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            window.location.href = '{{ route("admin.reports.view", array_merge(["id" => $prevReportId], $listParams ?? [])) }}';
        }
        @endif
        @if($nextReportId)
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            window.location.href = '{{ route("admin.reports.view", array_merge(["id" => $nextReportId], $listParams ?? [])) }}';
        }
        @endif

        if (e.key === 'Escape') {
            window.location.href = '{{ route("admin.reports", $listParams ?? []) }}';
        }
    });

    // Show keyboard shortcut hints
    @if($report->status === 'pending' || $prevReportId || $nextReportId)
    var hints = [];
    @if($report->status === 'pending')
    hints.push('A = Approve');
    hints.push('R = Reject');
    @endif
    @if($prevReportId)
    hints.push('← = Previous');
    @endif
    @if($nextReportId)
    hints.push('→ = Next');
    @endif
    hints.push('Esc = Back to list');

    var hintBar = document.createElement('div');
    hintBar.className = 'hidden lg:flex items-center gap-4 mt-4 p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-500';
    hintBar.innerHTML = '<i class="fas fa-keyboard text-slate-400"></i> <span class="font-medium">Shortcuts:</span> ' + hints.map(function(h) { return '<kbd class="px-1.5 py-0.5 bg-white border border-slate-300 rounded text-slate-600 font-mono">' + h + '</kbd>'; }).join(' ');
    document.querySelector('.bg-white.rounded-xl').appendChild(hintBar);
    @endif
})();
</script>

@endsection
