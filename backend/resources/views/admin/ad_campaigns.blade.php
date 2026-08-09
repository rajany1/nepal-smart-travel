@extends('admin.layout')
@section('title', 'Ad Campaigns')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h3 class="text-2xl font-bold text-slate-900">Local Business Ads</h3><p class="text-sm text-slate-500 mt-1">Partner campaigns need approval. Admin earns the spend from partner-paid budgets (eSewa/Khalti): impressions x CPM/1000 + clicks x CPC..</p></div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2"><i class="fas fa-plus"></i> New Campaign</button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-primary-100 grid place-items-center text-primary-600 mb-2"><i class="fas fa-bullhorn"></i></div>
            <p class="text-xl font-bold text-slate-900">{{ $stats['total'] }}</p>
            <p class="text-xs text-slate-500 font-medium">Campaigns</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-amber-100 grid place-items-center text-amber-600 mb-2"><i class="fas fa-clock"></i></div>
            <p class="text-xl font-bold text-amber-600">{{ $stats['pending'] }}</p>
            <p class="text-xs text-slate-500 font-medium">Pending Approval</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-green-100 grid place-items-center text-green-600 mb-2"><i class="fas fa-check-circle"></i></div>
            <p class="text-xl font-bold text-green-600">{{ $stats['active'] }}</p>
            <p class="text-xs text-slate-500 font-medium">Live</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-blue-100 grid place-items-center text-blue-600 mb-2"><i class="fas fa-eye"></i></div>
            <p class="text-xl font-bold text-blue-600">{{ number_format($stats['impressions']) }}</p>
            <p class="text-xs text-slate-500 font-medium">Impressions</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-purple-100 grid place-items-center text-purple-600 mb-2"><i class="fas fa-mouse-pointer"></i></div>
            <p class="text-xl font-bold text-purple-600">{{ number_format($stats['clicks']) }} <span class="text-xs font-medium text-slate-400">({{ $stats['ctr'] }}% CTR)</span></p>
            <p class="text-xs text-slate-500 font-medium">Clicks</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
            <div class="w-10 h-10 rounded-lg bg-rose-100 grid place-items-center text-rose-600 mb-2"><i class="fas fa-coins"></i></div>
            <p class="text-xl font-bold text-rose-600">Rs. {{ number_format($stats['revenue'], 0) }}</p>
            <p class="text-xs text-slate-500 font-medium">Revenue (admin spend)</p>
        </div>
    </div>

    <div class="flex gap-2">
        <a href="{{ route('admin.ad-campaigns') }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ !$status ? 'bg-primary-600 text-white' : 'bg-slate-100 text-slate-600' }}">All</a>
        <a href="{{ route('admin.ad-campaigns', ['status' => 'pending']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600' }}">Pending</a>
        <a href="{{ route('admin.ad-campaigns', ['status' => 'active']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'active' ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-600' }}">Active</a>
        <a href="{{ route('admin.ad-campaigns', ['status' => 'paused']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'paused' ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-600' }}">Paused</a>
        <a href="{{ route('admin.ad-campaigns', ['status' => 'rejected']) }}" class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $status === 'rejected' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600' }}">Rejected</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr><th class="text-left px-4 py-3">Campaign</th><th class="text-left px-4 py-3">Business</th><th class="text-center px-4 py-3">Impressions</th><th class="text-center px-4 py-3">Clicks</th><th class="text-center px-4 py-3">CTR</th><th class="text-center px-4 py-3">Budget / Paid</th><th class="text-center px-4 py-3">Payment</th><th class="text-center px-4 py-3">Targeting</th><th class="text-center px-4 py-3">Status</th><th class="text-right px-4 py-3">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($campaigns as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-4">
                            <p class="font-semibold text-sm text-slate-900">{{ $c->name }}</p>
                            @if($c->content)<p class="text-xs text-slate-400">{{ Str::limit($c->content, 70) }}</p>@endif
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{{ str_replace('_', ' ', $c->ad_type) }}</span>
                        </td>
                        <td class="px-4 py-4 text-sm">{{ $c->business?->name ?? '—' }}</td>
                        <td class="px-4 py-4 text-center text-sm">{{ number_format($c->current_impressions) }}{{ $c->max_impressions > 0 ? ' / '.number_format($c->max_impressions) : '' }}</td>
                        <td class="px-4 py-4 text-center text-sm">{{ number_format($c->current_clicks) }}</td>
                        <td class="px-4 py-4 text-center text-sm">{{ $c->ctr() }}%</td>
                        <td class="px-4 py-4 text-center text-sm"><span class="text-xs font-semibold">Rs. {{ number_format((float) $c->budget, 0) }}</span><span class="text-xs text-slate-400 block">/ {{ number_format((float) $c->paid_amount, 0) }} paid</span></td><td class="px-4 py-4 text-center">@if($c->payment_status === 'paid')<span class="text-xs bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full">Paid</span>@elseif($c->payment_status === 'refunded')<span class="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">Refunded</span>@else<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Unpaid</span>@endif</td>
                        <td class="px-4 py-4 text-center">
                            <div class="text-xs text-slate-500">
                                @if($c->contexts)<p class="text-[10px]"><i class="fas fa-crosshairs"></i> {{ implode(', ', array_map('ucfirst', $c->contexts)) }}</p>@endif
                                @if($c->target_district)<p class="text-[10px] text-teal-600">{{ $c->target_district }}</p>@endif
                                @if($c->target_category)<p class="text-[10px]">{{ $c->target_category }}</p>@endif
                                @if(!$c->contexts && !$c->target_district && !$c->target_category)<span class="text-[10px] text-slate-300">Global</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            @if($c->status === 'rejected' && $c->rejection_reason)
                                <div class="text-[10px] text-red-500 mb-1">{{ $c->rejection_reason }}</div>
                            @endif
                            @switch($c->status)
                                @case('active')
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Active</span>
                                    @if($c->ends_at && $c->ends_at->lte(now()))<span class="block text-[10px] text-orange-500 mt-0.5">Ended — not serving</span>
                                    @elseif(!$c->hasBudget())<span class="block text-[10px] text-orange-500 mt-0.5">Budget exhausted — not serving</span>@endif
                                    @break
                                @case('pending')<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Pending</span>@break
                                @case('paused')
                                    <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">Paused</span>
                                    @if($c->paused_by === 'system' && !$c->hasBudget())<span class="block text-[10px] text-orange-500 mt-0.5">Budget exhausted</span>
                                    @elseif($c->paused_by === 'admin')<span class="block text-[10px] text-orange-500 mt-0.5">Paused by admin</span>
                                    @elseif($c->ends_at && $c->ends_at->lte(now()))<span class="block text-[10px] text-orange-500 mt-0.5">Ended</span>
                                    @elseif($c->paused_by === 'partner')<span class="block text-[10px] text-orange-500 mt-0.5">Paused by partner</span>@endif
                                    @break
                                @case('completed')<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Completed</span>@break
                                @case('rejected')<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Rejected</span>@break
                            @endswitch
                        </td>
                        <td class="px-4 py-4 text-right whitespace-nowrap">
                            @if($c->status === 'pending')
                                @if($c->business_id && $c->payment_status !== 'paid')<span class="inline-flex items-center gap-1 text-xs bg-amber-50 text-amber-700 px-2 py-1 rounded-lg mr-2" title="Partner must complete eSewa/Khalti payment first"><i class="fas fa-hourglass-half"></i></span>@endif
                                <form method="POST" action="{{ route('admin.ad-campaigns.approve', $c) }}" class="inline">
                                    @csrf
                                    <button class="px-3 py-1.5 text-xs font-semibold bg-green-600 hover:bg-green-700 text-white rounded-lg"><i class="fas fa-check"></i> Approve</button>
                                </form>
                                <button onclick="openReject({{ $c->id }})" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"><i class="fas fa-times"></i> Reject</button>
                            @endif
                            @if($c->status === 'active')
                                <form method="POST" action="{{ route('admin.ad-campaigns.pause', $c) }}" class="inline">
                                    @csrf
                                    <button class="px-3 py-1.5 text-xs font-medium bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100" title="Pause"><i class="fas fa-pause"></i></button>
                                </form>
                            @elseif($c->status === 'paused')
                                <form method="POST" action="{{ route('admin.ad-campaigns.resume', $c) }}" class="inline">
                                    @csrf
                                    <button class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100" title="Resume"><i class="fas fa-play"></i></button>
                                </form>
                            @endif
                            <button onclick="openEdit({{ $c->id }})" class="px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100"><i class="fas fa-edit"></i></button>
                                @if($c->payment_status === 'paid')
                                <form method="POST" action="{{ route('admin.ad-campaigns.refund', $c) }}" class="inline" onsubmit="return confirm('Refund this campaign to the partner?')">
                                    @csrf
                                    <button class="px-3 py-1.5 text-xs font-medium bg-slate-100 text-slate-600 rounded-lg hover:bg-slate-200" title="Refund payment"><i class="fas fa-undo"></i></button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.ad-campaigns.destroy', $c) }}" class="inline" onsubmit="return confirm('Delete this campaign?')">
                                @csrf @method('DELETE')
                                <button class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-ad text-3xl mb-3"></i><p class="text-sm">No campaigns yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $campaigns->appends(['status' => $status])->links() }}</div>
</div>

{{-- Reject Modal --}}
<div id="rejectModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4"><h4 class="text-lg font-bold">Reject Campaign</h4><button onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button></div>
        <form method="POST" id="rejectForm" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Reason</label>
                <textarea name="reason" rows="3" required placeholder="e.g. Contains misleading claims..." class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-red-600 text-white rounded-lg hover:bg-red-700">Reject</button>
            </div>
        </form>
    </div>
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
function openReject(id) {
    document.getElementById('rejectForm').action = '/admin/ad-campaigns/' + id + '/reject';
    document.getElementById('rejectModal').classList.remove('hidden');
}
</script>
@endsection
