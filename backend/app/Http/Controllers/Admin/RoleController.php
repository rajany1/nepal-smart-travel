<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only administrators can manage roles.');
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);
        $roles = Role::with('permissions')->withCount('users')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        $permissionGroups = $permissions->groupBy('group');

        $rolePermissions = Role::pluck('id')->mapWithKeys(function ($id) {
            return [$id => Role::find($id)?->permissions->pluck('name')->toArray() ?? []];
        });

        return view('admin.roles', compact('roles', 'permissions', 'permissionGroups', 'rolePermissions'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $role = Role::create($data + ['is_system' => false]);

        $this->moderatorService->log(
            Auth::user(),
            'role.create',
            'role',
            $role->id,
            "Created role {$role->display_name}",
        );

        return redirect()->route('admin.roles')->with('success', "Role '{$role->display_name}' created.");
    }

    public function edit(Request $request, Role $role)
    {
        $this->requireAdmin($request);
        return response()->json($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_default' => 'boolean',
        ]);

        $role->update($data);

        if ($request->has('permissions')) {
            $permIds = Permission::whereIn('name', $request->input('permissions', []))->pluck('id');
            $role->permissions()->sync($permIds);
        } else {
            $role->permissions()->sync([]);
        }

        $this->moderatorService->log(
            Auth::user(),
            'role.update',
            'role',
            $role->id,
            "Updated role {$role->display_name}",
        );

        return redirect()->route('admin.roles')->with('success', "Role '{$role->display_name}' updated.");
    }

    public function destroy(Request $request, Role $role)
    {
        $this->requireAdmin($request);

        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'Cannot delete a role that has users assigned.');
        }

        $this->moderatorService->log(
            Auth::user(),
            'role.delete',
            'role',
            $role->id,
            "Deleted role {$role->display_name}",
        );

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('admin.roles')->with('success', "Role '{$role->display_name}' deleted.");
    }
}
