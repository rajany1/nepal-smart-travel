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
            [
                'name' => 'General',
                'slug' => 'general',
                'icon' => 'info',
                'description' => 'General reports and updates',
                'description_ne' => 'सामान्य रिपोर्ट र अपडेटहरू',
                'sort_order' => 0,
                'is_active' => true,
                'is_featured' => false,
                'is_emergency' => false,
            ],
            [
                'name' => 'Road & Traffic',
                'slug' => 'road-traffic',
                'icon' => 'road',
                'description' => 'Road conditions, traffic, construction, blockages',
                'description_ne' => 'सडक अवस्था, यातायात, निर्माण, अवरोधहरू',
                'sort_order' => 1,
                'is_active' => true,
                'is_featured' => true,
                'is_emergency' => false,
            ],
            [
                'name' => 'Safety & Hazards',
                'slug' => 'safety-hazards',
                'icon' => 'warning',
                'description' => 'Safety concerns, hazards, dangerous conditions',
                'description_ne' => 'सुरक्षा चिन्ता, जोखिम, खतरनाक अवस्थाहरू',
                'sort_order' => 2,
                'is_active' => true,
                'is_featured' => true,
                'is_emergency' => true,
            ],
            [
                'name' => 'Weather & Conditions',
                'slug' => 'weather-conditions',
                'icon' => 'ac_unit',
                'description' => 'Weather reports, flooding, landslides, conditions',
                'description_ne' => 'मौसम रिपोर्ट, बाढी, पहिरो, अवस्थाहरू',
                'sort_order' => 3,
                'is_active' => true,
                'is_featured' => true,
                'is_emergency' => true,
            ],
            [
                'name' => 'Transportation',
                'slug' => 'transportation',
                'icon' => 'directions_bus',
                'description' => 'Public transport, bus, taxi, flight issues',
                'description_ne' => 'सार्वजनिक यातायात, बस, ट्याक्सी, उडान समस्याहरू',
                'sort_order' => 4,
                'is_active' => true,
                'is_featured' => false,
                'is_emergency' => false,
            ],
            [
                'name' => 'Hidden Destinations',
                'slug' => 'hidden-destinations',
                'icon' => 'explore',
                'description' => 'Off-the-beaten-path places and discoveries',
                'description_ne' => 'अनछुए स्थानहरू र नयाँ खोजहरू',
                'sort_order' => 5,
                'is_active' => true,
                'is_featured' => false,
                'is_emergency' => false,
            ],
            [
                'name' => 'Services & Utilities',
                'slug' => 'services-utilities',
                'icon' => 'local_gas_station',
                'description' => 'Fuel, electricity, water, internet, ATMs',
                'description_ne' => 'इन्धन, बिजुली, पानी, इन्टरनेट, एटीएम',
                'sort_order' => 6,
                'is_active' => true,
                'is_featured' => true,
                'is_emergency' => false,
            ],
            [
                'name' => 'Events & Notices',
                'slug' => 'events-notices',
                'icon' => 'event',
                'description' => 'Local events, festivals, community notices',
                'description_ne' => 'स्थानीय कार्यक्रमहरू, पर्वहरू, समुदायिक सूचनाहरू',
                'sort_order' => 7,
                'is_active' => true,
                'is_featured' => false,
                'is_emergency' => false,
            ],
        ];

        foreach ($reportCategories as $cat) {
            ReportCategorie::firstOrCreate(['slug' => $cat['slug']], $cat);
        }

        $this->command->info('Created ' . count($reportCategories) . ' report categories.');

        // Seed report category groups, options, and fields
        $this->call(\Database\Seeders\ReportCategoryGroupSeeder::class);
        $this->call(\Database\Seeders\ReportCategoryOptionSeeder::class);
        $this->call(\Database\Seeders\ReportCategoryFieldSeeder::class);

        // Place Categories - use firstOrCreate to prevent duplicates on re-seeding
        $categories = [
            ['name' => 'All', 'icon' => 'explore'],
            ['name' => 'Attractions', 'icon' => 'tour'],
            ['name' => 'Hotels', 'icon' => 'hotel'],
            ['name' => 'Restaurants', 'icon' => 'restaurant'],
            ['name' => 'Emergency', 'icon' => 'local_hospital'],
            ['name' => 'Blood Bank', 'icon' => 'bloodtype'],
            ['name' => 'Pharmacy', 'icon' => 'medication'],
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

        // Seed AI agents
        $this->call(\Database\Seeders\AiAgentSeeder::class);

        // Seed translation glossary (rules-based translator)
        $this->call(\Database\Seeders\TranslationGlossarySeeder::class);
$this->call(\Database\Seeders\AppUiWordSeeder::class);

        // Seed curated routes (trekking + itineraries)
        $this->call(\Database\Seeders\CuratedRouteSeeder::class);

        // Seed legal document types
        $this->call(\Database\Seeders\LegalDocumentTypeSeeder::class);

        // Seed default moderator permissions (deprecated — replaced by RolePermissionSeeder)
        // $this->call(\Database\Seeders\PermissionSeeder::class);
    }
}