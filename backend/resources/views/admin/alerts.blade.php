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
                <a href="{{ route('admin.alerts', ['severity' => 'medium']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'medium' ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Medium</a>
                <a href="{{ route('admin.alerts', ['severity' => 'low']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'low' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Low</a>
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
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Languages</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Created By</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($alerts as $alert)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900 max-w-[250px] truncate">{{ $alert->title }}</p>
                            @if($alert->title_ne)
                            <p class="text-xs text-primary-600 mt-0.5 truncate">{{ $alert->title_ne }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $alert->alert_type)) }}</td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-medium px-2 py-1 rounded-full 
                                {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $alert->severity === 'high' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $alert->severity === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $alert->severity === 'low' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $alert->severity === 'info' ? 'bg-blue-100 text-blue-800' : '' }}">
                                {{ ucfirst($alert->severity) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $alert->affected_district ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex gap-1 flex-wrap">
                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-blue-50 text-blue-700 border border-blue-100">EN</span>
                                @if($alert->title_ne || $alert->description_ne)
                                <span class="px-2 py-0.5 text-xs font-medium rounded bg-primary-50 text-primary-700 border border-primary-100">नेपाली</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $alert->creator?->name ?? 'System' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $alert->created_at->format('M d, Y H:i') }}</td>
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
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
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
        @endif
    </div>
</div>

<!-- Create Alert Form - Enhanced with Language Workflow -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-plus-circle text-primary-600"></i>
            Create New Alert
        </h3>
    </div>
    <div class="p-6">
        <form method="POST" action="{{ route('admin.alerts.create') }}" id="alertForm">
            @csrf
            
            <!-- Hidden fields for actual submission -->
            <input type="hidden" name="title" id="submit_title">
            <input type="hidden" name="description" id="submit_description">
            <input type="hidden" name="title_ne" id="submit_title_ne">
            <input type="hidden" name="description_ne" id="submit_description_ne">
            
            <!-- Section 1: Alert Information -->
            <div class="mb-6 pb-6 border-b border-gray-100">
                <h4 class="font-medium text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-bold">1</span>
                    Alert Information
                </h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alert Type <span class="text-red-500">*</span></label>
                        <select name="alert_type" id="alertType" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="weather">Weather</option>
                            <option value="flood">Flood</option>
                            <option value="landslide">Landslide</option>
                            <option value="earthquake">Earthquake</option>
                            <option value="strike">Strike/Bandh</option>
                            <option value="emergency">Emergency</option>
                            <option value="system">System Notice</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Severity <span class="text-red-500">*</span></label>
                        <select name="severity" id="alertSeverity" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="info">Info</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Affected District <span class="text-red-500">*</span></label>
                        <input type="text" name="affected_district" id="affectedDistrict" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. Kathmandu, Lalitpur, Bhaktapur or All">
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Time (optional)</label>
                        <input type="datetime-local" name="expires_at" id="expiresAt" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <p class="mt-1 text-xs text-gray-500">Leave empty for no expiry</p>
                    </div>
                </div>
            </div>

            <!-- Section 2: Alert Content with Language Workflow -->
            <div class="mb-6 pb-6 border-b border-gray-100">
                <h4 class="font-medium text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-bold">2</span>
                    Alert Content & Translation
                </h4>
                
                <!-- Language Tabs -->
                <div class="border-b border-gray-200 mb-4">
                    <nav class="flex gap-1" aria-label="Content language">
                        <button type="button" class="lang-tab px-4 py-2 text-sm font-medium rounded-t-lg transition" data-lang="en" onclick="switchLangTab('en')">
                            <i class="fas fa-globe-americas mr-1"></i> English
                        </button>
                        <button type="button" class="lang-tab px-4 py-2 text-sm font-medium rounded-t-lg transition" data-lang="roman" onclick="switchLangTab('roman')">
                            <i class="fas fa-font mr-1"></i> Roman Nepali
                        </button>
                        <button type="button" class="lang-tab px-4 py-2 text-sm font-medium rounded-t-lg transition" data-lang="ne" onclick="switchLangTab('ne')">
                            <i class="fas fa-language mr-1"></i> नेपाली
                        </button>
                    </nav>
                </div>

                <!-- English Tab -->
                <div id="lang-en" class="lang-panel">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title (English) <span class="text-red-500">*</span></label>
                            <input type="text" name="title" id="title_en" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Enter alert title in English">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (English) <span class="text-red-500">*</span></label>
                            <textarea name="description" id="description_en" rows="4" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Enter alert description in English"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="translateFromEnglish()" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition flex items-center gap-2" id="translateEnBtn">
                                <i class="fas fa-language"></i> Generate Roman Nepali
                            </button>
                            <button type="button" onclick="previewAlert('en')" class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center gap-2">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Roman Nepali Tab -->
                <div id="lang-roman" class="lang-panel hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title (Roman Nepali) <span class="text-red-500">*</span></label>
                            <input type="text" name="title_roman" id="title_roman" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. Nepal ma bhari barsa ko karan...">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (Roman Nepali) <span class="text-red-500">*</span></label>
                            <textarea name="description_roman" id="description_roman" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. Nepal ma bhari barsa ko karan aabasyak kaam bahek bahira nabiskinu hola."></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="generateNepali()" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition flex items-center gap-2" id="generateNepaliBtn">
                                <i class="fas fa-magic"></i> Generate नेपाली
                            </button>
                            <button type="button" onclick="previewAlert('roman')" class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center gap-2">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">Write naturally in Roman Nepali. The system will convert to Devanagari script.</p>
                    </div>
                </div>

                <!-- Nepali Tab -->
                <div id="lang-ne" class="lang-panel hidden">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title (नेपाली) <span class="text-red-500">*</span></label>
                            <input type="text" name="title_ne" id="title_ne" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. नेपालमा भारी वर्षाका कारण...">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description (नेपाली) <span class="text-red-500">*</span></label>
                            <textarea name="description_ne" id="description_ne" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. नेपालमा भारी वर्षाका कारण आवश्यक काम बाहेक बाहिर ननिस्किनु होला।"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" onclick="previewAlert('ne')" class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition flex items-center gap-2">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">Review and edit the generated Nepali text. You can also write directly in Devanagari.</p>
                    </div>
                </div>

                <!-- Nepali Preview/Result Panel (shown after generation) -->
                <div id="nepaliPreviewPanel" class="hidden bg-primary-50 border border-primary-200 rounded-lg p-4 mt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h5 class="font-medium text-primary-800 flex items-center gap-2">
                            <i class="fas fa-eye"></i> Generated नेपाली Preview
                        </h5>
                        <button type="button" onclick="closeNepaliPreview()" class="text-primary-600 hover:text-primary-800">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-primary-700 mb-1">Title (नेपाली)</label>
                            <textarea id="preview_title_ne" rows="2" class="w-full px-3 py-2 border border-primary-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500" readonly></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary-700 mb-1">Description (नेपाली)</label>
                            <textarea id="preview_description_ne" rows="4" class="w-full px-3 py-2 border border-primary-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500" readonly></textarea>
                        </div>
                        <div class="flex gap-2 pt-2 border-t border-primary-100">
                            <button type="button" onclick="regenerateNepali()" class="px-4 py-2 text-sm font-medium bg-primary-100 text-primary-700 rounded-lg hover:bg-primary-200 transition flex items-center gap-2">
                                <i class="fas fa-redo"></i> Regenerate
                            </button>
                            <button type="button" onclick="editNepali()" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition flex items-center gap-2">
                                <i class="fas fa-edit"></i> Edit नेपाली
                            </button>
                            <button type="button" onclick="useNepaliVersion()" class="px-4 py-2 text-sm font-medium bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                                <i class="fas fa-check"></i> Use नेपाली Version
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Translation Status Messages -->
                <div id="translationStatus" class="hidden mt-3 p-3 rounded-lg"></div>
            </div>

            <!-- Section 3: Alert Preview -->
            <div class="mb-6">
                <h4 class="font-medium text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-bold">3</span>
                    Alert Preview
                </h4>
                <div id="alertPreview" class="bg-gray-50 border border-gray-200 rounded-xl p-4 min-h-[200px]">
                    <div class="text-center text-gray-400 py-8">
                        <i class="fas fa-mobile-alt text-4xl mb-2"></i>
                        <p class="text-sm">Fill in alert details and click "Preview" to see how it will appear to users</p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" onclick="previewAlert(currentLangTab)" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition flex items-center gap-2">
                        <i class="fas fa-eye"></i> Update Preview
                    </button>
                    <span id="previewLangIndicator" class="flex items-center px-3 py-2 text-xs text-gray-500 bg-gray-100 rounded-lg">Previewing: English</span>
                </div>
            </div>

            <!-- Submit Actions -->
            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="saveDraft()" class="flex-1 px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-save mr-1"></i> Save Draft
                </button>
                <button type="submit" class="flex-1 px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                    <i class="fas fa-paper-plane mr-1"></i> Publish Alert
                </button>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function () {
    var map = null;
    var marker = null;
    var currentLangTab = 'en';
    var nepaliPreviewData = { title: '', description: '' };
    var isEditingNepali = false;

    function initMap() {
        if (map || typeof L === 'undefined') return;
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

    function tryInit() {
        if (!document.getElementById('alertMap')) return;
        if (map) return;
        if (typeof L === 'undefined') { setTimeout(tryInit, 200); return; }
        initMap();
    }
    tryInit();
})();

function toggleBroadcast(broadcast) {
    var box = document.getElementById('locationBox');
    if (box) box.style.display = broadcast ? 'none' : '';
    if (broadcast) {
        var lat = document.getElementById('alertLat');
        var lng = document.getElementById('alertLng');
        if (lat) lat.value = '';
        if (lng) lng.value = '';
        var ro = document.getElementById('locReadout');
        if (ro) ro.textContent = '';
    }
}

// Language Tab Switching
function switchLangTab(lang) {
    currentLangTab = lang;
    
    // Update tab buttons
    document.querySelectorAll('.lang-tab').forEach(function(btn) {
        btn.classList.remove('bg-primary-600', 'text-white', 'shadow');
        btn.classList.add('text-gray-600', 'hover:bg-gray-100');
    });
    var activeTab = document.querySelector('.lang-tab[data-lang="' + lang + '"]');
    if (activeTab) {
        activeTab.classList.add('bg-primary-600', 'text-white', 'shadow');
        activeTab.classList.remove('text-gray-600', 'hover:bg-gray-100');
    }

    // Update panels
    document.querySelectorAll('.lang-panel').forEach(function(panel) {
        panel.classList.add('hidden');
    });
    var activePanel = document.getElementById('lang-' + lang);
    if (activePanel) {
        activePanel.classList.remove('hidden');
    }

    // Update preview language indicator
    var labels = { en: 'English', roman: 'Roman Nepali', ne: 'नेपाली' };
    var indicator = document.getElementById('previewLangIndicator');
    if (indicator) {
        indicator.textContent = 'Previewing: ' + (labels[lang] || lang);
    }
}

// Translate from English to Roman Nepali (placeholder - in practice you'd use a translation service)
function translateFromEnglish() {
    var title = document.getElementById('title_en').value.trim();
    var description = document.getElementById('description_en').value.trim();
    
    if (!title && !description) {
        showStatus('Please enter English content first', 'error');
        return;
    }

    showStatus('English to Roman Nepali translation not yet implemented. Please switch to Roman Nepali tab and write manually.', 'warning');
    switchLangTab('roman');
}

// Generate Nepali from Roman Nepali
async function generateNepali() {
    var title = document.getElementById('title_roman').value.trim();
    var description = document.getElementById('description_roman').value.trim();
    
    if (!title && !description) {
        showStatus('Please enter Roman Nepali content first', 'error');
        return;
    }

    var btn = document.getElementById('generateNepaliBtn');
    var originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Generating...';
    btn.disabled = true;

    try {
        var response = await fetch('{{ route("admin.alerts.translate") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                title: title,
                description: description,
                source_language: 'roman_nepali'
            })
        });

        var data = await response.json();
        btn.innerHTML = originalText;
        btn.disabled = false;

        if (data.success && (data.title_ne || data.description_ne)) {
            nepaliPreviewData.title = data.title_ne || '';
            nepaliPreviewData.description = data.description_ne || '';
            showNepaliPreview();
            showStatus('नेपाली translation generated successfully. Review and edit if needed.', 'success');
        } else {
            var msg = data.message || 'Translation failed. You can manually enter नेपाली text.';
            if (data.warnings && data.warnings.length) {
                msg += ' Warnings: ' + data.warnings.join('; ');
            }
            showStatus(msg, data.rate_limited ? 'warning' : 'error');
        }
    } catch (e) {
        btn.innerHTML = originalText;
        btn.disabled = false;
        showStatus('Network error. Please try again.', 'error');
    }
}

// Regenerate Nepali translation
async function regenerateNepali() {
    // Same as generate but for re-generation
    await generateNepali();
}

// Show the Nepali preview panel
function showNepaliPreview() {
    document.getElementById('preview_title_ne').value = nepaliPreviewData.title;
    document.getElementById('preview_description_ne').value = nepaliPreviewData.description;
    document.getElementById('nepaliPreviewPanel').classList.remove('hidden');
    isEditingNepali = false;
    updatePreviewPanelReadonly(true);
}

// Edit Nepali (make preview fields editable)
function editNepali() {
    isEditingNepali = true;
    updatePreviewPanelReadonly(false);
    document.getElementById('preview_title_ne').focus();
}

// Use Nepali version - copy to Nepali tab fields
function useNepaliVersion() {
    var title = document.getElementById('preview_title_ne').value;
    var description = document.getElementById('preview_description_ne').value;
    
    document.getElementById('title_ne').value = title;
    document.getElementById('description_ne').value = description;
    
    document.getElementById('nepaliPreviewPanel').classList.add('hidden');
    showStatus('नेपाली version applied. You can now edit further in the नेपाली tab.', 'success');
    switchLangTab('ne');
}

function closeNepaliPreview() {
    document.getElementById('nepaliPreviewPanel').classList.add('hidden');
}

function updatePreviewPanelReadonly(readonly) {
    document.getElementById('preview_title_ne').readOnly = readonly;
    document.getElementById('preview_description_ne').readOnly = readonly;
}

// Preview Alert
async function previewAlert(lang) {
    var title, description, alertType, severity, district, titleNe, descriptionNe;
    
    if (lang === 'en') {
        title = document.getElementById('title_en').value;
        description = document.getElementById('description_en').value;
    } else if (lang === 'roman') {
        title = document.getElementById('title_roman').value;
        description = document.getElementById('description_roman').value;
    } else if (lang === 'ne') {
        title = document.getElementById('title_ne').value || document.getElementById('title_roman').value || document.getElementById('title_en').value;
        description = document.getElementById('description_ne').value || document.getElementById('description_roman').value || document.getElementById('description_en').value;
    }
    
    alertType = document.getElementById('alertType').value;
    severity = document.getElementById('alertSeverity').value;
    district = document.getElementById('affectedDistrict').value || 'All Areas';
    titleNe = document.getElementById('title_ne').value;
    descriptionNe = document.getElementById('description_ne').value;

    if (!title || !description) {
        showStatus('Please fill in title and description for the selected language', 'error');
        return;
    }

    var btnText = 'Updating...';
    // We'll just show a loading state in the preview area
    document.getElementById('alertPreview').innerHTML = '<div class="text-center text-gray-400 py-8"><i class="fas fa-spinner fa-spin text-2xl mb-2"></i><p class="text-sm">Generating preview...</p></div>';

    try {
        var response = await fetch('{{ route("admin.alerts.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                title: title,
                description: description,
                alert_type: alertType,
                severity: severity,
                affected_district: district,
                title_ne: titleNe,
                description_ne: descriptionNe,
                language: lang === 'ne' ? 'nepali' : 'english'
            })
        });

        var data = await response.json();
        
        if (data.success && data.html) {
            document.getElementById('alertPreview').innerHTML = data.html;
            var labels = { en: 'English', roman: 'Roman Nepali', ne: 'नेपाली' };
            var indicator = document.getElementById('previewLangIndicator');
            if (indicator) {
                indicator.textContent = 'Previewing: ' + (labels[lang] || lang);
            }
        } else {
            document.getElementById('alertPreview').innerHTML = '<div class="text-center text-red-500 py-8"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><p class="text-sm">Preview failed: ' + (data.message || 'Unknown error') + '</p></div>';
        }
    } catch (e) {
        document.getElementById('alertPreview').innerHTML = '<div class="text-center text-red-500 py-8"><i class="fas fa-exclamation-triangle text-2xl mb-2"></i><p class="text-sm">Network error. Please try again.</p></div>';
    }
}

// Save Draft (placeholder)
function saveDraft() {
    showStatus('Draft saving not yet implemented. Use "Publish Alert" to create the alert.', 'info');
}

// Status message helper
function showStatus(message, type) {
    var container = document.getElementById('translationStatus');
    var colors = {
        success: 'bg-green-50 text-green-800 border-green-200',
        error: 'bg-red-50 text-red-800 border-red-200',
        warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
        info: 'bg-blue-50 text-blue-800 border-blue-200'
    };
    var icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    container.className = 'mt-3 p-3 rounded-lg border ' + (colors[type] || colors.info);
    container.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + ' mr-2"></i>' + message;
    container.classList.remove('hidden');
    
    // Auto-hide after 5 seconds for non-error messages
    if (type !== 'error') {
        setTimeout(function() {
            container.classList.add('hidden');
        }, 5000);
    }
}

// Populate hidden fields before form submit
document.getElementById('alertForm').addEventListener('submit', function(e) {
    var title, description, titleNe, descriptionNe;
    
    if (currentLangTab === 'en') {
        title = document.getElementById('title_en').value;
        description = document.getElementById('description_en').value;
    } else if (currentLangTab === 'roman') {
        title = document.getElementById('title_roman').value;
        description = document.getElementById('description_roman').value;
    } else if (currentLangTab === 'ne') {
        title = document.getElementById('title_ne').value;
        description = document.getElementById('description_ne').value;
    }
    
    titleNe = document.getElementById('title_ne').value;
    descriptionNe = document.getElementById('description_ne').value;
    
    // If no Nepali content but we have Roman Nepali, try to use the generated preview
    if (!titleNe && !descriptionNe && nepaliPreviewData.title) {
        titleNe = nepaliPreviewData.title;
        descriptionNe = nepaliPreviewData.description;
    }
    
    if (!title || !description) {
        e.preventDefault();
        showStatus('Please fill in title and description', 'error');
        return false;
    }
    
    document.getElementById('submit_title').value = title;
    document.getElementById('submit_description').value = description;
    document.getElementById('submit_title_ne').value = titleNe;
    document.getElementById('submit_description_ne').value = descriptionNe;
    
    return true;
});

// Initialize default tab
document.addEventListener('DOMContentLoaded', function() {
    switchLangTab('en');
});
</script>
@endsection