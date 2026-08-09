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
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Duration (days) *</label>
                    <input type="number" name="duration_days" id="rDays" min="1" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Best Season</label>
                    <input type="text" name="best_season" id="rSeason" placeholder="e.g. Oct–Nov" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
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
    document.getElementById('rDays').value = 1;
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
    document.getElementById('rDays').value = r.duration_days || 1;
    document.getElementById('rSeason').value = r.best_season || '';
    document.getElementById('rImage').value = r.image || '';
    document.getElementById('rWaypoints').value = (r.waypoints || []).join(', ');
    document.getElementById('rDesc').value = r.description || '';
    document.getElementById('rActive').checked = !!r.is_active;
    document.getElementById('routeModal').classList.remove('hidden');
}
</script>
@endsection
