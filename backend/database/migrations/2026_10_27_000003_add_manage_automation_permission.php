<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'manage_automation',
            'display_name' => 'Automation & Queues',
            'description' => 'See queue workers, scheduled commands and control which automation tasks run',
            'group' => 'system',
            'menu_label' => 'Automation',
            'menu_icon' => 'gears',
            'menu_order' => 106,
            'menu_group' => 'system',
            'route_name' => 'admin.automation',
        ]);

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'super_admin'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('name', 'manage_automation')->first();
        if (!$permission) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
        DB::table('permissions')->where('id', $permission->id)->delete();
    }
};
