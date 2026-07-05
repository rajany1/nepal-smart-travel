@extends('admin.layout')
@section('title', 'Store Items')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">XP Reward Store</h3>
            <p class="text-sm text-slate-500 mt-1">Partner-sponsored rewards — users redeem XP for discounts, free items, and offers.</p>
        </div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Item
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Item</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Sponsor</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Reward Type</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Price (XP)</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-{{ $item->icon }} text-indigo-600 text-xl w-6 text-center"></i>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $item->name }}</p>
                                    @if($item->description)
                                    <p class="text-xs text-slate-400">{{ Str::limit($item->description, 60) }}</p>
                                    @endif
                                    @if($item->terms)
                                    <p class="text-xs text-slate-400 mt-0.5"><i class="fas fa-info-circle"></i> {{ Str::limit($item->terms, 40) }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            @if($item->sponsor)
                            <div class="flex items-center gap-2">
                                @if($item->sponsor->logo)
                                <img src="{{ $item->sponsor->logo_url }}" alt="" class="w-7 h-7 rounded object-cover bg-slate-100">
                                @else
                                <div class="w-7 h-7 rounded bg-indigo-100 grid place-items-center text-indigo-600 text-xs">
                                    <i class="fas fa-building"></i>
                                </div>
                                @endif
                                <span class="text-sm font-medium">{{ $item->sponsor->name }}</span>
                            </div>
                            @else
                            <span class="text-sm text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                            $rewardLabels = ['discount' => 'Discount', 'free_item' => 'Free Item', 'voucher' => 'Voucher', 'special_offer' => 'Special Offer'];
                            $rewardColors = ['discount' => 'blue', 'free_item' => 'green', 'voucher' => 'purple', 'special_offer' => 'amber'];
                            $rc = $rewardColors[$item->reward_type] ?? 'slate';
                            @endphp
                            <span class="text-xs bg-{{ $rc }}-100 text-{{ $rc }}-700 px-2 py-1 rounded-full font-medium">
                                {{ $rewardLabels[$item->reward_type] ?? $item->reward_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center font-semibold text-amber-600">{{ number_format($item->price_xp) }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($item->stock_type === 'unlimited')
                            <span class="text-xs text-green-600 font-medium"><i class="fas fa-infinity mr-1"></i> Unlimited</span>
                            @elseif($item->stock_type === 'limited')
                            <span class="text-xs font-medium {{ $item->stock_qty > 0 ? 'text-blue-600' : 'text-red-600' }}">{{ $item->stock_qty }} left</span>
                            @else
                            <span class="text-xs text-purple-600 font-medium"><i class="fas fa-key mr-1"></i> {{ $item->availableCodes()->count() }}/{{ $item->codes()->count() }} codes</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($item->is_active)
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Active</span>
                            @else
                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @if($item->stock_type === 'code_pool')
                                <button onclick="openUploadCodes({{ $item->id }})" class="px-3 py-1.5 text-xs font-medium bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100 transition">
                                    <i class="fas fa-upload"></i> Codes
                                </button>
                                @endif
                                <button onclick="openEdit({{ $item->id }})" class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                            <i class="fas fa-store text-3xl mb-3"></i>
                            <p class="text-sm">No shop items yet.</p>
                            <p class="text-xs">Create your first sponsor reward!</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Create Modal --}}
<div id="createModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">New Reward Item</h4>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.store.items.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input type="text" name="name" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Icon (FontAwesome)</label>
                    <input type="text" name="icon" value="fa-gift" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reward Type</label>
                    <select name="reward_type" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="discount">Discount</option>
                        <option value="free_item">Free Item</option>
                        <option value="voucher">Voucher</option>
                        <option value="special_offer">Special Offer</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                <textarea name="description" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sponsor</label>
                    <select name="sponsor_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">— No sponsor —</option>
                        @foreach($sponsors as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Price (XP)</label>
                    <input type="number" name="price_xp" min="1" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Min Level</label>
                    <input type="number" name="min_level" min="1" value="1" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Expiry (days)</label>
                    <input type="number" name="expiry_days" min="1" placeholder="e.g. 30" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Usage Limit / User</label>
                    <input type="number" name="usage_limit_per_user" min="1" placeholder="e.g. 1" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stock Type</label>
                    <select name="stock_type" id="createStockType" onchange="toggleCreateStockQty()" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="unlimited">Unlimited</option>
                        <option value="limited">Limited</option>
                        <option value="code_pool">Code Pool</option>
                    </select>
                </div>
                <div id="createStockQtyWrapper">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stock Qty</label>
                    <input type="number" name="stock_qty" min="0" value="0" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" min="0" value="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Terms & Conditions</label>
                <textarea name="terms" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="e.g. Valid at XYZ Cafe, Pokhara branch. Cannot be combined with other offers."></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Redemption Instructions</label>
                <textarea name="redemption_instructions" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm" placeholder="How does the user claim this reward? e.g. Show this code at the counter."></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="createActive" value="1" checked class="rounded border-slate-300">
                <label for="createActive" class="text-sm text-slate-700">Active</label>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Create Item</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">Edit Reward Item</h4>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="editForm" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                    <input type="text" name="name" id="editName" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Icon</label>
                    <input type="text" name="icon" id="editIcon" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reward Type</label>
                    <select name="reward_type" id="editRewardType" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="discount">Discount</option>
                        <option value="free_item">Free Item</option>
                        <option value="voucher">Voucher</option>
                        <option value="special_offer">Special Offer</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                <textarea name="description" id="editDescription" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sponsor</label>
                    <select name="sponsor_id" id="editSponsor" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="">— No sponsor —</option>
                        @foreach($sponsors as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Price (XP)</label>
                    <input type="number" name="price_xp" id="editPrice" min="1" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Min Level</label>
                    <input type="number" name="min_level" id="editMinLevel" min="1" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Expiry (days)</label>
                    <input type="number" name="expiry_days" id="editExpiry" min="1" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Usage Limit / User</label>
                    <input type="number" name="usage_limit_per_user" id="editUsageLimit" min="1" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stock Type</label>
                    <select name="stock_type" id="editStockType" onchange="toggleEditStockQty()" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="unlimited">Unlimited</option>
                        <option value="limited">Limited</option>
                        <option value="code_pool">Code Pool</option>
                    </select>
                </div>
                <div id="editStockQtyWrapper">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stock Qty</label>
                    <input type="number" name="stock_qty" id="editStockQty" min="0" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" id="editSortOrder" min="0" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Terms & Conditions</label>
                <textarea name="terms" id="editTerms" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Redemption Instructions</label>
                <textarea name="redemption_instructions" id="editRedemption" rows="2" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_active" id="editActive" value="1" class="rounded border-slate-300">
                <label for="editActive" class="text-sm text-slate-700">Active</label>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Update Item</button>
            </div>
        </form>
    </div>
</div>

{{-- Upload Codes Modal --}}
<div id="uploadCodesModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">Upload Codes</h4>
            <button onclick="document.getElementById('uploadCodesModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="uploadCodesForm" class="space-y-4">
            @csrf
            <p class="text-sm text-slate-500">Paste one code per line.</p>
            <textarea name="codes" rows="10" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono" placeholder="CAFE20OFF-ABC123&#10;CAFE20OFF-DEF456&#10;CAFE20OFF-GHI789"></textarea>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('uploadCodesModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-purple-600 text-white rounded-lg hover:bg-purple-700">Upload</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
let items = {!! json_encode($itemsJson) !!};

function toggleCreateStockQty() {
    document.getElementById('createStockQtyWrapper').style.display =
        document.getElementById('createStockType').value === 'unlimited' ? 'none' : 'block';
}

function toggleEditStockQty() {
    document.getElementById('editStockQtyWrapper').style.display =
        document.getElementById('editStockType').value === 'unlimited' ? 'none' : 'block';
}

function openEdit(id) {
    const i = items.find(x => x.id === id);
    if (!i) return;
    document.getElementById('editForm').action = '/admin/store/items/' + id;
    document.getElementById('editName').value = i.name;
    document.getElementById('editIcon').value = i.icon;
    document.getElementById('editDescription').value = i.description || '';
    document.getElementById('editSponsor').value = i.sponsor_id || '';
    document.getElementById('editRewardType').value = i.reward_type;
    document.getElementById('editPrice').value = i.price_xp;
    document.getElementById('editMinLevel').value = i.min_level;
    document.getElementById('editStockType').value = i.stock_type;
    document.getElementById('editStockQty').value = i.stock_qty;
    document.getElementById('editSortOrder').value = i.sort_order;
    document.getElementById('editTerms').value = i.terms || '';
    document.getElementById('editExpiry').value = i.expiry_days || '';
    document.getElementById('editUsageLimit').value = i.usage_limit_per_user || '';
    document.getElementById('editRedemption').value = i.redemption_instructions || '';
    document.getElementById('editActive').checked = i.is_active;
    toggleEditStockQty();
    document.getElementById('editModal').classList.remove('hidden');
}

function openUploadCodes(itemId) {
    document.getElementById('uploadCodesForm').action = '/admin/store/items/' + itemId + '/codes';
    document.getElementById('uploadCodesModal').classList.remove('hidden');
}
</script>
@endsection
