@extends('admin.layout')
@section('title', 'Sponsors')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Sponsors</h3>
            <p class="text-sm text-slate-500 mt-1">Partner brands that provide rewards in the XP store.</p>
        </div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Sponsor
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Sponsor</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Items</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Order</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sponsors as $sponsor)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                @if($sponsor->logo)
                                <img src="{{ $sponsor->logo_url }}" alt="{{ $sponsor->name }}" class="w-10 h-10 rounded-lg object-cover bg-slate-100">
                                @else
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 grid place-items-center text-indigo-600">
                                    <i class="fas fa-building"></i>
                                </div>
                                @endif
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $sponsor->name }}</p>
                                    @if($sponsor->website)
                                    <p class="text-xs text-slate-400">{{ $sponsor->website }}</p>
                                    @endif
                                    @if($sponsor->description)
                                    <p class="text-xs text-slate-400 mt-0.5">{{ Str::limit($sponsor->description, 50) }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            @if($sponsor->contact_email)
                            <p class="text-slate-600"><i class="fas fa-envelope w-4 text-slate-400"></i> {{ $sponsor->contact_email }}</p>
                            @endif
                            @if($sponsor->contact_phone)
                            <p class="text-slate-600"><i class="fas fa-phone w-4 text-slate-400"></i> {{ $sponsor->contact_phone }}</p>
                            @endif
                            @if($sponsor->latitude && $sponsor->longitude)
                            <p class="text-slate-600"><i class="fas fa-map-marker-alt w-4 text-slate-400"></i> {{ number_format($sponsor->latitude, 4) }}, {{ number_format($sponsor->longitude, 4) }}</p>
                            @endif
                            @if(!$sponsor->contact_email && !$sponsor->contact_phone && !$sponsor->latitude)
                            <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-slate-600">{{ $sponsor->shopItems()->count() }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($sponsor->is_active)
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Active</span>
                            @else
                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-slate-600">{{ $sponsor->sort_order }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="openEdit({{ $sponsor->id }})" class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" action="{{ route('admin.sponsors.destroy', $sponsor) }}" onsubmit="return confirm('Delete this sponsor?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-handshake text-3xl mb-3"></i>
                            <p class="text-sm">No sponsors yet.</p>
                            <p class="text-xs">Add your first partner brand!</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $sponsors->links() }}
    </div>
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">New Sponsor</h4>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.sponsors.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                <input type="text" name="name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Logo</label>
                <input type="file" name="logo" accept="image/jpg,image/jpeg,image/png,image/webp" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                <p class="text-xs text-slate-400 mt-1">JPG, PNG or WebP. Max 2MB.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Website</label>
                    <input type="url" name="website" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="https://">
                    <p class="text-xs text-slate-400 mt-1">Optional if location is provided.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contact Email</label>
                    <input type="email" name="contact_email" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Latitude</label>
                    <input type="number" name="latitude" step="any" min="-90" max="90" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="27.7172">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Longitude</label>
                    <input type="number" name="longitude" step="any" min="-180" max="180" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="85.3240">
                </div>
            </div>
            <p class="text-xs text-slate-400 -mt-2">Required when no website is provided. Use <a href="https://www.google.com/maps" target="_blank" class="text-indigo-600 underline">Google Maps</a> to find coordinates.</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                    <input type="text" name="contact_phone" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="createActive" value="1" checked class="rounded border-slate-300">
                <label for="createActive" class="text-sm text-slate-700">Active</label>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Create Sponsor</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">Edit Sponsor</h4>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="editForm" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                <input type="text" name="name" id="editName" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Logo <span class="text-slate-400 font-normal">(leave empty to keep current)</span></label>
                <input type="file" name="logo" accept="image/jpg,image/jpeg,image/png,image/webp" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                <textarea name="description" id="editDescription" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Website</label>
                    <input type="url" name="website" id="editWebsite" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    <p class="text-xs text-slate-400 mt-1">Optional if location is provided.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Contact Email</label>
                    <input type="email" name="contact_email" id="editEmail" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Latitude</label>
                    <input type="number" name="latitude" id="editLatitude" step="any" min="-90" max="90" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="27.7172">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Longitude</label>
                    <input type="number" name="longitude" id="editLongitude" step="any" min="-180" max="180" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="85.3240">
                </div>
            </div>
            <p class="text-xs text-slate-400 -mt-2">Required when no website is provided.</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                    <input type="text" name="contact_phone" id="editPhone" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="editSortOrder" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="editActive" value="1" class="rounded border-slate-300">
                <label for="editActive" class="text-sm text-slate-700">Active</label>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Update Sponsor</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
let sponsors = @json($sponsors->items());

function openEdit(id) {
    const s = sponsors.find(sp => sp.id === id);
    if (!s) return;
    document.getElementById('editForm').action = '/admin/sponsors/' + id;
    document.getElementById('editName').value = s.name;
    document.getElementById('editDescription').value = s.description || '';
    document.getElementById('editWebsite').value = s.website || '';
    document.getElementById('editLatitude').value = s.latitude ?? '';
    document.getElementById('editLongitude').value = s.longitude ?? '';
    document.getElementById('editEmail').value = s.contact_email || '';
    document.getElementById('editPhone').value = s.contact_phone || '';
    document.getElementById('editSortOrder').value = s.sort_order;
    document.getElementById('editActive').checked = s.is_active;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
@endsection
