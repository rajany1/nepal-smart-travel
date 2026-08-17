<?php

namespace Database\Seeders;

use App\Models\CuratedRoute;
use Illuminate\Database\Seeder;

class CuratedRouteSeeder extends Seeder
{
    public function run(): void
    {
        $routes = [
            [
                'slug' => 'everest-base-camp-trek',
                'title' => 'Everest Base Camp Trek',
                'route_type' => 'trekking',
                'difficulty' => 'hard',
                'description' => "Nepal's most iconic trek to the foot of the world's highest peak. Fly to Lukla, climb through Sherpa villages, monasteries and the Khumbu valley, cross suspension bridges and end beneath the Khumbu Icefall at Everest Base Camp — with a sunrise hike to Kala Patthar for the classic view of Everest.",
                'duration_days' => 14,
                'best_season' => 'Mar–May, Oct–Nov',
                'max_altitude_m' => 5644,
                'total_distance_km' => 130,
                'elevation_gain_m' => 2600,
                'starting_point' => 'Lukla',
                'ending_point' => 'Lukla',
                'track' => [
                    ['lat' => 27.6880, 'lng' => 86.7313, 'name' => 'Lukla (2,860m)'],
                    ['lat' => 27.7377, 'lng' => 86.7161, 'name' => 'Phakding (2,610m)'],
                    ['lat' => 27.8046, 'lng' => 86.7100, 'name' => 'Namche Bazaar (3,440m)'],
                    ['lat' => 27.8367, 'lng' => 86.7653, 'name' => 'Tengboche (3,860m)'],
                    ['lat' => 27.8942, 'lng' => 86.8226, 'name' => 'Pheriche (4,240m)'],
                    ['lat' => 27.8947, 'lng' => 86.8328, 'name' => 'Dingboche (4,410m)'],
                    ['lat' => 27.9497, 'lng' => 86.8126, 'name' => 'Lobuche (4,940m)'],
                    ['lat' => 27.9854, 'lng' => 86.8287, 'name' => 'Gorak Shep (5,164m)'],
                    ['lat' => 28.0004, 'lng' => 86.8513, 'name' => 'Everest Base Camp (5,364m)'],
                    ['lat' => 27.9958, 'lng' => 86.8283, 'name' => 'Kala Patthar (5,644m)'],
                    ['lat' => 27.9854, 'lng' => 86.8287, 'name' => 'Gorak Shep (5,164m)'],
                    ['lat' => 27.9497, 'lng' => 86.8126, 'name' => 'Lobuche (4,940m)'],
                    ['lat' => 27.8947, 'lng' => 86.8328, 'name' => 'Dingboche (4,410m)'],
                    ['lat' => 27.8367, 'lng' => 86.7653, 'name' => 'Tengboche (3,860m)'],
                    ['lat' => 27.8046, 'lng' => 86.7100, 'name' => 'Namche Bazaar (3,440m)'],
                    ['lat' => 27.7377, 'lng' => 86.7161, 'name' => 'Phakding (2,610m)'],
                    ['lat' => 27.6880, 'lng' => 86.7313, 'name' => 'Lukla (2,860m)'],
                ],
                'is_active' => true,
            ],
            [
                'slug' => 'annapurna-base-camp-trek',
                'title' => 'Annapurna Base Camp Trek',
                'route_type' => 'trekking',
                'difficulty' => 'moderate',
                'description' => "A classic Himalayan trail into the heart of the Annapurna Sanctuary — a natural amphitheatre ringed by Annapurna I, Machhapuchhre and Hiunchuli. Rice terraces, dense rhododendron forests, alpine meadows and hot springs at Jhinu make this one of the most scenic treks in Nepal.",
                'duration_days' => 8,
                'best_season' => 'Mar–May, Oct–Nov',
                'max_altitude_m' => 4130,
                'total_distance_km' => 110,
                'elevation_gain_m' => 3200,
                'starting_point' => 'Nayapul',
                'ending_point' => 'Nayapul',
                'track' => [
                    ['lat' => 28.3067, 'lng' => 83.6847, 'name' => 'Nayapul (1,010m)'],
                    ['lat' => 28.3530, 'lng' => 83.7350, 'name' => 'Tikhedhunga (1,540m)'],
                    ['lat' => 28.3650, 'lng' => 83.7500, 'name' => 'Ulleri (2,050m)'],
                    ['lat' => 28.3977, 'lng' => 83.6960, 'name' => 'Ghorepani (2,860m)'],
                    ['lat' => 28.3970, 'lng' => 83.6900, 'name' => 'Poon Hill (3,210m)'],
                    ['lat' => 28.3449, 'lng' => 83.8025, 'name' => 'Tadapani (2,630m)'],
                    ['lat' => 28.3140, 'lng' => 83.8540, 'name' => 'Chhomrong (2,170m)'],
                    ['lat' => 28.3130, 'lng' => 83.8830, 'name' => 'Bamboo (2,340m)'],
                    ['lat' => 28.3500, 'lng' => 83.8870, 'name' => 'Deurali (3,200m)'],
                    ['lat' => 28.4220, 'lng' => 83.9010, 'name' => 'Machhapuchhre Base Camp (3,700m)'],
                    ['lat' => 28.5300, 'lng' => 83.8770, 'name' => 'Annapurna Base Camp (4,130m)'],
                    ['lat' => 28.4220, 'lng' => 83.9010, 'name' => 'Machhapuchhre Base Camp (3,700m)'],
                    ['lat' => 28.3130, 'lng' => 83.8830, 'name' => 'Bamboo (2,340m)'],
                    ['lat' => 28.3140, 'lng' => 83.8540, 'name' => 'Chhomrong (2,170m)'],
                    ['lat' => 28.3067, 'lng' => 83.6847, 'name' => 'Nayapul (1,010m)'],
                ],
                'is_active' => true,
            ],
            [
                'slug' => 'poon-hill-trek',
                'title' => 'Poon Hill Sunrise Trek',
                'route_type' => 'trekking',
                'difficulty' => 'easy',
                'description' => "Nepal's most popular short trek — a 4–5 day walk through Gurung villages and rhododendron forests to Poon Hill (3,210m) for a panoramic sunrise over the Annapurna and Dhaulagiri ranges. Perfect first trek, no mountaineering experience needed.",
                'duration_days' => 5,
                'best_season' => 'Oct–Apr',
                'max_altitude_m' => 3210,
                'total_distance_km' => 55,
                'elevation_gain_m' => 1900,
                'starting_point' => 'Nayapul',
                'ending_point' => 'Nayapul',
                'track' => [
                    ['lat' => 28.3067, 'lng' => 83.6847, 'name' => 'Nayapul (1,010m)'],
                    ['lat' => 28.3530, 'lng' => 83.7350, 'name' => 'Tikhedhunga (1,540m)'],
                    ['lat' => 28.3650, 'lng' => 83.7500, 'name' => 'Ulleri (2,050m)'],
                    ['lat' => 28.3977, 'lng' => 83.6960, 'name' => 'Ghorepani (2,860m)'],
                    ['lat' => 28.3970, 'lng' => 83.6900, 'name' => 'Poon Hill (3,210m)'],
                    ['lat' => 28.3977, 'lng' => 83.6960, 'name' => 'Ghorepani (2,860m)'],
                    ['lat' => 28.3449, 'lng' => 83.8025, 'name' => 'Tadapani (2,630m)'],
                    ['lat' => 28.3067, 'lng' => 83.6847, 'name' => 'Nayapul (1,010m)'],
                ],
                'is_active' => true,
            ],
            [
                'slug' => 'kathmandu-valley-heritage-circle',
                'title' => 'Kathmandu Valley Heritage Circle',
                'route_type' => 'itinerary',
                'difficulty' => 'easy',
                'description' => "Two days across the UNESCO World Heritage Sites of the valley: the temples of Kathmandu Durbar Square, the stupas of Boudhanath and Swayambhunath, the living goddess Kumari, and the medieval squares of Patan and Bhaktapur.",
                'duration_days' => 2,
                'best_season' => 'Sep–Nov, Feb–Apr',
                'max_altitude_m' => 1400,
                'total_distance_km' => 40,
                'elevation_gain_m' => 300,
                'starting_point' => 'Kathmandu Durbar Square',
                'ending_point' => 'Bhaktapur Durbar Square',
                'is_active' => true,
            ],
            [
                'slug' => 'pokhara-lakeside-explorer',
                'title' => 'Pokhara Lakeside Explorer',
                'route_type' => 'itinerary',
                'difficulty' => 'easy',
                'description' => "A relaxed tour of Pokhara's lakeside: sunrise over the Annapurna range from Sarangkot, boating on Phewa Lake, the World Peace Pagoda, Davis Falls and Gupteshwor Cave.",
                'duration_days' => 2,
                'best_season' => 'Oct–Apr',
                'max_altitude_m' => 1592,
                'total_distance_km' => 20,
                'elevation_gain_m' => 200,
                'starting_point' => 'Lakeside, Pokhara',
                'ending_point' => 'Lakeside, Pokhara',
                'is_active' => true,
            ],
        ];

        foreach ($routes as $route) {
            CuratedRoute::updateOrCreate(['slug' => $route['slug']], $route);
        }

        $this->command->info('Seeded ' . count($routes) . ' curated routes (trekking + itineraries).');
    }
}
