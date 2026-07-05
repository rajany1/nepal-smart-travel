<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'price' => 0,
                'billing_interval' => 'monthly',
                'description' => 'Basic access to community reports, alerts, and nearby places.',
                'features' => ['basic_map', 'community_reports', 'alerts', 'nearby_places'],
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Basic',
                'price' => 299,
                'billing_interval' => 'monthly',
                'description' => 'Offline maps, ad-free experience, and detailed place info.',
                'features' => ['offline_maps', 'ad_free', 'detailed_place_info', 'community_reports', 'alerts'],
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Premium',
                'price' => 599,
                'billing_interval' => 'monthly',
                'description' => 'AI itinerary planner, trek planner, priority support, and all Basic features.',
                'features' => ['offline_maps', 'ad_free', 'ai_itinerary', 'trek_planner', 'priority_support', 'detailed_place_info', 'community_reports', 'alerts'],
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Pro Yearly',
                'price' => 4999,
                'billing_interval' => 'yearly',
                'description' => 'Everything in Premium at a discounted annual rate — best value for frequent travelers.',
                'features' => ['offline_maps', 'ad_free', 'ai_itinerary', 'trek_planner', 'priority_support', 'detailed_place_info', 'community_reports', 'alerts', 'exclusive_deals'],
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'name' => 'Lifetime',
                'price' => 9999,
                'billing_interval' => 'yearly',
                'description' => 'One-time payment. Unlimited access to all features forever.',
                'features' => ['offline_maps', 'ad_free', 'ai_itinerary', 'trek_planner', 'priority_support', 'detailed_place_info', 'community_reports', 'alerts', 'exclusive_deals', 'api_access'],
                'is_active' => true,
                'sort_order' => 50,
            ],
        ];

        foreach ($plans as $data) {
            $data['slug'] = Str::slug($data['name']);
            SubscriptionPlan::updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );
        }

        $this->command->info('Created/ensured ' . count($plans) . ' subscription plans.');
    }
}
