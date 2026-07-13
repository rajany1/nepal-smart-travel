@extends('admin.layout')
@section('title', 'Ad Campaigns')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h3 class="text-2xl font-bold text-slate-900">Local Business Ads</h3><p class="text-sm text-slate-500 mt-1">Banner, promoted place & sponsored card campaigns.</p></div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2"><i class="fas fa-plus"></i> New Campaign</button>
    </div>

    <div class="flex gap-2">
        <a href="{{ route('admin.ad-campaigns') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$status ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600' }}">All</a>
        <a href="{{ route('admin.ad-campaigns', ['status' => 'active']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'active' ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-600' }}">Active</a>
        <a href="{{ route('admin.ad-campaigns', ['status' => 'pending']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600' }}">Pending</a>
        <a href="{{ route('admin.ad-campaigns', ['status' => 'paused']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'paused' ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-600' }}">Paused</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr><th>Campaign</th><th>Type</th><th>Business</th><th class="text-center">Impressions</th><th class="text-center">Budget</th><th class="text-center">Status</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($campaigns as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4"><p class="font-semibold text-sm">{{ $c->name }}</p>@if($c->content)<p class="text-xs text-slate-400">{{ Str::limit($c->content, 60) }}</p>@endif</td>
                        <td class="px-6 py-4"><span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full">{{ str_replace('_', ' ', $c->ad_type) }}</span></td>
                        <td class="px-6 py-4 text-sm">{{ $c->business?->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-center text-sm">{{ $c->current_impressions }}{{ $c->max_impressions > 0 ? ' / '.$c->max_impressions : '' }}</td>
                        <td class="px-6 py-4 text-center font-semibold">Rs. {{ number_format($c->budget) }}</td>
                        <td class="px-6 py-4 text-center">
                            @switch($c->status)
                                @case('active')<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Active</span>@break
                                @case('pending')<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Pending</span>@break
                                @case('paused')<span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">Paused</span>@break
                                @case('completed')<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Completed</span>@break
                                @case('rejected')<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Rejected</span>@break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button onclick="openEdit({{ $c->id }})" class="px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100"><i class="fas fa-edit"></i> Edit</button>
                            <form method="POST" action="{{ route('admin.ad-campaigns.destroy', $c) }}" class="inline" onsubmit="return confirm('Delete this campaign?')">
                                @csrf @method('DELETE')
                                <button class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-ad text-3xl mb-3"></i><p class="text-sm">No campaigns yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $campaigns->appends(['status' => $status])->links() }}</div>
</div>

<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4"><h4 class="text-lg font-bold">New Campaign</h4><button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button></div>
        <form method="POST" action="{{ route('admin.ad-campaigns.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div><label>Campaign Name</label><input type="text" name="name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Ad Type</label><select name="ad_type" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="banner">Banner</option><option value="promoted_place">Promoted Place</option><option value="sponsored_card">Sponsored Card</option></select></div>
            </div>
            <div><label>Business (optional)</label><select name="business_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="">— None —</option>@foreach($partners as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
            <div><label>Content</label><textarea name="content" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Target URL</label><input type="url" name="target_url" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Status</label><select name="status" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="pending">Pending</option><option value="active">Active</option><option value="paused">Paused</option></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Target District</label><input type="text" name="target_district" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Target Category</label><input type="text" name="target_category" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label>Budget (Rs.)</label><input type="number" name="budget" step="0.01" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Cost/View (Rs.)</label><input type="number" name="cost_per_view" step="0.01" min="0" value="0.50" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Max Impressions</label><input type="number" name="max_impressions" min="0" value="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Start Date</label><input type="datetime-local" name="starts_at" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>End Date</label><input type="datetime-local" name="ends_at" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700">Create</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4"><h4 class="text-lg font-bold">Edit Campaign</h4><button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button></div>
        <form method="POST" id="editForm" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div><label>Name</label><input type="text" name="name" id="editName" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Type</label><select name="ad_type" id="editType" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="banner">Banner</option><option value="promoted_place">Promoted Place</option><option value="sponsored_card">Sponsored Card</option></select></div>
            </div>
            <div><label>Business</label><select name="business_id" id="editBusiness" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="">— None —</option>@foreach($partners as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
            <div><label>Content</label><textarea name="content" id="editContent" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea></div>
            <div class="grid grid-cols-2 gap-4">
                <div><label>Target URL</label><input type="url" name="target_url" id="editUrl" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Status</label><select name="status" id="editStatus" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"><option value="pending">Pending</option><option value="active">Active</option><option value="paused">Paused</option><option value="rejected">Rejected</option></select></div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div><label>Budget (Rs.)</label><input type="number" name="budget" id="editBudget" step="0.01" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Cost/View</label><input type="number" name="cost_per_view" id="editCost" step="0.01" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
                <div><label>Max Impressions</label><input type="number" name="max_impressions" id="editMax" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
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
let campaigns = @json($campaigns->items());
function openEdit(id) {
    const c = campaigns.find(x => x.id === id); if (!c) return;
    document.getElementById('editForm').action = '/admin/ad-campaigns/' + id;
    document.getElementById('editName').value = c.name; document.getElementById('editType').value = c.ad_type;
    document.getElementById('editBusiness').value = c.business_id || ''; document.getElementById('editContent').value = c.content || '';
    document.getElementById('editUrl').value = c.target_url || ''; document.getElementById('editStatus').value = c.status;
    document.getElementById('editBudget').value = c.budget; document.getElementById('editCost').value = c.cost_per_view;
    document.getElementById('editMax').value = c.max_impressions;
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
@endsection
