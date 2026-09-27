<?php

namespace Database\Seeders;

use App\Models\ReportCategoryGroup;
use Illuminate\Database\Seeder;

class ReportCategoryGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => 'Travel',
                'slug' => 'travel',
                'name_ne' => 'यात्रा',
                'description' => 'Road, traffic, transport, fuel, and travel-related reports',
                'description_ne' => 'सडक, यातायात, परिवहन, इन्धन र यात्रा सम्बन्धी रिपोर्टहरू',
                'icon' => 'directions_car',
                'icon_type' => 'material',
                'icon_color' => '#F39C12',
                'icon_background' => '#FFF3E0',
                'sort_order' => 1,
                'is_active' => true,
                'is_emergency_group' => false,
            ],
            [
                'name' => 'Community',
                'slug' => 'community',
                'name_ne' => 'समुदाय',
                'description' => 'Local updates, events, lost & found, community notices',
                'description_ne' => 'स्थानीय अपडेटहरू, कार्यक्रमहरू, हरायो-पायो, समुदायिक सूचनाहरू',
                'icon' => 'groups',
                'icon_type' => 'material',
                'icon_color' => '#8E44AD',
                'icon_background' => '#F3E5F5',
                'sort_order' => 2,
                'is_active' => true,
                'is_emergency_group' => false,
            ],
            [
                'name' => 'Safety',
                'slug' => 'safety',
                'name_ne' => 'सुरक्षा',
                'description' => 'Hazards, weather emergencies, landslides, floods, emergencies',
                'description_ne' => 'जोखिमहरू, मौसम आपत्कालीन, पहिरो, बाढी, आपत्कालीन अवस्थाहरू',
                'icon' => 'warning',
                'icon_type' => 'material',
                'icon_color' => '#E74C3C',
                'icon_background' => '#FDEDEC',
                'sort_order' => 3,
                'is_active' => true,
                'is_emergency_group' => true,
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'name_ne' => 'सेवाहरू',
                'description' => 'Utilities, hospitals, ATMs, pharmacies, government services',
                'description_ne' => 'उपयोगिताहरू, अस्पतालहरू, एटीएमहरू, फार्मेसीहरू, सरकारी सेवाहरू',
                'icon' => 'local_hospital',
                'icon_type' => 'material',
                'icon_color' => '#27AE60',
                'icon_background' => '#E8F8F5',
                'sort_order' => 4,
                'is_active' => true,
                'is_emergency_group' => false,
            ],
        ];

        foreach ($groups as $group) {
            ReportCategoryGroup::firstOrCreate(['slug' => $group['slug']], $group);
        }

        $this->command->info('Created ' . count($groups) . ' report category groups.');
    }
}