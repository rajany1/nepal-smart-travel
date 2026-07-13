@extends('admin.layout')
@section('title', 'Role Management')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-slate-900">Role Management</h3>
            <p class="text-sm text-slate-500 mt-1">Define custom roles and assign permissions to control access.</p>
        </div>
        <button onclick="document.getElementById('createRoleModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2">
            <i class="fas fa-plus"></i> New Role
        </button>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @foreach($roles as $role)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 hover:shadow-md transition">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h4 class="text-lg font-bold text-slate-900">{{ $role->display_name }}</h4>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">{{ $role->name }}</code>
                        @if($role->is_system) <span class="ml-1 text-primary-500 font-semibold">System</span> @endif
                        @if($role->is_default) <span class="ml-1 text-green-500 font-semibold">Default</span> @endif
                    </p>
                </div>
                <span class="text-xs bg-slate-100 text-slate-600 rounded-full px-2.5 py-1 font-medium">
                    {{ $role->users_count ?? $role->users()->count() }} users
                </span>
            </div>

            @if($role->description)
            <p class="text-sm text-slate-600 mb-3">{{ $role->description }}</p>
            @endif

            <div class="mb-4">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Permissions</p>
                <div class="flex flex-wrap gap-1.5">
                    @forelse($role->permissions as $perm)
                    <span class="text-xs bg-primary-50 text-primary-700 px-2 py-0.5 rounded-full">{{ $perm->display_name }}</span>
                    @empty
                    <span class="text-xs text-slate-400 italic">No permissions assigned</span>
                    @endforelse
                </div>
            </div>

            <div class="flex gap-2 pt-3 border-t border-slate-100">
                <button onclick="openEditRole({{ $role->id }})" class="text-sm text-primary-600 hover:text-primary-800 font-medium flex items-center gap-1">
                    <i class="fas fa-edit text-xs"></i> Edit
                </button>
                @if(!$role->is_system)
                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete role {{ $role->display_name }}?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium flex items-center gap-1">
                        <i class="fas fa-trash text-xs"></i> Delete
                    </button>
                </form>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

<!-- Create Role Modal -->
<div id="createRoleModal" class="hidden fixed inset-0 z-50 grid place-items-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-lg font-bold text-slate-900">Create New Role</h4>
            <button onclick="document.getElementById('createRoleModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Role Key</label>
                    <input type="text" name="name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. editor">
                    <p class="text-xs text-slate-400 mt-1">Lowercase, no spaces. Used internally.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                    <input type="text" name="display_name" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. Editor">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="What can this role do?"></textarea>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="document.getElementById('createRoleModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-xl text-sm font-semibold">Create Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" class="hidden fixed inset-0 z-50 grid place-items-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h4 id="editRoleTitle" class="text-lg font-bold text-slate-900">Edit Role</h4>
            <button onclick="closeEditRole()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="editRoleForm" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Display Name</label>
                        <input type="text" name="display_name" id="editDisplayName" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default for new users</label>
                        <select name="is_default" id="editIsDefault" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="editDescription" rows="2" class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-700 mb-3">Permissions</p>
                    @foreach($permissionGroups as $group => $perms)
                    <div class="mb-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ $group }}</p>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($perms as $perm)
                            <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input type="checkbox" name="permissions[]" value="{{ $perm->name }}" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500 edit-perm-checkbox">
                                {{ $perm->display_name }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button type="button" onclick="closeEditRole()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-xl text-sm font-semibold">Update Role</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const rolePermissions = @json($rolePermissions);

    function openEditRole(roleId) {
        // Find role card data from DOM
        const modal = document.getElementById('editRoleModal');
        const form = document.getElementById('editRoleForm');
        form.action = `/admin/roles/${roleId}`;

        // Fetch role data via AJAX
        fetch(`/admin/roles/${roleId}/edit`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('editRoleTitle').textContent = `Edit ${data.display_name}`;
                document.getElementById('editDisplayName').value = data.display_name;
                document.getElementById('editDescription').value = data.description || '';
                document.getElementById('editIsDefault').value = data.is_default ? '1' : '0';

                // Check assigned permissions
                const assigned = rolePermissions[data.id] || [];
                document.querySelectorAll('.edit-perm-checkbox').forEach(cb => {
                    cb.checked = assigned.includes(cb.value);
                });

                modal.classList.remove('hidden');
            });
    }

    function closeEditRole() {
        document.getElementById('editRoleModal').classList.add('hidden');
    }
</script>
@endsection
