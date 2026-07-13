@extends('admin.layout')
@section('title', 'Achievements')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Achievement & Badge Management</h3>
            <p class="text-sm text-slate-500 mt-1">Create and manage achievements, badges, and unlock criteria.</p>
        </div>
        <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Achievement
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Icon</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Criteria</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">XP Reward</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Users</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">System</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($achievements as $achievement)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 text-xl"><i class="fas fa-{{ $achievement->icon }} text-primary-600"></i></td>
                        <td class="px-6 py-4">
                            <p class="font-semibold text-slate-900">{{ $achievement->display_name }}</p>
                            <p class="text-xs text-slate-500"><code class="bg-slate-100 px-1 py-0.5 rounded">{{ $achievement->name }}</code></p>
                            @if($achievement->description)
                            <p class="text-xs text-slate-400 mt-1">{{ Str::limit($achievement->description, 60) }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded-full">{{ $achievement->category }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">{{ json_encode($achievement->criteria) }}</code>
                        </td>
                        <td class="px-6 py-4 text-center font-semibold text-primary-600">{{ $achievement->xp_reward }} XP</td>
                        <td class="px-6 py-4 text-center text-sm text-slate-600">{{ $achievement->users_count ?? 0 }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($achievement->is_system)
                            <span class="text-xs bg-primary-100 text-primary-700 px-2 py-0.5 rounded-full font-medium">System</span>
                            @else
                            <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="openEdit({{ $achievement->id }})" class="px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-600 rounded-lg hover:bg-primary-100 transition">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                @if(!$achievement->is_system)
                                <form method="POST" action="{{ route('admin.achievements.destroy', $achievement) }}" onsubmit="return confirm('Delete achievement &quot;{{ $achievement->display_name }}&quot;?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-slate-500">No achievements found. Create your first one!</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($achievements->hasPages())
        <div class="px-6 py-4 border-t border-slate-100">{{ $achievements->links() }}</div>
        @endif
    </div>
</div>

<!-- Create Modal -->
<div id="createModal" class="hidden fixed inset-0 z-50 grid place-items-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">Create Achievement</h4>
            <button onclick="document.getElementById('createModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.achievements.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Internal Key</label>
                    <input type="text" name="name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. first_report">
                    <p class="text-xs text-slate-400 mt-1">Lowercase, no spaces. Used internally.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                    <input type="text" name="display_name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. First Report">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="Submit your first report"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Icon</label>
                        <input type="text" name="icon" value="emoji_events" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="FontAwesome icon name">
                        <p class="text-xs text-slate-400 mt-1">FontAwesome icon name (e.g. star, trophy, map)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <input type="text" name="category" value="general" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. reports, alerts, level">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Criteria (JSON)</label>
                    <input type="text" name="criteria" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm font-mono text-xs" placeholder='{"type":"reports_count","value":1}'>
                    <p class="text-xs text-slate-400 mt-1">
                        Types: reports_count, approved_reports, alerts_count, reviews_count, comments_count, level_reached, critical_alerts
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">XP Reward</label>
                        <input type="number" name="xp_reward" value="0" min="0" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="0" min="0" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-xl text-sm font-semibold">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 z-50 grid place-items-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h4 id="editTitle" class="text-lg font-bold text-slate-900">Edit Achievement</h4>
            <button onclick="closeEdit()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="editForm" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Internal Key</label>
                    <input type="text" name="name" id="editName" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                    <input type="text" name="display_name" id="editDisplayName" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="editDescription" rows="2" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Icon</label>
                        <input type="text" name="icon" id="editIcon" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <input type="text" name="category" id="editCategory" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Criteria (JSON)</label>
                    <input type="text" name="criteria" id="editCriteria" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm font-mono text-xs">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">XP Reward</label>
                        <input type="number" name="xp_reward" id="editXpReward" min="0" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" id="editSortOrder" min="0" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeEdit()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-xl text-sm font-semibold">Update</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openEdit(id) {
        fetch(`/admin/achievements/${id}/edit`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('editTitle').textContent = `Edit ${data.display_name}`;
                document.getElementById('editForm').action = `/admin/achievements/${id}`;
                document.getElementById('editName').value = data.name;
                document.getElementById('editDisplayName').value = data.display_name;
                document.getElementById('editDescription').value = data.description || '';
                document.getElementById('editIcon').value = data.icon;
                document.getElementById('editCategory').value = data.category;
                document.getElementById('editCriteria').value = data.criteria ? JSON.stringify(data.criteria) : '';
                document.getElementById('editXpReward').value = data.xp_reward;
                document.getElementById('editSortOrder').value = data.sort_order;
                document.getElementById('editModal').classList.remove('hidden');
            });
    }

    function closeEdit() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
@endsection
