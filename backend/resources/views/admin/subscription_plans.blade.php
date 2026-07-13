@extends('admin.layout')
@section('title', 'Subscription Plans')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h3 class="text-2xl font-bold text-slate-900">Subscription Plans</h3><p class="text-sm text-slate-500 mt-1">Premium plans for users — offline maps, AI itinerary, trek planner.</p></div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2"><i class="fas fa-plus"></i> New Plan</button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr><th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Plan</th><th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Price</th><th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Billing</th><th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Subscribers</th><th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th><th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($plans as $plan)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <p class="font-semibold">{{ $plan->name }}</p>
                            @if($plan->description)<p class="text-xs text-slate-400">{{ $plan->description }}</p>@endif
                            @php $rawFeat = $plan->features; $planFeatures = is_array($rawFeat) ? $rawFeat : (is_string($rawFeat) ? (json_decode($rawFeat, true) ?? []) : []); if (!is_array($planFeatures)) $planFeatures = []; @endphp
                            @if(count($planFeatures))
                            <div class="flex gap-1 mt-1">@foreach(array_slice($planFeatures, 0, 3) as $f)<span class="text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ $f }}</span>@endforeach @if(count($planFeatures) > 3)<span class="text-xs text-slate-400">+{{ count($planFeatures)-3 }} more</span>@endif</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center font-bold text-lg text-primary-600">Rs. {{ number_format($plan->price) }}</td>
                        <td class="px-6 py-4 text-center text-sm text-slate-600">{{ $plan->billing_interval }}</td>
                        <td class="px-6 py-4 text-center text-sm text-slate-600">{{ $plan->userSubscriptions()->count() }}</td>
                        <td class="px-6 py-4 text-center">
                            <form method="POST" action="{{ route('admin.subscription.plans.toggle-active', $plan) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-xs px-3 py-1.5 rounded-full font-medium transition {{ $plan->is_active ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-red-100 text-red-700 hover:bg-red-200' }}">
                                    {{ $plan->is_active ? 'Visible' : 'Hidden' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button onclick="openEdit({{ $plan->id }})" class="px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100"><i class="fas fa-edit"></i> Edit</button>
                            <form method="POST" action="{{ route('admin.subscription.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan? This cannot be undone.')" class="inline ml-1">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-crown text-3xl mb-3"></i><p class="text-sm">No plans yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $plans->links() }}</div>
</div>

<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4"><h4 class="text-lg font-bold">New Plan</h4><button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button></div>
        <form method="POST" action="{{ route('admin.subscription.plans.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div><label>Name</label><input type="text" name="name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Price (Rs.)</label><input type="number" name="price" step="0.01" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div><label>Description</label><textarea name="description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Billing Interval</label><select name="billing_interval" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
                <div><label>Sort Order</label><input type="number" name="sort_order" min="0" value="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div><label>Features (JSON array)</label><textarea name="features" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono" placeholder='["offline_maps", "ai_itinerary", "trek_planner"]'></textarea></div>
            <div class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300"><label class="text-sm">Active</label></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700">Create</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4"><h4 class="text-lg font-bold">Edit Plan</h4><button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button></div>
        <form method="POST" id="editForm" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div><label>Name</label><input type="text" name="name" id="editName" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Price (Rs.)</label><input type="number" name="price" id="editPrice" step="0.01" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div><label>Description</label><textarea name="description" id="editDescription" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Billing</label><select name="billing_interval" id="editBilling" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
                <div><label>Sort Order</label><input type="number" name="sort_order" id="editSortOrder" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div><label>Features (JSON)</label><textarea name="features" id="editFeatures" rows="3" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono"></textarea></div>
            <div class="flex items-center gap-2"><input type="checkbox" name="is_active" id="editActive" value="1" class="rounded border-slate-300"><label class="text-sm">Active</label></div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700">Update</button>
            </div>
        </form>
    </div>
</div>
@endsection
@section('scripts')
<script>
let plans = @json($plans->items());
function openEdit(id) {
    const p = plans.find(x => x.id === id); if (!p) return;
    document.getElementById('editForm').action = '/admin/subscription/plans/' + id;
    document.getElementById('editName').value = p.name; document.getElementById('editPrice').value = p.price;
    document.getElementById('editDescription').value = p.description || '';
    document.getElementById('editBilling').value = p.billing_interval;
    document.getElementById('editSortOrder').value = p.sort_order;
    document.getElementById('editFeatures').value = JSON.stringify(p.features || [], null, 2);
    document.getElementById('editActive').checked = p.is_active;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
@endsection
