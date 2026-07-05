<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // ========== Permissions ==========
        $permissions = [
            // Dashboard
            ['name' => 'view_dashboard', 'display_name' => 'View Dashboard', 'group' => 'system', 'description' => 'Access the admin dashboard overview', 'menu_label' => 'Dashboard', 'menu_icon' => 'chart-line', 'menu_order' => 1, 'route_name' => 'admin.dashboard'],

            // Reports
            ['name' => 'approve_reports', 'display_name' => 'Approve Reports', 'group' => 'reports', 'description' => 'Approve or reject user reports', 'menu_label' => 'Reports', 'menu_icon' => 'flag', 'menu_order' => 2, 'route_name' => 'admin.reports'],
            ['name' => 'delete_reports', 'display_name' => 'Delete Reports', 'group' => 'reports', 'description' => 'Delete reports from the system', 'route_name' => 'admin.reports'],
            ['name' => 'moderate_reviews', 'display_name' => 'Moderate Reviews', 'group' => 'reports', 'description' => 'Moderate place reviews and comments', 'route_name' => 'admin.reports'],

            // Alerts
            ['name' => 'manage_alerts', 'display_name' => 'Manage Alerts', 'group' => 'alerts', 'description' => 'Create and delete alerts', 'menu_label' => 'Alerts', 'menu_icon' => 'bell', 'menu_order' => 3, 'route_name' => 'admin.alerts'],

            // Users
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'group' => 'users', 'description' => 'View list and manage user status', 'menu_label' => 'Users', 'menu_icon' => 'users', 'menu_order' => 4, 'route_name' => 'admin.users'],
            ['name' => 'assign_moderator', 'display_name' => 'Assign Moderator', 'group' => 'users', 'description' => 'Promote or demote moderators and admins', 'route_name' => 'admin.users'],

            // Places
            ['name' => 'manage_places', 'display_name' => 'Manage Places', 'group' => 'places', 'description' => 'Create, edit, and delete places', 'menu_label' => 'Places', 'menu_icon' => 'map-marker-alt', 'menu_order' => 5, 'route_name' => 'admin.places'],

            // Achievements
            ['name' => 'manage_achievements', 'display_name' => 'Manage Achievements', 'group' => 'system', 'description' => 'View and manage achievements and user progress', 'menu_label' => 'Achievements', 'menu_icon' => 'trophy', 'menu_order' => 6, 'route_name' => 'admin.achievements'],

            // Live Map
            ['name' => 'view_live_map', 'display_name' => 'View Live Map', 'group' => 'system', 'description' => 'View all resources on live map', 'menu_label' => 'Live Map', 'menu_icon' => 'globe-asia', 'menu_order' => 2, 'route_name' => 'admin.live-map'],

            // Settings
            ['name' => 'view_analytics', 'display_name' => 'View Analytics', 'group' => 'system', 'description' => 'View dashboard analytics and settings', 'menu_label' => 'Settings', 'menu_icon' => 'cogs', 'menu_order' => 7, 'route_name' => 'admin.settings'],

            // System (no nav menu)
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles', 'group' => 'system', 'description' => 'Create, edit, and delete roles', 'route_name' => 'admin.roles'],
            ['name' => 'manage_permissions', 'display_name' => 'Manage Permissions', 'group' => 'system', 'description' => 'Create, edit, and delete permissions', 'route_name' => 'admin.permissions'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(
                ['name' => $p['name']],
                $p + ['is_system' => true],
            );
        }

        // ========== Roles ==========
        $roles = [
            'admin' => [
                'display_name' => 'Administrator',
                'description' => 'Full system access to all features',
                'is_system' => true,
                'is_default' => false,
                'permissions' => Permission::pluck('name')->toArray(),
            ],
            'moderator' => [
                'display_name' => 'Moderator',
                'description' => 'Content moderator with granular permissions',
                'is_system' => true,
                'is_default' => false,
                'permissions' => [
                    'view_dashboard', 'view_live_map', 'approve_reports', 'delete_reports',
                    'manage_places', 'manage_alerts', 'manage_users',
                    'view_analytics',
                ],
            ],
            'user' => [
                'display_name' => 'User',
                'description' => 'Regular platform user',
                'is_system' => true,
                'is_default' => true,
                'permissions' => [],
            ],
        ];

        foreach ($roles as $name => $data) {
            $permissionsList = $data['permissions'];
            unset($data['permissions']);

            $role = Role::updateOrCreate(
                ['name' => $name],
                $data,
            );

            $permIds = Permission::whereIn('name', $permissionsList)->pluck('id');
            $role->permissions()->sync($permIds);
        }
    }
}
