<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * One-shot, idempotent repair for the admin sidebar.
 *
 * The admin sidebar (resources/views/admin/layout.blade.php) is 100% DB-driven:
 * it renders Permission rows that have menu_label + route_name + menu_icon set.
 * If the `permissions` table gets corrupted (rows dropped / menu fields nulled)
 * the "dynamic" menu items silently disappear, forcing admins to type URLs.
 *
 * This seeder rebuilds every admin menu entry with the correct fields and
 * re-syncs the admin role, so the sidebar always shows the full set of pages
 * the app actually exposes.
 *
 * Safe to re-run any number of times (updateOrCreate + sync).
 */
class AdminMenuSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // ===== MAIN =====
            ['name' => 'view_dashboard', 'display_name' => 'View Dashboard', 'description' => 'Access the admin dashboard overview', 'group' => 'system', 'menu_label' => 'Dashboard', 'menu_icon' => 'chart-line', 'menu_order' => 1, 'menu_group' => 'main', 'route_name' => 'admin.dashboard'],
            ['name' => 'view_live_map', 'display_name' => 'View Live Map', 'description' => 'View all resources on the live map', 'group' => 'system', 'menu_label' => 'Live Map', 'menu_icon' => 'globe-asia', 'menu_order' => 2, 'menu_group' => 'main', 'route_name' => 'admin.live-map'],
            ['name' => 'approve_reports', 'display_name' => 'Approve Reports', 'description' => 'Approve or reject user reports', 'group' => 'reports', 'menu_label' => 'Reports', 'menu_icon' => 'flag', 'menu_order' => 3, 'menu_group' => 'main', 'route_name' => 'admin.reports'],
            ['name' => 'manage_alerts', 'display_name' => 'Manage Alerts', 'description' => 'Create and delete alerts', 'group' => 'alerts', 'menu_label' => 'Alerts', 'menu_icon' => 'bell', 'menu_order' => 4, 'menu_group' => 'main', 'route_name' => 'admin.alerts'],
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'description' => 'View list and manage user status', 'group' => 'users', 'menu_label' => 'Users', 'menu_icon' => 'users', 'menu_order' => 5, 'menu_group' => 'main', 'route_name' => 'admin.users'],
            ['name' => 'manage_places', 'display_name' => 'Manage Places', 'description' => 'Create, edit, and delete places', 'group' => 'places', 'menu_label' => 'Places', 'menu_icon' => 'map-marker-alt', 'menu_order' => 6, 'menu_group' => 'main', 'route_name' => 'admin.places'],
            ['name' => 'view_osm_places', 'display_name' => 'Places (OSM)', 'description' => 'Browse imported OpenStreetMap places', 'group' => 'places', 'menu_label' => 'Places (OSM)', 'menu_icon' => 'map', 'menu_order' => 7, 'menu_group' => 'main', 'route_name' => 'admin.places.osm'],
            ['name' => 'manage_place_corrections', 'display_name' => 'Place Corrections', 'description' => 'Review and apply user-submitted place corrections', 'group' => 'places', 'menu_label' => 'Place Corrections', 'menu_icon' => 'edit', 'menu_order' => 8, 'menu_group' => 'main', 'route_name' => 'admin.places.corrections'],
            ['name' => 'manage_achievements', 'display_name' => 'Manage Achievements', 'description' => 'View and manage achievements and user progress', 'group' => 'system', 'menu_label' => 'Achievements', 'menu_icon' => 'trophy', 'menu_order' => 9, 'menu_group' => 'main', 'route_name' => 'admin.achievements'],
            ['name' => 'view_analytics', 'display_name' => 'View Analytics', 'description' => 'View dashboard analytics and platform settings', 'group' => 'system', 'menu_label' => 'Settings', 'menu_icon' => 'cogs', 'menu_order' => 10, 'menu_group' => 'main', 'route_name' => 'admin.settings'],
            ['name' => 'manage_moderation', 'display_name' => 'Content Safety', 'description' => 'View violations, strike history and manage user warnings/suspensions/bans', 'group' => 'moderation', 'menu_label' => 'Content Safety', 'menu_icon' => 'shield-halved', 'menu_order' => 11, 'menu_group' => 'main', 'route_name' => 'admin.moderation'],
            ['name' => 'manage_curated_routes', 'display_name' => 'Manage Curated Routes', 'description' => 'Create and edit curated routes for the public website', 'group' => 'website', 'menu_label' => 'Routes', 'menu_icon' => 'route', 'menu_order' => 12, 'menu_group' => 'main', 'route_name' => 'admin.routes'],
            ['name' => 'manage_travel_partners', 'display_name' => 'Business Verification', 'description' => 'Verify business partner registrations and view partners', 'group' => 'partners', 'menu_label' => 'Travel Partners', 'menu_icon' => 'store', 'menu_order' => 13, 'menu_group' => 'main', 'route_name' => 'admin.travel-partners'],
            ['name' => 'manage_bookings', 'display_name' => 'Manage Bookings', 'description' => 'View and manage travel partner bookings', 'group' => 'partners', 'menu_label' => 'Bookings', 'menu_icon' => 'calendar-check', 'menu_order' => 14, 'menu_group' => 'main', 'route_name' => 'admin.bookings'],
            ['name' => 'view_audit_logs', 'display_name' => 'View Audit Logs', 'description' => 'View system audit log history', 'group' => 'system', 'menu_label' => 'Audit Logs', 'menu_icon' => 'history', 'menu_order' => 15, 'menu_group' => 'main', 'route_name' => 'admin.audit-logs'],
        ];

        $permissions = array_merge($permissions, [
            // ===== MONETIZATION =====
            ['name' => 'manage_offers', 'display_name' => 'Manage Offers', 'description' => 'Approve, reject, or pause business reward offers', 'group' => 'monetization', 'menu_label' => 'Offers', 'menu_icon' => 'gift', 'menu_order' => 20, 'menu_group' => 'monetization', 'route_name' => 'admin.offers'],
            ['name' => 'manage_payouts', 'display_name' => 'Manage Payouts', 'description' => 'View and process partner payout requests', 'group' => 'monetization', 'menu_label' => 'Payouts', 'menu_icon' => 'hand-holding-usd', 'menu_order' => 21, 'menu_group' => 'monetization', 'route_name' => 'admin.payouts'],

            // ===== STORE =====
            ['name' => 'manage_subscription_plans', 'display_name' => 'Subscription Plans', 'description' => 'Manage subscription plan pricing and features', 'group' => 'store', 'menu_label' => 'Subscription Plans', 'menu_icon' => 'tags', 'menu_order' => 30, 'menu_group' => 'store', 'route_name' => 'admin.subscription.plans'],
            ['name' => 'manage_user_subscriptions', 'display_name' => 'User Subscriptions', 'description' => 'View and manage user subscriptions', 'group' => 'store', 'menu_label' => 'User Subscriptions', 'menu_icon' => 'id-card', 'menu_order' => 31, 'menu_group' => 'store', 'route_name' => 'admin.subscription.users'],
            ['name' => 'manage_ad_campaigns', 'display_name' => 'Ad Campaigns', 'description' => 'Approve, reject, pause and manage ad campaigns', 'group' => 'store', 'menu_label' => 'Ad Campaigns', 'menu_icon' => 'bullhorn', 'menu_order' => 32, 'menu_group' => 'store', 'route_name' => 'admin.ad-campaigns'],

            // ===== ACCESS (admin_only) =====
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles', 'description' => 'Create, edit, and delete roles', 'group' => 'system', 'menu_label' => 'Roles', 'menu_icon' => 'user-shield', 'menu_order' => 40, 'menu_group' => 'access', 'route_name' => 'admin.roles'],
            ['name' => 'manage_permissions', 'display_name' => 'Manage Permissions', 'description' => 'Create, edit, and delete permissions', 'group' => 'system', 'menu_label' => 'Permissions', 'menu_icon' => 'key', 'menu_order' => 41, 'menu_group' => 'access', 'route_name' => 'admin.permissions'],
        ]);

        foreach ($permissions as $p) {
            Permission::updateOrCreate(
                ['name' => $p['name']],
                $p + ['is_system' => true],
            );
        }

        // Admin role -> every permission (granted to the pivot for consistency).
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->sync(Permission::pluck('id')->toArray());
        }

        // Moderator -> keep the same subset the original seeder granted.
        $moderatorRole = Role::where('name', 'moderator')->first();
        if ($moderatorRole) {
            $modPerms = Permission::whereIn('name', [
                'view_dashboard', 'view_live_map', 'approve_reports', 'delete_reports',
                'manage_places', 'manage_alerts', 'manage_users', 'view_analytics',
            ])->pluck('id')->toArray();
            $moderatorRole->permissions()->sync($modPerms);
        }

        $this->command->info('Admin sidebar menus rebuilt (' . count($permissions) . ' entries) and roles re-synced.');
    }
}
