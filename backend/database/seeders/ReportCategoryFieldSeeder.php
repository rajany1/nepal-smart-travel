<?php

namespace Database\Seeders;

use App\Models\ReportCategorie;
use App\Models\ReportCategoryField;
use Illuminate\Database\Seeder;

class ReportCategoryFieldSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ReportCategorie::whereIn('slug', [
            'road-traffic', 'safety-hazards', 'weather-conditions',
            'transportation', 'hidden-destinations', 'services-utilities',
            'events-notices', 'general'
        ])->get()->keyBy('slug');

        $fields = [
            'road-traffic' => [
                [
                    'name' => 'subcategory',
                    'label' => 'Type of Road Issue',
                    'label_ne' => 'सडक समस्याको प्रकार',
                    'placeholder' => 'Select the type of issue',
                    'placeholder_ne' => 'समस्याको प्रकार छान्नुहोस्',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['value' => 'pothole', 'label' => 'Pothole / Road Damage', 'label_ne' => 'गड्ढा / सडक क्षति'],
                        ['value' => 'road-blocked', 'label' => 'Road Blocked', 'label_ne' => 'सडक अवरुद्ध'],
                        ['value' => 'road-landslide', 'label' => 'Landslide on Road', 'label_ne' => 'सडकमा पहिरो'],
                        ['value' => 'construction', 'label' => 'Construction Work', 'label_ne' => 'निर्माण कार्य'],
                        ['value' => 'road-other', 'label' => 'Other Road Issue', 'label_ne' => 'अन्य सडक समस्या'],
                    ],
                    'validation' => ['required'],
                    'help_text' => 'Select the specific type of road issue',
                    'help_text_ne' => 'विशिष्ट सडक समस्याको प्रकार छान्नुहोस्',
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'severity',
                    'label' => 'Severity',
                    'label_ne' => 'गम्भीरता',
                    'placeholder' => 'How severe is the issue?',
                    'placeholder_ne' => 'समस्या कति गम्भीर छ?',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 2,
                    'options' => [
                        ['value' => 'low', 'label' => 'Low - Minor inconvenience', 'label_ne' => 'कम - सानो असुविधा'],
                        ['value' => 'medium', 'label' => 'Medium - Noticeable issue', 'label_ne' => 'मध्यम - देखिने समस्या'],
                        ['value' => 'high', 'label' => 'High - Significant disruption', 'label_ne' => 'उच्च - ठूलो व्यवधान'],
                        ['value' => 'critical', 'label' => 'Critical - Impassable/Dangerous', 'label_ne' => 'गम्भीर - अगम्य/खतरनाक'],
                    ],
                    'validation' => ['required'],
                    'help_text' => 'Rate the severity of the road issue',
                    'help_text_ne' => 'सडक समस्याको गम्भीरता मूल्यांकन गर्नुहोस्',
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'vehicle_type_affected',
                    'label' => 'Vehicle Types Affected',
                    'label_ne' => 'प्रभावित सवारी साधनहरू',
                    'type' => 'multi_select',
                    'required' => false,
                    'sort_order' => 3,
                    'options' => [
                        ['value' => 'car', 'label' => 'Cars', 'label_ne' => 'कारहरू'],
                        ['value' => 'motorcycle', 'label' => 'Motorcycles', 'label_ne' => 'मोटरसाइकलहरू'],
                        ['value' => 'bus', 'label' => 'Buses', 'label_ne' => 'बसहरू'],
                        ['value' => 'truck', 'label' => 'Trucks', 'label_ne' => 'ट्रकहरू'],
                        ['value' => 'bicycle', 'label' => 'Bicycles', 'label_ne' => 'साइकलहरू'],
                        ['value' => 'pedestrian', 'label' => 'Pedestrians', 'label_ne' => 'पैदल यात्रीहरू'],
                    ],
                    'validation' => [],
                    'help_text' => 'Which vehicle types are affected?',
                    'help_text_ne' => 'कुन सवारी साधनहरू प्रभावित भएका छन्?',
                    'show_in_preview' => true,
                ],
            ],
            'safety-hazards' => [
                [
                    'name' => 'subcategory',
                    'label' => 'Type of Hazard',
                    'label_ne' => 'जोखिमको प्रकार',
                    'placeholder' => 'Select hazard type',
                    'placeholder_ne' => 'जोखिमको प्रकार छान्नुहोस्',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['value' => 'hazard', 'label' => 'Hazard / Dangerous Spot', 'label_ne' => 'जोखिम / खतरनाक स्थान'],
                        ['value' => 'unsafe-structure', 'label' => 'Unsafe Structure', 'label_ne' => 'असुरक्षित संरचना'],
                        ['value' => 'falling-debris', 'label' => 'Falling Rocks/Debris', 'label_ne' => 'पत्थर/मलुवा पर्ने'],
                        ['value' => 'hazard-other', 'label' => 'Other Safety Hazard', 'label_ne' => 'अन्य सुरक्षा जोखिम'],
                    ],
                    'validation' => ['required'],
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'immediate_danger',
                    'label' => 'Immediate Danger to Life',
                    'label_ne' => 'जीवनका लागि तत्काल खतरा',
                    'type' => 'yes_no',
                    'required' => true,
                    'sort_order' => 2,
                    'options' => [
                        ['value' => 'yes', 'label' => 'Yes', 'label_ne' => 'हो'],
                        ['value' => 'no', 'label' => 'No', 'label_ne' => 'होइन'],
                    ],
                    'validation' => ['required', 'in:yes,no'],
                    'help_text' => 'Is there immediate danger to people?',
                    'help_text_ne' => 'के मानिसहरूका लागि तत्काल खतरा छ?',
                    'show_in_preview' => true,
                ],
            ],
            'weather-conditions' => [
                [
                    'name' => 'subcategory',
                    'label' => 'Weather Condition',
                    'label_ne' => 'मौसम अवस्था',
                    'placeholder' => 'Select weather condition',
                    'placeholder_ne' => 'मौसम अवस्था छान्नुहोस्',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['value' => 'heavy-rain', 'label' => 'Heavy Rain', 'label_ne' => 'भारी वर्षा'],
                        ['value' => 'flooding', 'label' => 'Flooding', 'label_ne' => 'बाढी'],
                        ['value' => 'strong-wind', 'label' => 'Strong Wind', 'label_ne' => 'प्रबल हावा'],
                        ['value' => 'snow-ice', 'label' => 'Snow / Ice', 'label_ne' => 'हिउँ / बर्फ'],
                        ['value' => 'storm', 'label' => 'Lightning/Storm', 'label_ne' => 'बिजुली/आँधी'],
                        ['value' => 'weather-other', 'label' => 'Other Weather', 'label_ne' => 'अन्य मौसम'],
                    ],
                    'validation' => ['required'],
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'impact_level',
                    'label' => 'Impact Level',
                    'label_ne' => 'प्रभावको स्तर',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 2,
                    'options' => [
                        ['value' => 'minor', 'label' => 'Minor - Localized', 'label_ne' => 'सानो - स्थानीय'],
                        ['value' => 'moderate', 'label' => 'Moderate - Area-wide', 'label_ne' => 'मध्यम - क्षेत्रव्यापी'],
                        ['value' => 'severe', 'label' => 'Severe - Widespread', 'label_ne' => 'गम्भीर - व्यापक'],
                        ['value' => 'extreme', 'label' => 'Extreme - Emergency', 'label_ne' => 'चरम - आपत्कालीन'],
                    ],
                    'validation' => ['required'],
                    'show_in_preview' => true,
                ],
            ],
            'transportation' => [
                [
                    'name' => 'subcategory',
                    'label' => 'Transport Issue',
                    'label_ne' => 'यातायात समस्या',
                    'placeholder' => 'Select issue type',
                    'placeholder_ne' => 'समस्याको प्रकार छान्नुहोस्',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['value' => 'bus-delay', 'label' => 'Bus Delay/Cancelled', 'label_ne' => 'बस ढिलो/रद्द'],
                        ['value' => 'taxi-issue', 'label' => 'Taxi/Ride Issue', 'label_ne' => 'ट्याक्सी/सवारी समस्या'],
                        ['value' => 'flight-delay', 'label' => 'Flight Delay/Cancelled', 'label_ne' => 'उडान ढिलो/रद्द'],
                        ['value' => 'transport-other', 'label' => 'Other Transport', 'label_ne' => 'अन्य यातायात'],
                    ],
                    'validation' => ['required'],
                    'show_in_preview' => true,
                ],
            ],
            'hidden-destinations' => [
                [
                    'name' => 'place_type',
                    'label' => 'Type of Discovery',
                    'label_ne' => 'खोजको प्रकार',
                    'placeholder' => 'What did you discover?',
                    'placeholder_ne' => 'के खोज्नुभएको?',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['value' => 'hidden-trail', 'label' => 'Hidden Trail/Path', 'label_ne' => 'लुकिएको बाटो/पथ'],
                        ['value' => 'viewpoint', 'label' => 'Scenic Viewpoint', 'label_ne' => 'दृश्यावलोकन स्थल'],
                        ['value' => 'secret-spot', 'label' => 'Local Secret Spot', 'label_ne' => 'स्थानीय गुप्त स्थान'],
                        ['value' => 'discovery-other', 'label' => 'Other Discovery', 'label_ne' => 'अन्य खोज'],
                    ],
                    'validation' => ['required'],
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'accessibility',
                    'label' => 'Accessibility',
                    'label_ne' => 'पहुँच',
                    'type' => 'single_select',
                    'required' => false,
                    'sort_order' => 2,
                    'options' => [
                        ['value' => 'easy', 'label' => 'Easy Access', 'label_ne' => 'सजिलो पहुँच'],
                        ['value' => 'moderate', 'label' => 'Moderate Hike', 'label_ne' => 'मध्यम हाइक'],
                        ['value' => 'difficult', 'label' => 'Difficult/Technical', 'label_ne' => 'कठिन/प्राविधिक'],
                    ],
                    'validation' => [],
                    'help_text' => 'How difficult is it to reach?',
                    'help_text_ne' => 'पुग्न कति कठिन छ?',
                    'show_in_preview' => true,
                ],
            ],
            'services-utilities' => [
                [
                    'name' => 'subcategory',
                    'label' => 'Service Issue',
                    'label_ne' => 'सेवा समस्या',
                    'placeholder' => 'Select service issue',
                    'placeholder_ne' => 'सेवा समस्या छान्नुहोस्',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['value' => 'fuel-shortage', 'label' => 'Fuel Shortage', 'label_ne' => 'इन्धन कमी'],
                        ['value' => 'power-outage', 'label' => 'Power Outage', 'label_ne' => 'बिजुली कटौती'],
                        ['value' => 'water-issue', 'label' => 'Water Supply Issue', 'label_ne' => 'पानी आपूर्ति समस्या'],
                        ['value' => 'internet-down', 'label' => 'Internet/Network Down', 'label_ne' => 'इन्टरनेट/नेटवर्क डाउन'],
                        ['value' => 'atm-issue', 'label' => 'ATM/Banking Issue', 'label_ne' => 'एटीएम/बैंकिङ समस्या'],
                        ['value' => 'service-other', 'label' => 'Other Service Issue', 'label_ne' => 'अन्य सेवा समस्या'],
                    ],
                    'validation' => ['required'],
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'duration',
                    'label' => 'Expected Duration',
                    'label_ne' => 'अनुमानित अवधि',
                    'type' => 'single_select',
                    'required' => false,
                    'sort_order' => 2,
                    'options' => [
                        ['value' => 'hours', 'label' => 'Few Hours', 'label_ne' => 'केही घण्टा'],
                        ['value' => 'day', 'label' => '1 Day', 'label_ne' => '१ दिन'],
                        ['value' => 'days', 'label' => 'Multiple Days', 'label_ne' => 'एकाधिक दिन'],
                        ['value' => 'unknown', 'label' => 'Unknown', 'label_ne' => 'अज्ञात'],
                    ],
                    'validation' => [],
                    'help_text' => 'How long is the issue expected to last?',
                    'help_text_ne' => 'समस्या कति लामो समयसम्म रहन्छ?',
                    'show_in_preview' => true,
                ],
            ],
            'events-notices' => [
                [
                    'name' => 'subcategory',
                    'label' => 'Notice Type',
                    'label_ne' => 'सूचनाको प्रकार',
                    'placeholder' => 'Select notice type',
                    'placeholder_ne' => 'सूचनाको प्रकार छान्नुहोस्',
                    'type' => 'single_select',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['value' => 'local-event', 'label' => 'Local Event/Festival', 'label_ne' => 'स्थानीय कार्यक्रम/पर्व'],
                        ['value' => 'community-notice', 'label' => 'Community Notice', 'label_ne' => 'समुदायिक सूचना'],
                        ['value' => 'lost-found', 'label' => 'Lost & Found', 'label_ne' => 'हरायो-पायो'],
                        ['value' => 'road-closure', 'label' => 'Road Closure Notice', 'label_ne' => 'सडक बन्द सूचना'],
                        ['value' => 'notice-other', 'label' => 'Other Notice', 'label_ne' => 'अन्य सूचना'],
                    ],
                    'validation' => ['required'],
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'event_date',
                    'label' => 'Event Date',
                    'label_ne' => 'कार्यक्रमको मिति',
                    'type' => 'date',
                    'required' => false,
                    'sort_order' => 2,
                    'options' => [],
                    'validation' => ['date'],
                    'help_text' => 'When is the event/notice effective?',
                    'help_text_ne' => 'कार्यक्रम/सूचना कहिलेबाट प्रभावी छ?',
                    'show_in_preview' => true,
                ],
                [
                    'name' => 'contact_info',
                    'label' => 'Contact Information',
                    'label_ne' => 'सम्पर्क जानकारी',
                    'type' => 'text',
                    'required' => false,
                    'sort_order' => 3,
                    'options' => [],
                    'validation' => ['max:500'],
                    'help_text' => 'Optional contact for more info',
                    'help_text_ne' => 'थप जानकारीका लागि वैकल्पिक सम्पर्क',
                    'show_in_preview' => true,
                ],
            ],
            'general' => [
                [
                    'name' => 'description',
                    'label' => 'Description',
                    'label_ne' => 'विवरण',
                    'placeholder' => 'Describe what happened...',
                    'placeholder_ne' => 'के भयो भन्नुहोस्...',
                    'type' => 'textarea',
                    'required' => true,
                    'sort_order' => 1,
                    'options' => [],
                    'validation' => ['required', 'string', 'min:10', 'max:10000'],
                    'help_text' => 'Provide as much detail as possible',
                    'help_text_ne' => 'सकिँदेको विवरण दिनुहोस्',
                    'show_in_preview' => true,
                ],
            ],
        ];

        foreach ($fields as $categorySlug => $categoryFields) {
            $category = $categories->get($categorySlug);
            if (!$category) continue;

            foreach ($categoryFields as $index => $field) {
                ReportCategoryField::firstOrCreate(
                    ['category_id' => $category->id, 'name' => $field['name']],
                    array_merge($field, ['category_id' => $category->id, 'is_active' => true])
                );
            }
        }

        $this->command->info('Created report category fields for ' . count($fields) . ' categories.');
    }
}