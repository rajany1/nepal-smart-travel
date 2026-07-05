<?php

namespace Database\Seeders;

use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // Report-based
            [
                'name' => 'first_report',
                'display_name' => 'First Report',
                'description' => 'Submit your first report',
                'icon' => 'description',
                'category' => 'reports',
                'criteria' => ['type' => 'reports_count', 'value' => 1],
                'xp_reward' => 5,
                'is_system' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'report_master',
                'display_name' => 'Report Master',
                'description' => 'Submit 10 reports',
                'icon' => 'assignment',
                'category' => 'reports',
                'criteria' => ['type' => 'reports_count', 'value' => 10],
                'xp_reward' => 20,
                'is_system' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'quality_contributor',
                'display_name' => 'Quality Contributor',
                'description' => 'Get 5 reports approved',
                'icon' => 'verified',
                'category' => 'reports',
                'criteria' => ['type' => 'approved_reports', 'value' => 5],
                'xp_reward' => 15,
                'is_system' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'top_reporter',
                'display_name' => 'Top Reporter',
                'description' => 'Get 20 reports approved',
                'icon' => 'star',
                'category' => 'reports',
                'criteria' => ['type' => 'approved_reports', 'value' => 20],
                'xp_reward' => 50,
                'is_system' => true,
                'sort_order' => 40,
            ],
            // Alert-based
            [
                'name' => 'alert_hero',
                'display_name' => 'Alert Hero',
                'description' => 'Post 5 alerts',
                'icon' => 'warning',
                'category' => 'alerts',
                'criteria' => ['type' => 'alerts_count', 'value' => 5],
                'xp_reward' => 10,
                'is_system' => true,
                'sort_order' => 50,
            ],
            [
                'name' => 'emergency_responder',
                'display_name' => 'Emergency Responder',
                'description' => 'Post a critical alert',
                'icon' => 'emergency',
                'category' => 'alerts',
                'criteria' => ['type' => 'critical_alerts', 'value' => 1],
                'xp_reward' => 15,
                'is_system' => true,
                'sort_order' => 60,
            ],
            // Review-based
            [
                'name' => 'reviewer',
                'display_name' => 'Place Reviewer',
                'description' => 'Review 3 places',
                'icon' => 'rate_review',
                'category' => 'reviews',
                'criteria' => ['type' => 'reviews_count', 'value' => 3],
                'xp_reward' => 10,
                'is_system' => true,
                'sort_order' => 70,
            ],
            // Level-based
            [
                'name' => 'explorer',
                'display_name' => 'Explorer',
                'description' => 'Reach level 5',
                'icon' => 'explore',
                'category' => 'levels',
                'criteria' => ['type' => 'level_reached', 'value' => 5],
                'xp_reward' => 10,
                'is_system' => true,
                'sort_order' => 80,
            ],
            [
                'name' => 'contributor',
                'display_name' => 'Contributor',
                'description' => 'Reach level 15',
                'icon' => 'trending_up',
                'category' => 'levels',
                'criteria' => ['type' => 'level_reached', 'value' => 15],
                'xp_reward' => 25,
                'is_system' => true,
                'sort_order' => 90,
            ],
            [
                'name' => 'trusted_local',
                'display_name' => 'Trusted Local',
                'description' => 'Reach level 30',
                'icon' => 'groups',
                'category' => 'levels',
                'criteria' => ['type' => 'level_reached', 'value' => 30],
                'xp_reward' => 50,
                'is_system' => true,
                'sort_order' => 100,
            ],
            [
                'name' => 'regional_guide',
                'display_name' => 'Regional Guide',
                'description' => 'Reach level 50',
                'icon' => 'map',
                'category' => 'levels',
                'criteria' => ['type' => 'level_reached', 'value' => 50],
                'xp_reward' => 100,
                'is_system' => true,
                'sort_order' => 110,
            ],
            [
                'name' => 'community_expert',
                'display_name' => 'Community Expert',
                'description' => 'Reach level 100',
                'icon' => 'psychology',
                'category' => 'levels',
                'criteria' => ['type' => 'level_reached', 'value' => 100],
                'xp_reward' => 200,
                'is_system' => true,
                'sort_order' => 120,
            ],
            // Social
            [
                'name' => 'helper',
                'display_name' => 'Community Helper',
                'description' => 'Leave 10 comments',
                'icon' => 'comment',
                'category' => 'social',
                'criteria' => ['type' => 'comments_count', 'value' => 10],
                'xp_reward' => 10,
                'is_system' => true,
                'sort_order' => 130,
            ],
            // Photo (added but kept simple — will always be manual unlock via admin)
            [
                'name' => 'photographer',
                'display_name' => 'Photographer',
                'description' => 'Upload images to reports',
                'icon' => 'camera_alt',
                'category' => 'general',
                'criteria' => null,
                'xp_reward' => 0,
                'is_system' => true,
                'sort_order' => 140,
            ],
        ];

        foreach ($achievements as $data) {
            Achievement::updateOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }

        $this->command->info('Created/ensured ' . count($achievements) . ' achievements.');
    }
}
