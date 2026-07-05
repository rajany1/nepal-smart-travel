<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Only administrators can manage permissions.');
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);
        $permissions = Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
        return view('admin.permissions', compact('permissions'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
            'display_name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'group' => 'required|string|max:50',
            'menu_label' => 'nullable|string|max:100',
            'menu_icon' => 'nullable|string|max:50',
            'menu_order' => 'nullable|integer|min:0',
            'route_name' => 'nullable|string|max:100',
        ]);

        $perm = Permission::create($data + ['is_system' => false]);

        $this->moderatorService->log(
            Auth::user(),
            'permission.create',
            'permission',
            $perm->id,
            "Created permission {$perm->display_name}",
        );

        return redirect()->route('admin.permissions')->with('success', "Permission '{$perm->display_name}' created.");
    }

    public function edit(Request $request, Permission $permission)
    {
        $this->requireAdmin($request);
        return response()->json($permission);
    }

    public function update(Request $request, Permission $permission)
    {
        $this->requireAdmin($request);

        if ($permission->is_system) {
            return back()->with('error', 'System permissions cannot be edited.');
        }

        $data = $request->validate([
            'display_name' => 'required|string|max:150',
            'description' => 'nullable|string|max:500',
            'group' => 'required|string|max:50',
            'menu_label' => 'nullable|string|max:100',
            'menu_icon' => 'nullable|string|max:50',
            'menu_order' => 'nullable|integer|min:0',
            'route_name' => 'nullable|string|max:100',
        ]);

        $permission->update($data);

        $this->moderatorService->log(
            Auth::user(),
            'permission.update',
            'permission',
            $permission->id,
            "Updated permission {$permission->display_name}",
        );

        return redirect()->route('admin.permissions')->with('success', "Permission '{$permission->display_name}' updated.");
    }

    public function destroy(Request $request, Permission $permission)
    {
        $this->requireAdmin($request);

        if ($permission->is_system) {
            return back()->with('error', 'System permissions cannot be deleted.');
        }

        $permission->roles()->detach();

        $this->moderatorService->log(
            Auth::user(),
            'permission.delete',
            'permission',
            $permission->id,
            "Deleted permission {$permission->display_name}",
        );

        $permission->delete();

        return redirect()->route('admin.permissions')->with('success', "Permission '{$permission->display_name}' deleted.");
    }
}
