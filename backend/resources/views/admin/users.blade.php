@extends('admin.layout')
@section('title', 'Users Management')

@section('content')
@php
    $currentUser = Auth::user();
    $canManageUsers = $currentUser->hasPermission('manage_users');
    $canAssignModerator = $currentUser->hasPermission('assign_moderator');
@endphp
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
        <h3 class="font-semibold text-gray-800">All Users</h3>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.users', ['role' => 'all', 'status' => $status]) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $role === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
            <a href="{{ route('admin.users', ['role' => 'admin', 'status' => $status]) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $role === 'admin' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Admins</a>
            <a href="{{ route('admin.users', ['role' => 'moderator', 'status' => $status]) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $role === 'moderator' ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Moderators</a>
            <a href="{{ route('admin.users', ['role' => 'user', 'status' => $status]) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $role === 'user' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Users</a>
            @if(!$currentUser->isModerator())
            <a href="{{ route('admin.roles') }}" class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"><i class="fas fa-user-tag"></i> Roles</a>
            <a href="{{ route('admin.permissions') }}" class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"><i class="fas fa-key"></i> Permissions</a>
            @endif
        </div>
    </div>
    <div class="px-6 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
        <div class="flex gap-2">
            <a href="{{ route('admin.users', ['status' => 'all', 'role' => $role]) }}" class="px-2.5 py-1 text-xs rounded-lg {{ ($status ?? 'all') === 'all' ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
            <a href="{{ route('admin.users', ['status' => 'active', 'role' => $role]) }}" class="px-2.5 py-1 text-xs rounded-lg {{ $status === 'active' ? 'bg-green-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Active</a>
            <a href="{{ route('admin.users', ['status' => 'suspended', 'role' => $role]) }}" class="px-2.5 py-1 text-xs rounded-lg {{ $status === 'suspended' ? 'bg-red-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Suspended</a>
            <a href="{{ route('admin.users', ['status' => 'banned', 'role' => $role]) }}" class="px-2.5 py-1 text-xs rounded-lg {{ $status === 'banned' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Banned</a>
        </div>
        <form method="GET" action="{{ route('admin.users') }}" class="ml-auto flex gap-2">
            <input type="hidden" name="role" value="{{ $role }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or email..." class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 w-64" />
            <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Phone</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">XP</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Joined</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-500">#{{ $user->id }}</td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-indigo-600">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ $user->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $user->email }}</td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $user->phone ?? '-' }}</td>
                    <td class="px-6 py-4">
                        @php
                            $roleName = $user->role?->name ?? 'user';
                            $roleClasses = [
                                'admin' => 'bg-purple-100 text-purple-800',
                                'moderator' => 'bg-yellow-100 text-yellow-800',
                                'user' => 'bg-gray-100 text-gray-700',
                            ];
                        @endphp
                        @if($canAssignModerator && $user->id !== $currentUser->id)
                            <form method="POST" action="{{ route('admin.users.assign-role', $user->id) }}" class="inline-flex items-center gap-1" onsubmit="return confirm('Change role for {{ $user->name }}?');">
                                @csrf
                                <select name="role_id" onchange="this.form.submit()" class="text-xs font-medium px-2 py-1.5 rounded border border-gray-200 {{ $roleClasses[$roleName] ?? 'bg-gray-100 text-gray-700' }}">
                                    @foreach($roles as $r)
                                    <option value="{{ $r->id }}" {{ $r->name === $roleName ? 'selected' : '' }}>{{ ucfirst($r->display_name) }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @if($user->role && $user->role->permissions->count() > 0)
                            <div class="relative inline-block group mt-1">
                                <i class="fas fa-info-circle text-xs text-gray-400 cursor-help"></i>
                                <div class="absolute left-0 bottom-full mb-1 hidden group-hover:block z-10 w-56 bg-gray-900 text-white text-xs rounded-lg p-2 shadow-lg">
                                    <p class="font-semibold mb-1">{{ $user->role->display_name }} Permissions:</p>
                                    @foreach($user->role->permissions as $perm)
                                    <span class="block">{{ $perm->display_name }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        @else
                            <span class="text-xs font-medium px-2 py-1 rounded {{ $roleClasses[$roleName] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($user->role?->display_name ?? $roleName) }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ number_format($user->total_xp ?? 0) }}</td>
                    <td class="px-6 py-4">
                        @php
                            $statusClasses = [
                                'active' => 'bg-green-100 text-green-800',
                                'suspended' => 'bg-red-100 text-red-800',
                                'banned' => 'bg-gray-800 text-white',
                            ];
                            $statusIcons = [
                                'active' => 'fa-check-circle',
                                'suspended' => 'fa-ban',
                                'banned' => 'fa-skull',
                            ];
                        @endphp
                        <span class="text-xs font-medium px-2 py-1 rounded-full inline-flex items-center gap-1 {{ $statusClasses[$user->status] ?? 'bg-gray-100 text-gray-800' }}">
                            <i class="fas {{ $statusIcons[$user->status] ?? 'fa-circle' }}"></i>
                            {{ ucfirst($user->status ?? 'active') }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('admin.users.progress', $user) }}" class="px-2 py-1 text-xs rounded bg-blue-50 text-blue-600 hover:bg-blue-100" title="View progress">
                                <i class="fas fa-trophy"></i>
                            </a>
                            @if($canManageUsers && $user->id !== $currentUser->id)
                            <form method="POST" action="{{ route('admin.users.toggle-status', $user->id) }}" class="inline" onsubmit="return confirm('Change status for {{ $user->name }}? Current: {{ $user->status }}. Next: {{ $user->status === 'active' ? 'suspended' : ($user->status === 'suspended' ? 'banned' : 'active') }}.');">
                                @csrf
                                <button type="submit" class="px-2 py-1 text-xs rounded {{ $user->status === 'active' ? 'bg-red-50 text-red-600 hover:bg-red-100' : ($user->status === 'banned' ? 'bg-green-50 text-green-600 hover:bg-green-100' : 'bg-orange-50 text-orange-600 hover:bg-orange-100') }}" title="Toggle status">
                                    <i class="fas {{ $user->status === 'active' ? 'fa-ban' : ($user->status === 'banned' ? 'fa-check' : 'fa-step-forward') }}"></i>
                                </button>
                            </form>
                            @endif

                            @php $roleName = $user->role?->name ?? 'user'; @endphp
                            @if($roleName === 'admin')
                                @if($canAssignModerator && $user->id !== $currentUser->id)
                                <form method="POST" action="{{ route('admin.users.remove-admin', $user->id) }}" class="inline" onsubmit="return confirm('Remove admin privileges from {{ $user->name }}?');">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 text-xs rounded bg-orange-50 text-orange-600 hover:bg-orange-100" title="Remove admin">
                                        <i class="fas fa-user"></i>
                                    </button>
                                </form>
                                @endif
                            @elseif($roleName === 'moderator')
                                @if($canAssignModerator)
                                <form method="POST" action="{{ route('admin.users.make-admin', $user->id) }}" class="inline" onsubmit="return confirm('Promote {{ $user->name }} to admin?');">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 text-xs rounded bg-purple-50 text-purple-600 hover:bg-purple-100" title="Promote to admin">
                                        <i class="fas fa-crown"></i>
                                    </button>
                                </form>
                                @endif
                                @if($canAssignModerator)
                                <form method="POST" action="{{ route('admin.users.remove-moderator', $user->id) }}" class="inline" onsubmit="return confirm('Remove moderator privileges from {{ $user->name }}?');">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 text-xs rounded bg-yellow-50 text-yellow-800 hover:bg-yellow-100" title="Remove moderator">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                </form>
                                @endif
                            @else
                                @if($canAssignModerator)
                                <form method="POST" action="{{ route('admin.users.make-admin', $user->id) }}" class="inline" onsubmit="return confirm('Promote {{ $user->name }} to admin?');">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 text-xs rounded bg-purple-50 text-purple-600 hover:bg-purple-100" title="Promote to admin">
                                        <i class="fas fa-crown"></i>
                                    </button>
                                </form>
                                @endif
                                @if($canAssignModerator)
                                <form method="POST" action="{{ route('admin.users.make-moderator', $user->id) }}" class="inline" onsubmit="return confirm('Promote {{ $user->name }} to moderator?');">
                                    @csrf
                                    <button type="submit" class="px-2 py-1 text-xs rounded bg-yellow-50 text-yellow-800 hover:bg-yellow-100" title="Promote to moderator">
                                        <i class="fas fa-user-shield"></i>
                                    </button>
                                </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-users text-3xl text-gray-300 mb-3 block"></i>
                        No users found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $users->appends(['role' => $role, 'status' => $status, 'search' => request('search')])->links() }}
    </div>
    @endif
</div>

<!-- Moderator Permissions (Read-only display) -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Moderator Permissions</h3>
        <p class="text-sm text-gray-500 mt-1">Current permissions for each moderator based on their assigned role.</p>
    </div>
    <div class="px-6 py-4">
        <div class="rounded-lg bg-yellow-50 border border-yellow-200 p-3 mb-4 text-sm text-yellow-800 flex items-start gap-2">
            <i class="fas fa-info-circle mt-0.5"></i>
            <span>Moderator permissions are controlled via the role assigned to each user. To change permissions, go to <a href="{{ route('admin.roles') }}" class="underline font-semibold hover:text-yellow-900">Roles</a> and edit the moderator role.</span>
        </div>
        @forelse($moderators as $mod)
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 mb-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <span class="h-10 w-10 rounded-xl bg-amber-600 grid place-items-center text-white font-bold">{{ strtoupper(substr($mod->name, 0, 1)) }}</span>
                    <div>
                        <p class="font-semibold text-slate-900">{{ $mod->name }}</p>
                        <p class="text-xs text-slate-500">{{ $mod->email }}</p>
                    </div>
                </div>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Moderator</span>
            </div>

            <div>
                <p class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wider">Role: {{ $mod->role?->display_name ?? $mod->role?->name ?? 'None' }}</p>
                <div class="flex flex-wrap gap-2">
                    @php $modPerms = $mod->role?->permissions ?? collect(); @endphp
                    @forelse($modPerms as $perm)
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-1 text-xs font-medium text-indigo-700">
                            <i class="fas fa-check-circle text-indigo-400"></i>
                            {{ $perm->display_name }}
                        </span>
                    @empty
                        <span class="text-xs text-slate-400">No permissions assigned</span>
                    @endforelse
                </div>
            </div>
        </div>
        @empty
            <div class="text-center py-10 text-slate-500">
                <i class="fas fa-user-shield text-3xl text-slate-300 mb-3 block"></i>
                <p>No moderators found. Promote a user to moderator to assign permissions.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection
