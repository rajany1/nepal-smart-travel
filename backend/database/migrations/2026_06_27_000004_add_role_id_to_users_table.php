<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure default roles exist before migrating data
        $roles = [
            ['name' => 'user', 'display_name' => 'User', 'description' => 'Regular platform user', 'is_system' => true, 'is_default' => true],
            ['name' => 'moderator', 'display_name' => 'Moderator', 'description' => 'Content moderator with granular permissions', 'is_system' => true, 'is_default' => false],
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Full system access', 'is_system' => true, 'is_default' => false],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                $role
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Migrate existing role strings to role_id
        DB::statement('UPDATE users SET role_id = (SELECT id FROM roles WHERE roles.name = users.role)');

        // Make role_id not nullable now that data is migrated
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('id');
        });

        DB::statement('UPDATE users SET role = (SELECT name FROM roles WHERE roles.id = users.role_id)');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
