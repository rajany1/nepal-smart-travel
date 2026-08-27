<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permId = DB::table('permissions')->insertGetId([
            'name' => 'manage_moderation',
            'display_name' => 'Content Safety',
            'description' => 'View violations, strike history and manage user warnings/suspensions/bans',
            'group' => 'moderation',
            'menu_label' => 'Content Safety',
            'menu_icon' => 'shield-halved',
            'menu_order' => 105,
            'menu_group' => 'main',
            'route_name' => 'admin.moderation',
        ]);

        DB::table('role_has_permissions')->insertOrIgnore([
            'role_id' => DB::table('roles')->where('name', 'admin')->value('id'),
            'permission_id' => $permId,
        ]);
    }

    public function down(): void
    {
        $perm = DB::table('permissions')->where('name', 'manage_moderation')->first();
        if ($perm) {
            DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
            DB::table('permissions')->where('id', $perm->id)->delete();
        }
    }
};
