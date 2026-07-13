@extends('admin.layout')
@section('title', 'OSM Live Places - Places Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-1">
            <a href="{{ route('admin.places') }}" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-database mr-1"></i> Database
            </a>
            <a href="{{ route('admin.places.osm') }}" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-emerald-600 text-white">
                <i class="fas fa-globe-asia mr-1"></i> OSM Live (Nepal)
            </a>
        </div>
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h3 class="font-semibold text-gray-800">OpenStreetMap — Nepal</h3>
                <span class="text-xs text-gray-400">({{ count($osmPlaces) }} results)</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('admin.places.osm') }}" class="flex items-center gap-2">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search OSM by name..." class="pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg w-64 focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none">
                    </div>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Search</button>
                    @if($search)
                    <a href="{{ route('admin.places.osm') }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">Clear</a>
                    @endif
                </form>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">District</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Coordinates</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Phone</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($osmPlaces as $index => $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $index + 1 }}</td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900 max-w-[200px] truncate">{{ $p['name'] }}</p>
                            @if($p['address'])
                            <p class="text-xs text-gray-500 truncate">{{ $p['address'] }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">{{ $p['category'] ?? 'Place' }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $p['district'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500 font-mono">
                            {{ number_format($p['latitude'], 4) }}, {{ number_format($p['longitude'], 4) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $p['phone'] ?? '-' }}</td>
                        <td class="px-6 py-4 text-right flex gap-2 justify-end">
                            <a href="{{ route('admin.live-map') }}?lat={{ $p['latitude'] }}&lng={{ $p['longitude'] }}&zoom=16" target="_blank" class="px-3 py-1.5 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition" title="View on map">
                                <i class="fas fa-map-marker-alt"></i>
                            </a>
                            <button type="button" onclick="quickImport(this)" data-name="{{ str_replace('"', '&quot;', $p['name']) }}" data-category="{{ $p['category'] }}" data-lat="{{ $p['latitude'] }}" data-lng="{{ $p['longitude'] }}" data-address="{{ str_replace('"', '&quot;', $p['address'] ?? '') }}" data-district="{{ str_replace('"', '&quot;', $p['district'] ?? '') }}" data-phone="{{ str_replace('"', '&quot;', $p['phone'] ?? '') }}" class="px-3 py-1.5 text-xs font-medium bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100 transition" title="Import to database">
                                <i class="fas fa-download"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-map-marker-alt text-3xl text-gray-300 mb-3 block"></i>
                            @if($osmError)
                            <p class="text-red-500 font-medium mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> {{ $osmError }}</p>
                            <p class="text-xs text-gray-400">The Overpass public demo server is rate-limited. For production, self-host the Overpass instance.</p>
                            @elseif($search)
                            No OSM places found matching "{{ $search }}"
                            @else
                            No OSM places found in Nepal.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(count($osmPlaces) > 0)
        <div class="px-6 py-4 border-t border-gray-100 text-xs text-gray-400 flex items-center justify-between">
            <span>Showing {{ count($osmPlaces) }} places from OpenStreetMap (live data, not stored in DB)</span>
            <span class="text-emerald-600"><i class="fas fa-sync-alt mr-1"></i> Auto-refreshes every 10 min</span>
        </div>
        @endif
    </div>

    <!-- Right Column -->
    <div class="space-y-6">
        <!-- Quick Import Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800"><i class="fas fa-download text-emerald-500 mr-2"></i>Import to Database</h3>
            </div>
            <div class="p-6">
                <form id="importForm" method="POST" action="{{ route('admin.places.create') }}">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="name" id="import_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="import_description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category_id" id="import_category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" name="address" id="import_address" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                            <input type="text" name="district" id="import_district" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input type="number" step="0.000001" name="latitude" id="import_latitude" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input type="number" step="0.000001" name="longitude" id="import_longitude" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" name="phone" id="import_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-emerald-700 transition">
                            <i class="fas fa-save mr-1"></i> Save to Database
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800"><i class="fas fa-info-circle text-blue-500 mr-2"></i>About OSM Live</h3>
            </div>
            <div class="p-6 space-y-3 text-sm text-gray-600">
                <p><i class="fas fa-check-circle text-emerald-500 mr-1"></i> Data fetched live from <a href="https://overpass-api.de" target="_blank" class="text-blue-600 hover:underline">Overpass API</a></p>
                <p><i class="fas fa-check-circle text-emerald-500 mr-1"></i> Covers all Nepal (26–31°N, 79.5–89°E)</p>
                <p><i class="fas fa-check-circle text-emerald-500 mr-1"></i> Max 500 results per query</p>
                <p><i class="fas fa-check-circle text-emerald-500 mr-1"></i> Data cached for 10 minutes</p>
                <p><i class="fas fa-arrow-right text-blue-500 mr-1"></i> Click <i class="fas fa-map-marker-alt text-blue-500"></i> to view on live map</p>
                <p><i class="fas fa-arrow-right text-blue-500 mr-1"></i> Click <i class="fas fa-download text-emerald-500"></i> to pre-fill import form</p>
            </div>
        </div>

        <!-- Category Management -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Categories</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.places.categories') }}" class="mb-4">
                    @csrf
                    <div class="flex gap-2">
                        <input type="text" name="name" placeholder="New category name" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500">
                        <button type="submit" class="px-3 py-2 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700"><i class="fas fa-plus"></i></button>
                    </div>
                </form>
                <div class="space-y-2">
                    @foreach($categories as $cat)
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-700">{{ $cat->name }}</span>
                        <form method="POST" action="{{ route('admin.places.categories') }}" onsubmit="return confirm('Delete category {{ $cat->name }}?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="id" value="{{ $cat->id }}">
                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function quickImport(btn) {
    document.getElementById('import_name').value = btn.dataset.name;
    document.getElementById('import_latitude').value = btn.dataset.lat;
    document.getElementById('import_longitude').value = btn.dataset.lng;
    document.getElementById('import_address').value = btn.dataset.address;
    document.getElementById('import_district').value = btn.dataset.district;
    document.getElementById('import_phone').value = btn.dataset.phone;

    const catSelect = document.getElementById('import_category_id');
    const catMap = {
        'Restaurant': 'Restaurant', 'Cafe': 'Cafe', 'Food': 'Food',
        'Hotel': 'Hotel', 'Hospital': 'Hospital', 'Clinic': 'Clinic',
        'Pharmacy': 'Pharmacy', 'Bank': 'Bank', 'ATM': 'ATM',
        'Fuel Station': 'Fuel Station', 'Attraction': 'Attractions',
        'Emergency': 'Emergency', 'Transport': 'Transport',
        'Education': 'Education', 'Entertainment': 'Entertainment',
        'Market': 'Shopping', 'Shopping': 'Shopping',
        'Parking': 'Parking', 'Services': 'Services',
    };
    const mapped = catMap[btn.dataset.category] || btn.dataset.category;
    for (let i = 0; i < catSelect.options.length; i++) {
        if (catSelect.options[i].text.toLowerCase() === mapped.toLowerCase()) {
            catSelect.selectedIndex = i;
            break;
        }
    }

    document.getElementById('importForm').scrollIntoView({ behavior: 'smooth' });
}
</script>
@endsection