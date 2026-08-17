@extends('admin.layout')

@section('title', 'Curated Routes')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-2xl font-bold text-slate-800">Curated Routes</h2>
    <button onclick="openCreate()" class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5 transition">
        <i class="fas fa-plus"></i> New Route
    </button>
</div>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                <tr>
                    <th class="text-left px-6 py-3">Route</th>
                    <th class="text-left px-4 py-3">Type</th>
                    <th class="text-left px-4 py-3">Duration</th>
                    <th class="text-left px-4 py-3">Best Season</th>
                    <th class="text-left px-4 py-3">Stops</th>
                    <th class="text-left px-4 py-3">Status</th>
                    <th class="text-right px-6 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($routes as $route)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800">{{ $route->title }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $route->slug }}</div>
                        </td>
                        <td class="px-4 py-4">
                            @if($route->route_type === 'trekking')
                                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium">Trekking</span>
                                @if($route->difficulty)
                                    <div class="text-xs text-slate-400 mt-1">{{ $route->difficultyLabel() }}</div>
                                @endif
                            @else
                                <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Itinerary</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-slate-600">{{ $route->duration_days }} day{{ $route->duration_days > 1 ? 's' : '' }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ $route->best_season ?? '—' }}</td>
                        <td class="px-4 py-4 text-slate-600">{{ count($route->waypoints ?? []) }}</td>
                        <td class="px-4 py-4">
                            @if($route->is_active)
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Active</span>
                            @else
                                <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-medium">Hidden</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('web.route', $route->slug) }}" target="_blank" class="px-3 py-1.5 text-xs font-medium bg-slate-50 text-slate-600 rounded-lg hover:bg-slate-100">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <button onclick="openEdit(@json($route))" class="px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" action="{{ route('admin.routes.destroy', $route) }}" onsubmit="return confirm('Delete this route?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-route text-3xl mb-3 block"></i>No routes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4">{{ $routes->links() }}</div>
</div>

<div id="routeModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <h4 class="text-lg font-bold mb-4" id="modalTitle">New Route</h4>
        <form method="POST" id="routeForm" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Title *</label>
                <input type="text" name="title" id="rTitle" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Slug (optional — auto-generated)</label>
                <input type="text" name="slug" id="rSlug" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Route Type *</label>
                    <select name="route_type" id="rType" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="itinerary">Itinerary (city/culture)</option>
                        <option value="trekking">Trekking (mountain trail)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Difficulty</label>
                    <select name="difficulty" id="rDifficulty" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">—</option>
                        <option value="easy">Easy</option>
                        <option value="moderate">Moderate</option>
                        <option value="challenging">Challenging</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Duration (days) *</label>
                    <input type="number" name="duration_days" id="rDays" min="1" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Best Season</label>
                    <input type="text" name="best_season" id="rSeason" placeholder="e.g. Oct–Nov" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Max Altitude (m)</label>
                    <input type="number" name="max_altitude_m" id="rMaxAlt" min="0" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Distance (km)</label>
                    <input type="number" step="0.1" name="total_distance_km" id="rDist" min="0" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Elev. Gain (m)</label>
                    <input type="number" name="elevation_gain_m" id="rElev" min="0" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Starting Point</label>
                    <input type="text" name="starting_point" id="rStart" placeholder="e.g. Lukla" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Ending Point</label>
                    <input type="text" name="ending_point" id="rEnd" placeholder="e.g. Lukla" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Image URL</label>
                <input type="url" name="image" id="rImage" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Waypoints (place IDs, comma separated)</label>
                <input type="text" name="waypoints" id="rWaypoints" placeholder="e.g. 1, 5, 12, 34" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Track (GPS line — one "lat,lng,Name" per line)</label>
                <textarea name="track" id="rTrack" rows="5" placeholder="27.6880,86.7313,Lukla&#10;27.8046,86.7100,Namche Bazaar" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono text-xs"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                <textarea name="description" id="rDesc" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" id="rActive" value="1" checked class="rounded border-slate-300"> Active on website
            </label>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('routeModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openCreate() {
    document.getElementById('routeForm').action = '{{ route('admin.routes.store') }}';
    document.getElementById('routeForm').method = 'POST';
    document.getElementById('modalTitle').textContent = 'New Route';
    ['rTitle','rSlug','rDays','rSeason','rImage','rWaypoints','rDesc'].forEach(id => document.getElementById(id).value = '');
    ['rMaxAlt','rDist','rElev','rStart','rEnd','rTrack'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('rDays').value = 1;
    document.getElementById('rType').value = 'itinerary';
    document.getElementById('rDifficulty').value = '';
    document.getElementById('rActive').checked = true;
    document.getElementById('routeModal').classList.remove('hidden');
}
function openEdit(r) {
    document.getElementById('routeForm').action = '/admin/routes/' + r.id;
    document.getElementById('routeForm').method = 'POST';
    let methodInput = document.getElementById('methodInput');
    if (!methodInput) {
        methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.id = 'methodInput';
        document.getElementById('routeForm').appendChild(methodInput);
    }
    methodInput.value = 'PUT';
    document.getElementById('modalTitle').textContent = 'Edit Route';
    document.getElementById('rTitle').value = r.title || '';
    document.getElementById('rSlug').value = r.slug || '';
    document.getElementById('rType').value = r.route_type || 'itinerary';
    document.getElementById('rDifficulty').value = r.difficulty || '';
    document.getElementById('rDays').value = r.duration_days || 1;
    document.getElementById('rSeason').value = r.best_season || '';
    document.getElementById('rMaxAlt').value = r.max_altitude_m || '';
    document.getElementById('rDist').value = r.total_distance_km || '';
    document.getElementById('rElev').value = r.elevation_gain_m || '';
    document.getElementById('rStart').value = r.starting_point || '';
    document.getElementById('rEnd').value = r.ending_point || '';
    document.getElementById('rImage').value = r.image || '';
    document.getElementById('rWaypoints').value = (r.waypoints || []).join(', ');
    document.getElementById('rTrack').value = (r.track || []).map(p => [p.lat, p.lng, p.name || ''].join(',')).join('\n');
    document.getElementById('rDesc').value = r.description || '';
    document.getElementById('rActive').checked = !!r.is_active;
    document.getElementById('routeModal').classList.remove('hidden');
}
</script>
@endsection
