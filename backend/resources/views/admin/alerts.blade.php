@extends('admin.layout')
@section('title', 'Alerts Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
            <h3 class="font-semibold text-gray-800">All Alerts</h3>
            <div class="flex gap-2">
                <a href="{{ route('admin.alerts', ['severity' => 'all']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'all' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
                <a href="{{ route('admin.alerts', ['severity' => 'critical']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'critical' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Critical</a>
                <a href="{{ route('admin.alerts', ['severity' => 'high']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'high' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">High</a>
                <a href="{{ route('admin.alerts', ['severity' => 'info']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'info' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Info</a>
            </div>
        </div>
        <div class="overflow-x-auto">
<div id="liveTable">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Severity</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">District</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($alerts as $alert)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900 max-w-[250px] truncate">{{ $alert->title }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $alert->alert_type)) }}</td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-medium px-2 py-1 rounded-full 
                                {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $alert->severity === 'high' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $alert->severity === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $alert->severity === 'low' || $alert->severity === 'info' ? 'bg-blue-100 text-blue-800' : '' }}">
                                {{ ucfirst($alert->severity) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $alert->affected_district ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $alert->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-right">
                            <form method="POST" action="{{ route('admin.alerts.delete', $alert->id) }}" class="inline" onsubmit="return confirm('Delete this alert?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-bell-slash text-3xl text-gray-300 mb-3 block"></i>
                            No alerts found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($alerts->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $alerts->links() }}</div>
</div>
        @endif
    </div>

    <!-- Create Alert Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Create New Alert</h3>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('admin.alerts.create') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alert Type</label>
                        <select name="alert_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="weather">Weather</option>
                            <option value="landslide">Landslide</option>
                            <option value="earthquake">Earthquake</option>
                            <option value="strike">Strike/Bandh</option>
                            <option value="emergency">Emergency</option>
                            <option value="traffic">Traffic</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                        <select name="severity" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="info">Info</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Affected District</label>
                        <input type="text" name="affected_district" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. Kathmandu or All">
                    </div>
                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_broadcast" value="1" id="broadcastToggle" onchange="toggleBroadcast(this.checked)" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Broadcast to ALL users (platform-wide, no location needed)</span>
                        </label>
                    </div>
                    <div id="locationBox">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location (click on the map)</label>
                        <div id="alertMap" class="w-full h-56 rounded-lg border border-gray-300 z-0" style="cursor:crosshair;"></div>
                        <input type="hidden" name="latitude" id="alertLat">
                        <input type="hidden" name="longitude" id="alertLng">
                        <p id="locReadout" class="mt-1 text-xs text-gray-500"></p>
                    </div>
                    <button type="submit" class="w-full bg-primary-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-primary-700 transition">
                        <i class="fas fa-plus mr-1"></i> Create Alert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    var map = null;
    var marker = null;

    function initMap() {
        if (map || typeof L === 'undefined') return;
        // Nepal bounds default view
        map = L.map('alertMap').setView([28.3949, 84.1240], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        map.on('click', function (e) {
            var lat = e.latlng.lat.toFixed(6);
            var lng = e.latlng.lng.toFixed(6);
            document.getElementById('alertLat').value = lat;
            document.getElementById('alertLng').value = lng;
            if (marker) { marker.setLatLng(e.latlng); } else {
                marker = L.marker(e.latlng).addTo(map);
            }
            document.getElementById('locReadout').textContent =
                'Selected: ' + lat + ', ' + lng;
        });
    }

    // init on load + after SPA content swap
    function tryInit() {
        if (!document.getElementById('alertMap')) return;
        if (map) return;
        if (typeof L === 'undefined') { setTimeout(tryInit, 200); return; }
        initMap();
    }
    tryInit();
})();
</script>

<script>
function toggleBroadcast(broadcast) {
    var box = document.getElementById('locationBox');
    if (box) box.style.display = broadcast ? 'none' : '';
    // When broadcasting, clear any picked location (not needed).
    if (broadcast) {
        var lat = document.getElementById('alertLat');
        var lng = document.getElementById('alertLng');
        if (lat) lat.value = '';
        if (lng) lng.value = '';
        var ro = document.getElementById('locReadout');
        if (ro) ro.textContent = '';
    }
}
</script>
@endsection
