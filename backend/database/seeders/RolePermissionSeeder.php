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
            ['name' => 'view_dashboard', 'display_name' => 'View Dashboard', 'group' => 'system', 'description' => 'Access the admin dashboard overview', 'menu_label' => 'Dashboard', 'menu_icon' => 'chart-line', 'menu_order' => 1, 'route_name' => 'admin.dashboard', 'menu_group' => 'main'],

            // Live Map
            ['name' => 'view_live_map', 'display_name' => 'View Live Map', 'group' => 'system', 'description' => 'View all resources on live map', 'menu_label' => 'Live Map', 'menu_icon' => 'globe-asia', 'menu_order' => 2, 'route_name' => 'admin.live-map', 'menu_group' => 'main'],

            // Reports
            ['name' => 'approve_reports', 'display_name' => 'Approve Reports', 'group' => 'reports', 'description' => 'Approve or reject user reports', 'menu_label' => 'Reports', 'menu_icon' => 'flag', 'menu_order' => 3, 'route_name' => 'admin.reports', 'menu_group' => 'main'],
            ['name' => 'delete_reports', 'display_name' => 'Delete Reports', 'group' => 'reports', 'description' => 'Delete reports from the system', 'route_name' => 'admin.reports'],
            ['name' => 'moderate_reviews', 'display_name' => 'Moderate Reviews', 'group' => 'reports', 'description' => 'Moderate place reviews and comments', 'route_name' => 'admin.reports'],

            // Alerts
            ['name' => 'manage_alerts', 'display_name' => 'Manage Alerts', 'group' => 'alerts', 'description' => 'Create and delete alerts', 'menu_label' => 'Alerts', 'menu_icon' => 'bell', 'menu_order' => 4, 'route_name' => 'admin.alerts', 'menu_group' => 'main'],

            // Users
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'group' => 'users', 'description' => 'View list and manage user status', 'menu_label' => 'Users', 'menu_icon' => 'users', 'menu_order' => 5, 'route_name' => 'admin.users', 'menu_group' => 'main'],
            ['name' => 'assign_moderator', 'display_name' => 'Assign Moderator', 'group' => 'users', 'description' => 'Promote or demote moderators and admins', 'route_name' => 'admin.users'],

            // Places
            ['name' => 'manage_places', 'display_name' => 'Manage Places', 'group' => 'places', 'description' => 'Create, edit, and delete places', 'menu_label' => 'Places', 'menu_icon' => 'map-marker-alt', 'menu_order' => 6, 'route_name' => 'admin.places', 'menu_group' => 'main'],

            // Achievements
            ['name' => 'manage_achievements', 'display_name' => 'Manage Achievements', 'group' => 'system', 'description' => 'View and manage achievements and user progress', 'menu_label' => 'Achievements', 'menu_icon' => 'trophy', 'menu_order' => 7, 'route_name' => 'admin.achievements', 'menu_group' => 'main'],

            // Content Safety
            ['name' => 'manage_content_safety', 'display_name' => 'Content Safety', 'group' => 'moderation', 'description' => 'Review AI agent reports and moderate content', 'menu_label' => 'Content Safety', 'menu_icon' => 'shield-alt', 'menu_order' => 8, 'route_name' => 'admin.moderation', 'menu_group' => 'main'],

            // Bookings
            ['name' => 'manage_bookings', 'display_name' => 'Manage Bookings', 'group' => 'bookings', 'description' => 'View and manage travel bookings', 'menu_label' => 'Bookings', 'menu_icon' => 'calendar-check', 'menu_order' => 9, 'route_name' => 'admin.bookings', 'menu_group' => 'main'],

            // Settings
            ['name' => 'view_analytics', 'display_name' => 'View Analytics', 'group' => 'system', 'description' => 'View dashboard analytics and settings', 'menu_label' => 'Settings', 'menu_icon' => 'cogs', 'menu_order' => 10, 'route_name' => 'admin.settings', 'menu_group' => 'main'],

            // System (no nav menu)
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles', 'group' => 'system', 'description' => 'Create, edit, and delete roles', 'route_name' => 'admin.roles'],
            ['name' => 'manage_permissions', 'display_name' => 'Manage Permissions', 'group' => 'system', 'description' => 'Create, edit, and delete permissions', 'route_name' => 'admin.permissions'],

            // ===== MONETIZATION GROUP =====
            // Ad Campaigns
            ['name' => 'manage_ad_campaigns', 'display_name' => 'Manage Ad Campaigns', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Create, edit, and manage ad campaigns', 'menu_label' => 'Ad Campaigns', 'menu_icon' => 'ad', 'menu_order' => 11, 'route_name' => 'admin.ad-campaigns'],

            // Offers
            ['name' => 'manage_offers', 'display_name' => 'Manage Offers', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Approve, reject, or pause business reward offers', 'menu_label' => 'Offers', 'menu_icon' => 'gift', 'menu_order' => 12, 'route_name' => 'admin.offers'],

            // Payouts
            ['name' => 'manage_payouts', 'display_name' => 'Manage Payouts', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Review and process partner payouts', 'menu_label' => 'Payouts', 'menu_icon' => 'money-bill-wave', 'menu_order' => 13, 'route_name' => 'admin.payouts'],

            // Withdrawals & Coins
            ['name' => 'manage_withdrawals', 'display_name' => 'Manage Withdrawals', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Approve or reject user withdrawal requests', 'menu_label' => 'Withdrawals', 'menu_icon' => 'wallet', 'menu_order' => 14, 'route_name' => 'admin.withdrawals'],

            // Coin Settings
            ['name' => 'manage_coin_settings', 'display_name' => 'Coin Settings', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Configure coin earning rates and withdrawal settings', 'menu_label' => 'Coin Settings', 'menu_icon' => 'coins', 'menu_order' => 15, 'route_name' => 'admin.coin-settings'],

            // Earnings Report
            ['name' => 'view_earnings_report', 'display_name' => 'Earnings Report', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'View platform earnings and ad revenue reports', 'menu_label' => 'Earnings Report', 'menu_icon' => 'chart-bar', 'menu_order' => 16, 'route_name' => 'admin.earnings-report'],

            // Business Verification
            ['name' => 'verify_businesses', 'display_name' => 'Verify Businesses', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Approve or reject business partner registrations', 'menu_label' => 'Travel Partners', 'menu_icon' => 'handshake', 'menu_order' => 17, 'route_name' => 'admin.travel-partners'],

            // Subscriptions
            ['name' => 'manage_subscriptions', 'display_name' => 'Manage Subscriptions', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Manage subscription plans and user subscriptions', 'menu_label' => 'Subscriptions', 'menu_icon' => 'id-card', 'menu_order' => 18, 'route_name' => 'admin.subscription.plans'],

            // Platform Expenses
            ['name' => 'manage_platform_expenses', 'display_name' => 'Platform Expenses', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Track and manage platform operating costs', 'menu_label' => 'Expenses', 'menu_icon' => 'file-invoice-dollar', 'menu_order' => 19, 'route_name' => 'admin.expenses'],

            // Employee Salaries
            ['name' => 'manage_salaries', 'display_name' => 'Employee Salaries', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'Manage employee salary records', 'menu_label' => 'Salaries', 'menu_icon' => 'users', 'menu_order' => 20, 'route_name' => 'admin.salaries'],

            // Financial Overview
            ['name' => 'view_financial_overview', 'display_name' => 'Financial Overview', 'group' => 'monetization', 'menu_group' => 'monetization', 'description' => 'View complete financial dashboard with P&L', 'menu_label' => 'Financial Overview', 'menu_icon' => 'chart-line', 'menu_order' => 21, 'route_name' => 'admin.financial-overview'],

            // ===== AI GROUP =====
            ['name' => 'manage_ai_agents', 'display_name' => 'Manage AI Agents', 'group' => 'ai', 'menu_group' => 'ai', 'description' => 'Create and configure AI agents', 'menu_label' => 'AI Agents', 'menu_icon' => 'robot', 'menu_order' => 22, 'route_name' => 'admin.ai.agents'],
            ['name' => 'manage_ai_tasks', 'display_name' => 'Manage AI Tasks', 'group' => 'ai', 'menu_group' => 'ai', 'description' => 'View and manage AI agent tasks', 'menu_label' => 'AI Tasks', 'menu_icon' => 'microchip', 'menu_order' => 23, 'route_name' => 'admin.ai.tasks'],

            // Translator
            ['name' => 'manage_translations', 'display_name' => 'Translator', 'group' => 'ai', 'menu_group' => 'ai', 'description' => 'Manage UI translations for the mobile app', 'menu_label' => 'Translator', 'menu_icon' => 'language', 'menu_order' => 24, 'route_name' => 'admin.translator'],

            // ===== WEBSITE GROUP =====
            ['name' => 'manage_curated_routes', 'display_name' => 'Manage Curated Routes', 'group' => 'website', 'menu_group' => 'main', 'description' => 'Create and edit curated routes for the public website', 'menu_label' => 'Routes', 'menu_icon' => 'route', 'menu_order' => 25, 'route_name' => 'admin.routes'],

            // Audit Logs
            ['name' => 'view_audit_logs', 'display_name' => 'View Audit Logs', 'group' => 'system', 'description' => 'View system audit logs', 'menu_label' => 'Audit Logs', 'menu_icon' => 'history', 'menu_order' => 26, 'route_name' => 'admin.audit-logs', 'menu_group' => 'main'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(
                ['name' => $p['name']],
                $p + ['is_system' => true],
            );
        }

        // ========== Roles ==========
        $roles = [
            'super_admin' => [
                'display_name' => 'Super Administrator',
                'description' => 'Full system access - can manage all users except other super admins',
                'is_system' => true,
                'is_default' => false,
                'permissions' => Permission::pluck('name')->toArray(),
            ],
            'admin' => [
                'display_name' => 'Administrator',
                'description' => 'Admin access - can manage moderators and users',
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
            'business' => [
                'display_name' => 'Business Partner',
                'description' => 'Business partner who manages reward offers',
                'is_system' => true,
                'is_default' => false,
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
