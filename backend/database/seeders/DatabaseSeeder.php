<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Alert;
use App\Models\PlaceCategories;
use App\Models\ReportCategorie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Report Categories (first one is the default)
        $reportCategories = [
            ['name' => 'General', 'icon' => 'info'],
            ['name' => 'Road & Traffic', 'icon' => 'road'],
            ['name' => 'Safety & Hazards', 'icon' => 'warning'],
            ['name' => 'Weather & Conditions', 'icon' => 'ac_unit'],
            ['name' => 'Transportation', 'icon' => 'directions_bus'],
            ['name' => 'Hidden Destinations', 'icon' => 'explore'],
            ['name' => 'Services & Utilities', 'icon' => 'local_gas_station'],
            ['name' => 'Events & Notices', 'icon' => 'event'],
        ];

        foreach ($reportCategories as $cat) {
            ReportCategorie::create($cat);
        }

        $this->command->info('Created ' . count($reportCategories) . ' report categories.');

        // Place Categories - use firstOrCreate to prevent duplicates on re-seeding
        $categories = [
            ['name' => 'All', 'icon' => 'explore'],
            ['name' => 'Attractions', 'icon' => 'tour'],
            ['name' => 'Hotels', 'icon' => 'hotel'],
            ['name' => 'Restaurants', 'icon' => 'restaurant'],
            ['name' => 'Emergency', 'icon' => 'local_hospital'],
            ['name' => 'ATMs', 'icon' => 'account_balance'],
            ['name' => 'Fuel', 'icon' => 'local_gas_station'],
            ['name' => 'Activities', 'icon' => 'directions_bike'],
        ];

        foreach ($categories as $cat) {
            PlaceCategories::firstOrCreate(['name' => $cat['name']], $cat);
        }

        $this->command->info('Ensured ' . count($categories) . ' place categories exist.');

        // Sample Alerts
        $alerts = [
            ['title' => 'Road Blockage on Prithvi Highway', 'description' => 'Major landslide near Malekhu. Traffic diverted to alternative route via Muglin.', 'alert_type' => 'landslide', 'severity' => 'critical', 'affected_district' => 'Dhading'],
            ['title' => 'Heavy Rainfall Warning', 'description' => 'Continuous heavy rain expected in Pokhara region for next 24 hours. Risk of flooding in low-lying areas.', 'alert_type' => 'weather', 'severity' => 'high', 'affected_district' => 'Kaski'],
            ['title' => 'Bandh Called in Kathmandu', 'description' => 'General strike announced for tomorrow. All businesses and transportation will be affected.', 'alert_type' => 'strike', 'severity' => 'high', 'affected_district' => 'Kathmandu'],
            ['title' => 'Fuel Shortage at Multiple Stations', 'description' => 'Diesel and petrol unavailable at several pumps in the valley due to supply disruption.', 'alert_type' => 'emergency', 'severity' => 'medium', 'affected_district' => 'Lalitpur'],
            ['title' => 'Traffic Congestion in Thamel', 'description' => 'Heavy traffic due to festival crowd. Expect significant delays in the tourist district.', 'alert_type' => 'emergency', 'severity' => 'medium', 'affected_district' => 'Kathmandu'],
            ['title' => 'Power Outage Scheduled', 'description' => 'Planned maintenance: No electricity from 8 AM - 2 PM in Bhaktapur area.', 'alert_type' => 'emergency', 'severity' => 'info', 'affected_district' => 'Bhaktapur'],
            ['title' => 'Earthquake Tremors Reported', 'description' => 'Minor tremors felt in Kathmandu valley this morning. No casualties reported.', 'alert_type' => 'earthquake', 'severity' => 'info', 'affected_district' => 'Kathmandu'],
        ];

        $hasAffected = Schema::hasColumn('alerts', 'affected_district');
        foreach ($alerts as $alert) {
            if (! $hasAffected) {
                unset($alert['affected_district']);
            }
            try {
                Alert::create($alert);
            } catch (\Throwable $e) {
                // don't break the seeder if alerts table differs
                $this->command->warn('Skipping an alert due to schema mismatch: ' . $e->getMessage());
            }
        }

        $this->command->info('Created up to ' . count($alerts) . ' sample alerts.');

        // Seed roles and permissions first (needed for user seeder)
        $this->call(\Database\Seeders\RolePermissionSeeder::class);

        // Seed achievements
        $this->call(\Database\Seeders\AchievementSeeder::class);

        // Seed subscription plans
        $this->call(\Database\Seeders\SubscriptionPlanSeeder::class);

        // Seed test users
        $this->call(\Database\Seeders\UserSeeder::class);

        // Seed default moderator permissions (deprecated — replaced by RolePermissionSeeder)
        // $this->call(\Database\Seeders\PermissionSeeder::class);
    }
}