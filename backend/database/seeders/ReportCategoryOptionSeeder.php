<?php

namespace Database\Seeders;

use App\Models\ReportCategorie;
use App\Models\ReportCategoryOption;
use Illuminate\Database\Seeder;

class ReportCategoryOptionSeeder extends Seeder
{
    public function run(): void
    {
        // Get categories by slug
        $categories = ReportCategorie::whereIn('slug', [
            'road-traffic', 'safety-hazards', 'weather-conditions',
            'transportation', 'hidden-destinations', 'services-utilities',
            'events-notices', 'general'
        ])->get()->keyBy('slug');

        $options = [
            'road-traffic' => [
                ['name' => 'Pothole / Road Damage', 'slug' => 'pothole', 'name_ne' => 'गड्ढा / सडक क्षति', 'icon' => 'pothole', 'sort_order' => 1, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Road Blocked', 'slug' => 'road-blocked', 'name_ne' => 'सडक अवरुद्ध', 'icon' => 'block', 'sort_order' => 2, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Landslide on Road', 'slug' => 'road-landslide', 'name_ne' => 'सडकमा पहिरो', 'icon' => 'terrain', 'sort_order' => 3, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Construction Work', 'slug' => 'construction', 'name_ne' => 'निर्माण कार्य', 'icon' => 'construction', 'sort_order' => 4, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Other Road Issue', 'slug' => 'road-other', 'name_ne' => 'अन्य सडक समस्या', 'icon' => 'help', 'sort_order' => 5, 'requires_photo' => false, 'requires_location' => true],
            ],
            'safety-hazards' => [
                ['name' => 'Hazard / Dangerous Spot', 'slug' => 'hazard', 'name_ne' => 'जोखिम / खतरनाक स्थान', 'icon' => 'warning', 'sort_order' => 1, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Unsafe Structure', 'slug' => 'unsafe-structure', 'name_ne' => 'असुरक्षित संरचना', 'icon' => 'home_repair_service', 'sort_order' => 2, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Falling Rocks/Debris', 'slug' => 'falling-debris', 'name_ne' => 'पत्थर/मलुवा पर्ने', 'icon' => 'landscape', 'sort_order' => 3, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Other Safety Hazard', 'slug' => 'hazard-other', 'name_ne' => 'अन्य सुरक्षा जोखिम', 'icon' => 'help', 'sort_order' => 4, 'requires_photo' => false, 'requires_location' => true],
            ],
            'weather-conditions' => [
                ['name' => 'Heavy Rain', 'slug' => 'heavy-rain', 'name_ne' => 'भारी वर्षा', 'icon' => 'rain', 'sort_order' => 1, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Flooding', 'slug' => 'flooding', 'name_ne' => 'बाढी', 'icon' => 'flood', 'sort_order' => 2, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Strong Wind', 'slug' => 'strong-wind', 'name_ne' => 'प्रबल हावा', 'icon' => 'air', 'sort_order' => 3, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Snow / Ice', 'slug' => 'snow-ice', 'name_ne' => 'हिउँ / बर्फ', 'icon' => 'ac_unit', 'sort_order' => 4, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Lightning/Storm', 'slug' => 'storm', 'name_ne' => 'बिजुली/आँधी', 'icon' => 'flash_on', 'sort_order' => 5, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Other Weather', 'slug' => 'weather-other', 'name_ne' => 'अन्य मौसम', 'icon' => 'help', 'sort_order' => 6, 'requires_photo' => false, 'requires_location' => true],
            ],
            'transportation' => [
                ['name' => 'Bus Delay/Cancelled', 'slug' => 'bus-delay', 'name_ne' => 'बस ढिलो/रद्द', 'icon' => 'directions_bus', 'sort_order' => 1, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Taxi/Ride Issue', 'slug' => 'taxi-issue', 'name_ne' => 'ट्याक्सी/सवारी समस्या', 'icon' => 'local_taxi', 'sort_order' => 2, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Flight Delay/Cancelled', 'slug' => 'flight-delay', 'name_ne' => 'उडान ढिलो/रद्द', 'icon' => 'flight', 'sort_order' => 3, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Other Transport', 'slug' => 'transport-other', 'name_ne' => 'अन्य यातायात', 'icon' => 'help', 'sort_order' => 4, 'requires_photo' => false, 'requires_location' => true],
            ],
            'hidden-destinations' => [
                ['name' => 'Hidden Trail/Path', 'slug' => 'hidden-trail', 'name_ne' => 'लुकिएको बाटो/पथ', 'icon' => 'hiking', 'sort_order' => 1, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Scenic Viewpoint', 'slug' => 'viewpoint', 'name_ne' => 'दृश्यावलोकन स्थल', 'icon' => 'visibility', 'sort_order' => 2, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Local Secret Spot', 'slug' => 'secret-spot', 'name_ne' => 'स्थानीय गुप्त स्थान', 'icon' => 'star', 'sort_order' => 3, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Other Discovery', 'slug' => 'discovery-other', 'name_ne' => 'अन्य खोज', 'icon' => 'help', 'sort_order' => 4, 'requires_photo' => false, 'requires_location' => true],
            ],
            'services-utilities' => [
                ['name' => 'Fuel Shortage', 'slug' => 'fuel-shortage', 'name_ne' => 'इन्धन कमी', 'icon' => 'local_gas_station', 'sort_order' => 1, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Power Outage', 'slug' => 'power-outage', 'name_ne' => 'बिजुली कटौती', 'icon' => 'bolt', 'sort_order' => 2, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Water Supply Issue', 'slug' => 'water-issue', 'name_ne' => 'पानी आपूर्ति समस्या', 'icon' => 'water_drop', 'sort_order' => 3, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Internet/Network Down', 'slug' => 'internet-down', 'name_ne' => 'इन्टरनेट/नेटवर्क डाउन', 'icon' => 'wifi_off', 'sort_order' => 4, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'ATM/Banking Issue', 'slug' => 'atm-issue', 'name_ne' => 'एटीएम/बैंकिङ समस्या', 'icon' => 'atm', 'sort_order' => 5, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Other Service Issue', 'slug' => 'service-other', 'name_ne' => 'अन्य सेवा समस्या', 'icon' => 'help', 'sort_order' => 6, 'requires_photo' => false, 'requires_location' => true],
            ],
            'events-notices' => [
                ['name' => 'Local Event/Festival', 'slug' => 'local-event', 'name_ne' => 'स्थानीय कार्यक्रम/पर्व', 'icon' => 'celebration', 'sort_order' => 1, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Community Notice', 'slug' => 'community-notice', 'name_ne' => 'समुदायिक सूचना', 'icon' => 'announcement', 'sort_order' => 2, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Lost & Found', 'slug' => 'lost-found', 'name_ne' => 'हरायो-पायो', 'icon' => 'find_in_page', 'sort_order' => 3, 'requires_photo' => true, 'requires_location' => true],
                ['name' => 'Road Closure Notice', 'slug' => 'road-closure', 'name_ne' => 'सडक बन्द सूचना', 'icon' => 'road', 'sort_order' => 4, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Other Notice', 'slug' => 'notice-other', 'name_ne' => 'अन्य सूचना', 'icon' => 'help', 'sort_order' => 5, 'requires_photo' => false, 'requires_location' => true],
            ],
            'general' => [
                ['name' => 'General Report', 'slug' => 'general-report', 'name_ne' => 'सामान्य रिपोर्ट', 'icon' => 'assignment', 'sort_order' => 1, 'requires_photo' => false, 'requires_location' => true],
                ['name' => 'Feedback/Suggestion', 'slug' => 'feedback', 'name_ne' => 'प्रतिपुष्टि/सुझाव', 'icon' => 'feedback', 'sort_order' => 2, 'requires_photo' => false, 'requires_location' => false],
            ],
        ];

        // Severity drives the green/blue/red outline on report option cards.
        // low => green, medium => blue, high/critical => red
        $severityBySlug = [
            'pothole' => 'medium', 'road-blocked' => 'high', 'road-landslide' => 'critical',
            'construction' => 'medium', 'road-other' => 'low',
            'hazard' => 'high', 'unsafe-structure' => 'critical', 'falling-debris' => 'critical',
            'hazard-other' => 'medium',
            'heavy-rain' => 'medium', 'flooding' => 'critical', 'strong-wind' => 'high',
            'snow-ice' => 'high', 'storm' => 'critical', 'weather-other' => 'low',
            'bus-delay' => 'low', 'taxi-issue' => 'low', 'flight-delay' => 'medium',
            'transport-other' => 'low',
            'hidden-trail' => 'medium', 'viewpoint' => 'low', 'secret-spot' => 'low',
            'discovery-other' => 'low',
            'fuel-shortage' => 'high', 'power-outage' => 'high', 'water-issue' => 'medium',
            'internet-down' => 'medium', 'atm-issue' => 'low', 'service-other' => 'low',
            'local-event' => 'low', 'community-notice' => 'low', 'lost-found' => 'medium',
            'road-closure' => 'high', 'notice-other' => 'low',
            'general-report' => 'low', 'feedback' => 'low',
        ];

        foreach ($options as $categorySlug => $categoryOptions) {
            $category = $categories->get($categorySlug);
            if (!$category) continue;

            foreach ($categoryOptions as $opt) {
                $severity = $severityBySlug[$opt['slug']] ?? 'medium';
                $row = ReportCategoryOption::firstOrCreate(
                    ['category_id' => $category->id, 'slug' => $opt['slug']],
                    array_merge($opt, ['category_id' => $category->id, 'icon_type' => 'material', 'is_active' => true, 'severity' => $severity])
                );
                // Backfill severity for rows created before the column existed
                if ($row->severity !== $severity) {
                    $row->update(['severity' => $severity]);
                }
            }
        }

        $this->command->info('Created report category options for ' . count($options) . ' categories.');
    }
}