@extends('admin.layout')
@section('title', 'Travel Partners')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Travel Partners</h3>
            <p class="text-sm text-slate-500 mt-1">Hotels, vehicle rentals, guides & adventure companies that earn you commission.</p>
        </div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Partner
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Partner</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Commission</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Bookings</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($partners as $p)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-indigo-100 grid place-items-center text-indigo-600">
                                    <i class="fas fa-{{ $p->type === 'hotel' ? 'hotel' : ($p->type === 'guide' ? 'user-tie' : ($p->type === 'adventure' ? 'mountain' : 'car')) }}"></i>
                                </div>
                                <div>
                                    <p class="font-semibold">{{ $p->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $p->district ?? '—' }}{{ $p->phone ? ' • '.$p->phone : '' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4"><span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full">{{ str_replace('_', ' ', $p->type) }}</span></td>
                        <td class="px-6 py-4 text-center"><span class="font-semibold text-green-600">{{ $p->commission_rate }}%</span>@if($p->commission_fixed > 0) <span class="text-xs text-slate-400">+ Rs.{{ number_format($p->commission_fixed) }}</span>@endif</td>
                        <td class="px-6 py-4 text-center text-sm text-slate-600">{{ $p->bookings_count }}</td>
                        <td class="px-6 py-4 text-center">@if($p->is_active)<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Active</span>@else<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Inactive</span>@endif</td>
                        <td class="px-6 py-4 text-right">
                            <button onclick="openEdit({{ $p->id }})" class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100"><i class="fas fa-edit"></i> Edit</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-building text-3xl mb-3"></i><p class="text-sm">No partners yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $partners->links() }}</div>
</div>

<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold">New Partner</h4>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.travel-partners.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Name</label><input type="text" name="name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="type" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="hotel">Hotel</option>
                        <option value="vehicle_rental">Vehicle Rental</option>
                        <option value="guide">Guide</option>
                        <option value="adventure">Adventure</option>
                    </select>
                </div>
            </div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Description</label><textarea name="description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Phone</label><input type="text" name="phone" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Email</label><input type="email" name="email" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Website</label><input type="url" name="website" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>District</label><input type="text" name="district" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div><label>Address</label><input type="text" name="address" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Commission Rate (%)</label><input type="number" name="commission_rate" step="0.01" value="10" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Fixed Commission (Rs.)</label><input type="number" name="commission_fixed" step="0.01" value="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"><label class="text-sm">Active</label></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Create</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold">Edit Partner</h4>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="editForm" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div><label>Name</label><input type="text" name="name" id="editName" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Type</label>
                    <select name="type" id="editType" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="hotel">Hotel</option>
                        <option value="vehicle_rental">Vehicle Rental</option>
                        <option value="guide">Guide</option>
                        <option value="adventure">Adventure</option>
                    </select>
                </div>
            </div>
            <div><label>Description</label><textarea name="description" id="editDescription" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Phone</label><input type="text" name="phone" id="editPhone" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Email</label><input type="email" name="email" id="editEmail" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Website</label><input type="url" name="website" id="editWebsite" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>District</label><input type="text" name="district" id="editDistrict" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div><label>Address</label><input type="text" name="address" id="editAddress" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Commission Rate (%)</label><input type="number" name="commission_rate" id="editRate" step="0.01" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Fixed Commission (Rs.)</label><input type="number" name="commission_fixed" id="editFixed" step="0.01" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="flex items-center gap-2"><input type="checkbox" name="is_active" id="editActive" value="1" class="rounded border-slate-300"><label class="text-sm">Active</label></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Update</button>
            </div>
        </form>
    </div>
</div>
@endsection
@section('scripts')
<script>
let partners = @json($partners->items());
function openEdit(id) {
    const p = partners.find(x => x.id === id); if (!p) return;
    document.getElementById('editForm').action = '/admin/travel-partners/' + id;
    document.getElementById('editName').value = p.name; document.getElementById('editType').value = p.type;
    document.getElementById('editDescription').value = p.description || ''; document.getElementById('editPhone').value = p.phone || '';
    document.getElementById('editEmail').value = p.email || ''; document.getElementById('editWebsite').value = p.website || '';
    document.getElementById('editDistrict').value = p.district || ''; document.getElementById('editAddress').value = p.address || '';
    document.getElementById('editRate').value = p.commission_rate; document.getElementById('editFixed').value = p.commission_fixed;
    document.getElementById('editActive').checked = p.is_active; document.getElementById('editModal').classList.remove('hidden');
}
</script>
@endsection
