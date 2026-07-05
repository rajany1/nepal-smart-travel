@extends('admin.layout')

@section('title', 'Live Map')

@section('content')
<div class="relative">
    <!-- Toolbar -->
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" id="mapSearch" placeholder="Search places, reports..." oninput="filterMarkers(this.value)" class="pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg w-72 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-500 ml-2">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" checked onchange="toggleLayer('places', this.checked)" class="rounded border-gray-300 text-blue-500">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block"></span> Places
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" checked onchange="toggleLayer('reports', this.checked)" class="rounded border-gray-300 text-orange-500">
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-500 inline-block"></span> Reports
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" checked onchange="toggleLayer('alerts', this.checked)" class="rounded border-gray-300 text-yellow-500">
                    <span class="w-2.5 h-2.5 rounded-full bg-yellow-500 inline-block"></span> Alerts
                </label>
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" onchange="toggleLayer('weather', this.checked)" class="rounded border-gray-300 text-indigo-500">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-400 inline-block"></span> Weather
                </label>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
            <input type="checkbox" id="satelliteToggle" onchange="toggleSatellite()" class="rounded border-gray-300">
            <i class="fas fa-satellite"></i> Satellite
        </label>
    </div>

    <div id="liveMap" style="height: 78vh;" class="rounded-xl border border-slate-200 shadow-sm"></div>

    <!-- Info Panel (replaces browser popup) -->
    <div id="infoPanel" class="hidden absolute bottom-6 left-1/2 -translate-x-1/2 bg-white rounded-xl shadow-2xl border border-gray-200 p-0 w-[420px] max-w-[90vw] overflow-hidden z-[1000] transition-all duration-200" style="box-shadow: 0 8px 32px rgba(0,0,0,0.18);">
        <button onclick="closeInfoPanel()" class="absolute top-2 right-2 z-10 w-7 h-7 bg-black/40 rounded-full text-white text-xs flex items-center justify-center hover:bg-black/60"><i class="fas fa-times"></i></button>
        <div id="infoPanelImg" class="h-36 bg-gray-100 bg-cover bg-center" style="display:none;"></div>
        <div class="p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h4 id="infoPanelTitle" class="font-semibold text-gray-900 text-base"></h4>
                    <p id="infoPanelMeta" class="text-xs text-gray-500 mt-0.5"></p>
                </div>
                <span id="infoPanelBadge" class="text-xs font-medium px-2.5 py-0.5 rounded-full flex-shrink-0"></span>
            </div>
            <p id="infoPanelDesc" class="text-sm text-gray-600 mt-2 line-clamp-2"></p>
            <div id="infoPanelRating" class="flex items-center gap-2 mt-2 text-sm"></div>
            <div class="flex gap-2 mt-3">
                <a id="infoPanelBtn" href="#" class="flex-1 text-center px-3 py-1.5 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">View Details</a>
                <button id="infoPanelDir" onclick="openDirections()" class="px-3 py-1.5 text-sm font-medium bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition"><i class="fas fa-directions"></i></button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<style>
.marker-cluster-small { background-color: rgba(99, 102, 241, 0.2); }
.marker-cluster-small div { background-color: rgba(99, 102, 241, 0.6); color: #fff; font-weight: 600; }
.marker-cluster-medium { background-color: rgba(99, 102, 241, 0.25); }
.marker-cluster-medium div { background-color: rgba(99, 102, 241, 0.7); color: #fff; font-weight: 600; }
.marker-cluster-large { background-color: rgba(99, 102, 241, 0.3); }
.marker-cluster-large div { background-color: rgba(99, 102, 241, 0.8); color: #fff; font-weight: 600; }
.custom-marker { display:flex; align-items:center; justify-content:center; border-radius:50%; border:2px solid #fff; box-shadow:0 2px 6px rgba(0,0,0,0.3); font-size:12px; color:#fff; transition:transform 0.15s; }
.custom-marker:hover { transform:scale(1.15); z-index:1000 !important; }
.leaflet-popup-content-wrapper { border-radius:12px !important; box-shadow:0 4px 20px rgba(0,0,0,0.15) !important; }
.leaflet-popup-content { margin:14px !important; min-width:220px; }
.leaflet-popup-tip { box-shadow:none !important; }
</style>
<script>
const resources = {!! $resources !!};
const nepalBounds = L.latLngBounds([26.0, 79.5], [31.0, 89.0]);
let selectedLat = null, selectedLng = null;

const map = L.map('liveMap', {
    center: [27.7, 85.3], zoom: 7, minZoom: 6,
    maxBounds: nepalBounds, maxBoundsViscosity: 1.0,
    zoomControl: false,
});

L.control.zoom({ position: 'bottomright' }).addTo(map);

const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 });
const overlayLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, opacity: 0.35,
});

function toggleSatellite() {
    const use = document.getElementById('satelliteToggle').checked;
    if (use) {
        map.removeLayer(osmLayer);
        map.addLayer(satelliteLayer);
        map.addLayer(overlayLayer);
    } else {
        map.removeLayer(satelliteLayer);
        map.removeLayer(overlayLayer);
        map.addLayer(osmLayer);
    }
}

const categoryIcons = {
    'restaurant': '\uf0f5', 'hotel': '\uf236', 'attractions': '\uf06b',
    'emergency': '\uf0f9', 'atm': '\uf0e0', 'fuel': '\uf017',
    'hospital': '\uf0fa', 'bank': '\uf19c', 'shopping': '\uf07a',
    'parking': '\uf1f9', 'education': '\uf19d', 'entertainment': '\uf008',
    'default': '\uf279',
};
const faUnicode = function(name) {
    const map = {
        'restaurant': '\uf0f5', 'hotel': '\uf236', 'tour': '\uf06b',
        'attractions': '\uf06b', 'local_hospital': '\uf0fa',
        'local_gas_station': '\uf017', 'account_balance': '\uf19c',
        'directions_bike': '\uf1b9', 'shopping': '\uf07a',
        'education': '\uf19d', 'entertainment': '\uf008', 'parking': '\uf1f9',
        'explore': '\uf279', 'tag': '\uf02b', 'bell': '\uf0f3',
        'flag': '\uf024', 'globe-asia': '\uf0ac',
    };
    return map[name] || '\uf279';
};

function makeMarkerIcon(color, iconName, size) {
    return L.divIcon({
        className: '',
        html: `<div class="custom-marker" style="width:${size}px;height:${size}px;background:${color}"><i class="fas fa-${iconName}" style="font-size:${size*0.45}px"></i></div>`,
        iconSize: [size, size],
        iconAnchor: [size/2, size/2],
    });
}

const placeCluster = L.markerClusterGroup({ chunkedLoading: true, maxClusterRadius: 60 });
const reportCluster = L.markerClusterGroup({ chunkedLoading: true, maxClusterRadius: 60 });
const alertGroup = L.layerGroup();
const weatherGroup = L.layerGroup();
let weatherData = [];

function fetchWeatherGrid() {
    fetch('/api/v1/weather/grid')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            weatherData = res.data || [];
            renderWeatherOverlay();
        })
        .catch(() => {});
}

function weatherCodeToColor(code) {
    if (code === 0) return '#FFD700';
    if (code >= 1 && code <= 3) return '#B0B0B0';
    if (code >= 45 && code <= 48) return '#D3D3D3';
    if (code >= 51 && code <= 55) return '#87CEEB';
    if (code >= 61 && code <= 65) return '#4169E1';
    if (code >= 71 && code <= 77) return '#FFFFFF';
    if (code >= 80 && code <= 82) return '#0000FF';
    if (code >= 95 && code <= 99) return '#800080';
    return 'transparent';
}

function renderWeatherOverlay() {
    weatherGroup.clearLayers();
    const step = 0.05, half = step / 2;
    weatherData.forEach(function(pt) {
        const color = weatherCodeToColor(pt.code);
        if (color === 'transparent') return;
        const rect = L.rectangle(
            [[pt.lat - half, pt.lng - half], [pt.lat + half, pt.lng + half]],
            { color: color, fillColor: color, fillOpacity: 0.35, weight: 0, opacity: 0 }
        );
        weatherGroup.addLayer(rect);
    });
}

fetchWeatherGrid();

const allMarkers = [];

resources.places.forEach(function(p) {
    const ico = p.icon || 'map-marker-alt';
    const color = p.color || '#6366f1';
    const marker = L.marker([p.latitude, p.longitude], {
        icon: makeMarkerIcon(color, ico, 26),
    });
    marker._resource = p;
    marker._type = 'place';
    marker.bindPopup(buildPopup(p));
    marker.on('click', function() { showInfoPanel(p); });
    placeCluster.addLayer(marker);
    allMarkers.push(marker);
});
map.addLayer(placeCluster);

resources.reports.forEach(function(r) {
    const color = r.color || '#f97316';
    const marker = L.marker([r.latitude, r.longitude], {
        icon: makeMarkerIcon(color, 'flag', 22),
    });
    marker._resource = r;
    marker._type = 'report';
    marker.bindPopup(buildPopup(r));
    marker.on('click', function() { showInfoPanel(r); });
    reportCluster.addLayer(marker);
    allMarkers.push(marker);
});
map.addLayer(reportCluster);

resources.alerts.forEach(function(a) {
    const color = a.color || '#eab308';
    const marker = L.marker([a.latitude, a.longitude], {
        icon: makeMarkerIcon(color, 'bell', 22),
    });
    marker._resource = a;
    marker._type = 'alert';
    marker.bindPopup(buildPopup(a));
    marker.on('click', function() { showInfoPanel(a); });
    alertGroup.addLayer(marker);
    allMarkers.push(marker);
});
map.addLayer(alertGroup);

function buildPopup(r) {
    const stars = r.rating ? '<span style="color:#f59e0b">' + '★'.repeat(Math.round(r.rating)) + '</span>' : '';
    return '<div>' +
        '<strong>' + r.name + '</strong>' +
        '<p style="font-size:11px;color:#6b7280;margin:2px 0">' + r.category + (r.status ? ' · ' + r.status : '') + '</p>' +
        (stars ? '<p style="font-size:12px;margin:2px 0">' + stars + ' ' + (r.rating || '').toFixed(1) + '</p>' : '') +
        (r.description ? '<p style="font-size:11px;color:#6b7280;margin:4px 0">' + r.description.substring(0,60) + '</p>' : '') +
        '<a href="' + r.url + '" style="font-size:11px;color:#6366f1">View details →</a>' +
    '</div>';
}

function showInfoPanel(r) {
    selectedLat = r.latitude; selectedLng = r.longitude;
    const panel = document.getElementById('infoPanel');
    document.getElementById('infoPanelTitle').textContent = r.name;
    document.getElementById('infoPanelMeta').textContent = (r.category || '') + (r.district ? ' · ' + r.district : '');
    document.getElementById('infoPanelDesc').textContent = r.description || '';
    document.getElementById('infoPanelDesc').style.display = r.description ? '' : 'none';
    document.getElementById('infoPanelBtn').href = r.url || '#';

    const imgDiv = document.getElementById('infoPanelImg');
    if (r.image) {
        imgDiv.style.backgroundImage = 'url(' + r.image + ')';
        imgDiv.style.display = '';
    } else {
        imgDiv.style.display = 'none';
    }

    const badge = document.getElementById('infoPanelBadge');
    if (r.type === 'place') {
        badge.textContent = r.status === 'verified' ? 'Verified' : 'Place';
        badge.className = 'text-xs font-medium px-2.5 py-0.5 rounded-full flex-shrink-0 ' + (r.status === 'verified' ? 'bg-green-100 text-green-700' : 'bg-indigo-100 text-indigo-700');
    } else if (r.type === 'report') {
        badge.textContent = r.status;
        badge.className = 'text-xs font-medium px-2.5 py-0.5 rounded-full flex-shrink-0 ' + (r.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700');
    } else {
        badge.textContent = (r.status || 'Alert').toUpperCase();
        badge.className = 'text-xs font-medium px-2.5 py-0.5 rounded-full flex-shrink-0 bg-yellow-100 text-yellow-700';
    }

    const ratingDiv = document.getElementById('infoPanelRating');
    if (r.rating !== undefined && r.rating !== null) {
        const full = Math.round(r.rating);
        ratingDiv.innerHTML = '<span style="color:#f59e0b">' + '★'.repeat(full) + '☆'.repeat(5-full) + '</span> <span class="text-gray-700 font-medium">' + r.rating.toFixed(1) + '</span><span class="text-gray-400"> (' + (r.reviews_count || 0) + ')</span>';
        ratingDiv.style.display = '';
    } else {
        ratingDiv.style.display = 'none';
    }

    const dirBtn = document.getElementById('infoPanelDir');
    if (r.latitude && r.longitude) {
        dirBtn.style.display = '';
        dirBtn.onclick = function() { window.open('https://www.google.com/maps/dir/?api=1&destination=' + r.latitude + ',' + r.longitude, '_blank'); };
    } else {
        dirBtn.style.display = 'none';
    }

    panel.classList.remove('hidden');
    map.closePopup();
}

function closeInfoPanel() {
    document.getElementById('infoPanel').classList.add('hidden');
}

function toggleLayer(type, show) {
    if (type === 'places') {
        if (show) map.addLayer(placeCluster); else map.removeLayer(placeCluster);
    } else if (type === 'reports') {
        if (show) map.addLayer(reportCluster); else map.removeLayer(reportCluster);
    } else if (type === 'alerts') {
        if (show) map.addLayer(alertGroup); else map.removeLayer(alertGroup);
    } else if (type === 'weather') {
        if (show) map.addLayer(weatherGroup); else map.removeLayer(weatherGroup);
        if (show && weatherData.length === 0) fetchWeatherGrid();
    }
}

function filterMarkers(query) {
    const q = query.toLowerCase().trim();
    allMarkers.forEach(function(m) {
        const r = m._resource;
        const match = !q || (r.name && r.name.toLowerCase().includes(q)) || (r.category && r.category.toLowerCase().includes(q)) || (r.description && r.description.toLowerCase().includes(q));
        if (match) {
            if (m._type === 'place' && !placeCluster.hasLayer(m)) placeCluster.addLayer(m);
            if (m._type === 'report' && !reportCluster.hasLayer(m)) reportCluster.addLayer(m);
            if (m._type === 'alert' && !alertGroup.hasLayer(m)) alertGroup.addLayer(m);
        } else {
            if (m._type === 'place' && placeCluster.hasLayer(m)) placeCluster.removeLayer(m);
            if (m._type === 'report' && reportCluster.hasLayer(m)) reportCluster.removeLayer(m);
            if (m._type === 'alert' && alertGroup.hasLayer(m)) alertGroup.removeLayer(m);
        }
    });
}

map.on('click', function() { closeInfoPanel(); });
</script>
@endsection
