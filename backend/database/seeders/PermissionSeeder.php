<?php

namespace Database\Seeders;

use App\Models\ModeratorPermission;
use App\Models\User;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $moderators = User::where('role', 'moderator')->get();

        $defaultPermissions = [
            'approve_reports',
            'delete_reports',
            'manage_places',
            'manage_alerts',
            'manage_users',
            'view_analytics',
        ];

        foreach ($moderators as $moderator) {
            foreach ($defaultPermissions as $permission) {
                ModeratorPermission::firstOrCreate([
                    'user_id' => $moderator->id,
                    'permission' => $permission,
                ]);
            }
            $this->command->info("Assigned default permissions to moderator #{$moderator->id} ({$moderator->name})");
        }

        if ($moderators->count() === 0) {
            $this->command->info('No moderators found. Run UserSeeder first or promote a user to moderator.');
        }
    }
}
