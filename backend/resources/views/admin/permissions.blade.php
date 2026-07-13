@extends('admin.layout')
@section('title', 'Permission Management')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Permission Management</h3>
            <p class="text-sm text-slate-500 mt-1">Define granular permissions that can be assigned to roles.</p>
        </div>
        <button onclick="document.getElementById('createPermModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Permission
        </button>
    </div>

    @foreach($permissions as $group => $perms)
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
        <div class="px-5 py-3 border-b border-slate-100">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">{{ $group }}</h4>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach($perms as $perm)
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ $perm->display_name }}</p>
                    <p class="text-xs text-slate-500">
                        <code class="bg-slate-100 px-1.5 py-0.5 rounded">{{ $perm->name }}</code>
                        @if($perm->description) &middot; {{ $perm->description }} @endif
                        @if($perm->is_system) <span class="ml-1 text-primary-500 font-semibold">System</span> @endif
                        @if($perm->menu_label)
                            &middot; <span class="text-green-600"><i class="fas fa-{{ $perm->menu_icon }}"></i> {{ $perm->menu_label }}</span>
                        @endif
                        @if($perm->route_name)
                            &middot; <code class="text-green-600">{{ $perm->route_name }}</code>
                        @endif
                    </p>
                </div>
                @if(!$perm->is_system)
                <div class="flex gap-2">
                    <button onclick="openEditPerm({{ $perm->id }})" class="text-sm text-primary-600 hover:text-primary-800 font-medium"><i class="fas fa-edit text-xs"></i> Edit</button>
                    <form method="POST" action="{{ route('admin.permissions.destroy', $perm) }}" onsubmit="return confirm('Delete permission {{ $perm->display_name }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium"><i class="fas fa-trash text-xs"></i> Delete</button>
                    </form>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
</div>

<!-- Create Modal -->
<div id="createPermModal" class="hidden fixed inset-0 z-50 grid place-items-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">Create New Permission</h4>
            <button onclick="document.getElementById('createPermModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.permissions.store') }}">
            @csrf
            <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Permission Key</label>
                        <input type="text" name="name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. manage_reports">
                        <p class="text-xs text-slate-400 mt-1">Lowercase, underscores. Used internally.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                        <input type="text" name="display_name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. Manage Reports">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Group</label>
                        <input type="text" name="group" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. reports" value="general">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <textarea name="description" rows="2" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
                    </div>
                    <hr class="border-slate-200">
                    <p class="text-sm font-semibold text-slate-700">Sidebar & Access Control</p>
                    <p class="text-xs text-slate-400 -mt-3">If you want this permission to add a sidebar link, fill below.</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Menu Label</label>
                            <input type="text" name="menu_label" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. Achievements">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Menu Icon</label>
                            <input type="text" name="menu_icon" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. trophy" value="circle">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Route Name</label>
                            <input type="text" name="route_name" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. admin.achievements">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Menu Order</label>
                            <input type="number" name="menu_order" value="0" min="0" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                        </div>
                    </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="document.getElementById('createPermModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-xl text-sm font-semibold">Create Permission</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editPermModal" class="hidden fixed inset-0 z-50 grid place-items-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-4">
            <h4 id="editPermTitle" class="text-lg font-bold text-slate-900">Edit Permission</h4>
            <button onclick="document.getElementById('editPermModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="editPermForm" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                    <input type="text" name="display_name" id="editPermDisplayName" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Group</label>
                    <input type="text" name="group" id="editPermGroup" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="editPermDescription" rows="2" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
                </div>
                <hr class="border-slate-200">
                <p class="text-sm font-semibold text-slate-700">Sidebar & Access Control</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Menu Label</label>
                        <input type="text" name="menu_label" id="editPermMenuLabel" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Menu Icon</label>
                        <input type="text" name="menu_icon" id="editPermMenuIcon" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Route Name</label>
                        <input type="text" name="route_name" id="editPermRouteName" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Menu Order</label>
                        <input type="number" name="menu_order" id="editPermMenuOrder" min="0" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="document.getElementById('editPermModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-xl text-sm font-semibold">Update Permission</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function openEditPerm(id) {
        fetch(`/admin/permissions/${id}/edit`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('editPermTitle').textContent = `Edit ${data.display_name}`;
                document.getElementById('editPermForm').action = `/admin/permissions/${data.id}`;
                document.getElementById('editPermDisplayName').value = data.display_name;
                document.getElementById('editPermGroup').value = data.group;
                document.getElementById('editPermDescription').value = data.description || '';
                document.getElementById('editPermMenuLabel').value = data.menu_label || '';
                document.getElementById('editPermMenuIcon').value = data.menu_icon || '';
                document.getElementById('editPermRouteName').value = data.route_name || '';
                document.getElementById('editPermMenuOrder').value = data.menu_order || 0;
                document.getElementById('editPermModal').classList.remove('hidden');
            });
    }
</script>
@endsection
