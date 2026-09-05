<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Place;
use App\Models\PlaceCategories;
use Illuminate\Support\Str;

class ImportOsmPlaces extends Command
{
    protected $signature = 'places:import-osm 
        {--radius=10 : Search radius in km around each city}
        {--city= : Single city to import (default: all major Nepali cities)}
        {--limit=200 : Max places per city}
        {--delay=3 : Seconds to wait between cities}
        {--retries=3 : Max retries per city on 429/504}';

    protected $description = 'Import nearby places from OpenStreetMap for Nepal and store in the database';

    private array $nepalCities = [
        // Province 1
        ['name' => 'Biratnagar', 'lat' => 26.4833, 'lng' => 87.2833],
        ['name' => 'Dharan', 'lat' => 26.8167, 'lng' => 87.2833],
        ['name' => 'Bhadrapur', 'lat' => 26.5500, 'lng' => 88.0833],
        ['name' => 'Itahari', 'lat' => 26.6667, 'lng' => 87.2833],
        ['name' => 'Damak', 'lat' => 26.6500, 'lng' => 87.9333],
        ['name' => 'Kakarbhitta', 'lat' => 26.4833, 'lng' => 88.1167],
        ['name' => 'Inaruwa', 'lat' => 26.6167, 'lng' => 87.1333],
        ['name' => 'Gaighat', 'lat' => 26.8000, 'lng' => 86.6000],
        ['name' => 'Udaipur', 'lat' => 26.9167, 'lng' => 86.5333],
        ['name' => 'Taplejung', 'lat' => 27.3500, 'lng' => 87.6667],
        ['name' => 'Ilam', 'lat' => 26.9167, 'lng' => 87.9333],
        ['name' => 'Phidim', 'lat' => 27.1500, 'lng' => 87.8333],
        ['name' => 'Mechinagar', 'lat' => 26.4667, 'lng' => 88.0167],
        ['name' => 'Kanchanbari', 'lat' => 26.5000, 'lng' => 88.0500],
        ['name' => 'Birtamod', 'lat' => 26.6333, 'lng' => 87.9833],
        ['name' => 'Hile', 'lat' => 26.9833, 'lng' => 87.4333],
        ['name' => 'Rangeli', 'lat' => 26.3667, 'lng' => 86.8667],
        ['name' => 'Supaul', 'lat' => 26.1333, 'lng' => 86.7500],
        ['name' => 'Siraha', 'lat' => 26.6500, 'lng' => 86.2000],
        ['name' => 'Lahan', 'lat' => 26.6833, 'lng' => 86.4833],
        ['name' => 'Rajbiraj', 'lat' => 26.5333, 'lng' => 86.7500],
        ['name' => 'Janakpur', 'lat' => 26.7286, 'lng' => 85.9248],
        ['name' => 'Jaleshwar', 'lat' => 26.6500, 'lng' => 85.8000],
        ['name' => 'Malangawa', 'lat' => 26.8333, 'lng' => 85.5667],
        ['name' => 'Bardibas', 'lat' => 26.8167, 'lng' => 85.9500],
        ['name' => 'Bhisamupur', 'lat' => 26.5833, 'lng' => 86.0500],
        ['name' => 'Kolhapur', 'lat' => 26.2000, 'lng' => 86.1000],
        ['name' => 'Kusaha', 'lat' => 26.6167, 'lng' => 86.9333],
        ['name' => 'Shambhunath', 'lat' => 26.4667, 'lng' => 86.6667],
        ['name' => 'Chandranath', 'lat' => 26.8000, 'lng' => 86.1500],
        ['name' => 'Dhanushadham', 'lat' => 26.7833, 'lng' => 86.0500],
        ['name' => 'Kurtha', 'lat' => 26.6500, 'lng' => 85.9833],
        ['name' => 'Ganeshman', 'lat' => 26.6833, 'lng' => 86.3833],
        ['name' => 'Bhedihari', 'lat' => 26.7500, 'lng' => 86.3333],

        // Bagmati Province
        ['name' => 'Kathmandu', 'lat' => 27.7172, 'lng' => 85.3240],
        ['name' => 'Lalitpur', 'lat' => 27.6667, 'lng' => 85.3333],
        ['name' => 'Bhaktapur', 'lat' => 27.6722, 'lng' => 85.4278],
        ['name' => 'Bharatpur', 'lat' => 27.6833, 'lng' => 84.4333],
        ['name' => 'Hetauda', 'lat' => 27.4167, 'lng' => 85.0333],
        ['name' => 'Chitwan', 'lat' => 27.5290, 'lng' => 84.3540],
        ['name' => 'Sindhuli', 'lat' => 27.2500, 'lng' => 85.9667],
        ['name' => 'Dolalghat', 'lat' => 27.6167, 'lng' => 85.5000],
        ['name' => 'Dhulikhel', 'lat' => 27.6167, 'lng' => 85.5500],
        ['name' => 'Banepa', 'lat' => 27.6333, 'lng' => 85.5333],
        ['name' => 'Panauti', 'lat' => 27.5833, 'lng' => 85.5167],
        ['name' => 'Temal', 'lat' => 27.8667, 'lng' => 85.7000],
        ['name' => 'Melamchi', 'lat' => 27.9000, 'lng' => 85.5833],
        ['name' => 'Sindhupalchok', 'lat' => 27.9500, 'lng' => 85.6333],
        ['name' => 'Barabise', 'lat' => 27.8333, 'lng' => 85.8833],
        ['name' => 'Dhunche', 'lat' => 28.1167, 'lng' => 85.3000],
        ['name' => 'Langtang', 'lat' => 28.2167, 'lng' => 85.4667],
        ['name' => 'Rasuwa', 'lat' => 28.0833, 'lng' => 85.3000],
        ['name' => 'Gorkha', 'lat' => 28.0000, 'lng' => 84.6333],
        ['name' => 'Manakamana', 'lat' => 27.9833, 'lng' => 84.5500],
        ['name' => 'Bandipur', 'lat' => 27.9333, 'lng' => 84.4167],
        ['name' => 'Dhading', 'lat' => 27.8667, 'lng' => 84.9333],
        ['name' => 'Nuwakot', 'lat' => 28.0333, 'lng' => 85.1833],
        ['name' => 'Trisuli', 'lat' => 27.8833, 'lng' => 84.9667],
        ['name' => 'Makwanpur', 'lat' => 27.4167, 'lng' => 85.0333],
        ['name' => 'Kailali', 'lat' => 28.7500, 'lng' => 80.9167],
        ['name' => 'Bagmati', 'lat' => 27.5500, 'lng' => 85.3167],
        ['name' => 'Godavari', 'lat' => 27.6000, 'lng' => 85.3667],
        ['name' => 'Changunarayan', 'lat' => 27.7167, 'lng' => 85.4333],
        ['name' => 'Thimi', 'lat' => 27.6833, 'lng' => 85.3833],
        ['name' => 'Sankhu', 'lat' => 27.7500, 'lng' => 85.4500],
        ['name' => 'Patan', 'lat' => 27.6728, 'lng' => 85.3253],
        ['name' => 'Kirtipur', 'lat' => 27.6700, 'lng' => 85.2833],
        ['name' => 'Bungmati', 'lat' => 27.6333, 'lng' => 85.3167],
        ['name' => 'Pharping', 'lat' => 27.5833, 'lng' => 85.2667],
        ['name' => 'Dakshinkali', 'lat' => 27.5667, 'lng' => 85.2500],

        // Gandaki Province
        ['name' => 'Pokhara', 'lat' => 28.2096, 'lng' => 83.9856],
        ['name' => 'Lumbini', 'lat' => 27.4840, 'lng' => 83.2740],
        ['name' => 'Tansen', 'lat' => 27.8667, 'lng' => 83.5500],
        ['name' => 'Jomsom', 'lat' => 28.7833, 'lng' => 83.7333],
        ['name' => 'Namche Bazaar', 'lat' => 27.8050, 'lng' => 86.7167],
        ['name' => 'Syangja', 'lat' => 28.1000, 'lng' => 83.8667],
        ['name' => 'Tanahu', 'lat' => 27.9333, 'lng' => 84.4167],
        ['name' => 'Lamjung', 'lat' => 28.2333, 'lng' => 84.3833],
        ['name' => 'Kaski', 'lat' => 28.2000, 'lng' => 83.9833],
        ['name' => 'Parbat', 'lat' => 28.2500, 'lng' => 83.6667],
        ['name' => 'Myagdi', 'lat' => 28.4667, 'lng' => 83.5500],
        ['name' => 'Mustang', 'lat' => 28.8000, 'lng' => 83.7500],
        ['name' => 'Baglung', 'lat' => 28.2667, 'lng' => 83.5833],
        ['name' => 'Gulmi', 'lat' => 28.0833, 'lng' => 83.5167],
        ['name' => 'Arghakhanchi', 'lat' => 27.9500, 'lng' => 83.2000],
        ['name' => 'Palpa', 'lat' => 27.8667, 'lng' => 83.5500],
        ['name' => 'Rupandehi', 'lat' => 27.5000, 'lng' => 83.3833],
        ['name' => 'Kapilvastu', 'lat' => 27.5833, 'lng' => 83.0500],
        ['name' => 'Nawalparasi', 'lat' => 27.5500, 'lng' => 83.7167],
        ['name' => 'Besisahar', 'lat' => 28.2333, 'lng' => 84.3833],
        ['name' => 'Chame', 'lat' => 28.5500, 'lng' => 84.2333],
        ['name' => 'Manang', 'lat' => 28.6667, 'lng' => 84.0167],
        ['name' => 'Marpha', 'lat' => 28.7500, 'lng' => 83.7333],
        ['name' => 'Kagbeni', 'lat' => 28.8333, 'lng' => 83.7833],
        ['name' => 'Muktinath', 'lat' => 28.8167, 'lng' => 83.8667],
        ['name' => 'Thorong', 'lat' => 28.7667, 'lng' => 83.8667],
        ['name' => 'Tatopani', 'lat' => 28.5000, 'lng' => 83.5833],
        ['name' => 'Ghorepani', 'lat' => 28.4000, 'lng' => 83.7000],
        ['name' => 'Poon Hill', 'lat' => 28.3967, 'lng' => 83.6950],
        ['name' => 'Sarangkot', 'lat' => 28.2433, 'lng' => 83.9550],
        ['name' => 'Lakeside', 'lat' => 28.2000, 'lng' => 83.9600],
        ['name' => 'Baidam', 'lat' => 28.2100, 'lng' => 83.9700],
        ['name' => 'Phewa', 'lat' => 28.1900, 'lng' => 83.9700],
        ['name' => 'Rupse', 'lat' => 28.5000, 'lng' => 83.4833],
        ['name' => 'Andhi Khola', 'lat' => 28.1833, 'lng' => 83.9333],

        // Lumbini Province (already covered above, adding more)
        ['name' => 'Siddharthanagar', 'lat' => 27.5000, 'lng' => 83.4500],
        ['name' => 'Tilottama', 'lat' => 27.5667, 'lng' => 83.4833],
        ['name' => 'Devdaha', 'lat' => 27.5500, 'lng' => 83.5167],
        ['name' => 'Sainamaina', 'lat' => 27.5333, 'lng' => 83.4167],
        ['name' => 'Suyardi', 'lat' => 27.5167, 'lng' => 83.4000],
        ['name' => 'Kotahimai', 'lat' => 27.4333, 'lng' => 83.3833],
        ['name' => 'Marchawari', 'lat' => 27.4500, 'lng' => 83.3500],
        ['name' => 'Ramgram', 'lat' => 27.6000, 'lng' => 83.3000],
        ['name' => 'Sunwal', 'lat' => 27.5833, 'lng' => 83.4667],
        ['name' => 'Swaroopnagar', 'lat' => 27.5667, 'lng' => 83.3833],
        ['name' => 'Pratappur', 'lat' => 27.6167, 'lng' => 83.4333],
        ['name' => 'Butwal', 'lat' => 27.7000, 'lng' => 83.4667],
        ['name' => 'Lumbini Sanskritik', 'lat' => 27.4833, 'lng' => 83.2833],
        ['name' => 'Buddhigram', 'lat' => 27.4750, 'lng' => 83.2750],
        ['name' => 'Mayadevi', 'lat' => 27.4667, 'lng' => 83.2500],
        ['name' => 'Siddharthanagar', 'lat' => 27.4900, 'lng' => 83.4400],
        ['name' => 'Shivaraj', 'lat' => 27.5167, 'lng' => 83.5167],
        ['name' => 'Stera', 'lat' => 27.5333, 'lng' => 83.5500],
        ['name' => 'Murgiya', 'lat' => 27.5500, 'lng' => 83.5333],

        // Karnali Province
        ['name' => 'Nepalgunj', 'lat' => 28.0500, 'lng' => 81.6167],
        ['name' => 'Surkhet', 'lat' => 28.6333, 'lng' => 81.6000],
        ['name' => 'Jumla', 'lat' => 29.2747, 'lng' => 82.1833],
        ['name' => 'Dolpa', 'lat' => 28.9500, 'lng' => 82.8333],
        ['name' => 'Humla', 'lat' => 29.6000, 'lng' => 81.8333],
        ['name' => 'Mugu', 'lat' => 29.5333, 'lng' => 82.0833],
        ['name' => 'Jumla', 'lat' => 29.2747, 'lng' => 82.1833],
        ['name' => 'Kalikot', 'lat' => 29.1333, 'lng' => 81.7167],
        ['name' => 'Dailekh', 'lat' => 28.8667, 'lng' => 81.7000],
        ['name' => 'Jajarkot', 'lat' => 28.7833, 'lng' => 82.1333],
        ['name' => 'Bardiya', 'lat' => 28.4167, 'lng' => 81.5000],
        ['name' => 'Tikapur', 'lat' => 28.5000, 'lng' => 81.1333],
        ['name' => 'Lamahi', 'lat' => 28.4500, 'lng' => 81.4333],
        ['name' => 'Gulariya', 'lat' => 28.3667, 'lng' => 81.5500],
        ['name' => 'Rajapur', 'lat' => 28.4833, 'lng' => 81.1500],
        ['name' => 'Manma', 'lat' => 29.1333, 'lng' => 81.5667],
        ['name' => 'Sinja', 'lat' => 29.2333, 'lng' => 82.0500],
        ['name' => 'Chaurpati', 'lat' => 28.8333, 'lng' => 81.5167],
        ['name' => 'Birendranagar', 'lat' => 28.6000, 'lng' => 81.6333],
        ['name' => 'Babai', 'lat' => 28.3500, 'lng' => 81.4500],
        ['name' => 'Dang', 'lat' => 28.0333, 'lng' => 82.4833],
        ['name' => 'Ghorahi', 'lat' => 28.0333, 'lng' => 82.4833],
        ['name' => 'Tulsipur', 'lat' => 28.0167, 'lng' => 82.3500],
        ['name' => 'Lamahi', 'lat' => 28.1333, 'lng' => 82.4167],
        ['name' => 'Salyan', 'lat' => 28.3833, 'lng' => 82.2000],
        ['name' => ' Musikot', 'lat' => 28.4000, 'lng' => 82.1833],
        ['name' => 'Rolpa', 'lat' => 28.3333, 'lng' => 82.6167],
        ['name' => 'Tamghas', 'lat' => 28.3500, 'lng' => 82.6500],
        ['name' => 'Rukum', 'lat' => 28.5000, 'lng' => 82.5000],
        ['name' => 'Musikot', 'lat' => 28.4833, 'lng' => 82.4667],
        ['name' => 'Pyuthan', 'lat' => 28.2833, 'lng' => 82.9333],
        ['name' => 'Sitaula', 'lat' => 28.3000, 'lng' => 82.9167],
        ['name' => 'Archalbot', 'lat' => 28.2667, 'lng' => 82.9500],

        // Sudurpashchim Province
        ['name' => 'Dhangadhi', 'lat' => 28.6833, 'lng' => 80.6167],
        ['name' => 'Mahendranagar', 'lat' => 28.9667, 'lng' => 80.2333],
        ['name' => 'Godawari', 'lat' => 28.8833, 'lng' => 80.5833],
        ['name' => 'Dipayal', 'lat' => 29.1833, 'lng' => 80.9500],
        ['name' => 'Darchula', 'lat' => 29.8333, 'lng' => 80.5500],
        ['name' => 'Baitadi', 'lat' => 29.5333, 'lng' => 80.5833],
        ['name' => 'Dadeldhura', 'lat' => 29.3000, 'lng' => 80.5833],
        ['name' => 'Doti', 'lat' => 29.2667, 'lng' => 80.9333],
        ['name' => 'Kailali', 'lat' => 28.7500, 'lng' => 80.9167],
        ['name' => 'Kanchanpur', 'lat' => 28.8833, 'lng' => 80.2000],
        ['name' => 'Bhimdatta', 'lat' => 28.9500, 'lng' => 80.2167],
        ['name' => 'Shuklaphanta', 'lat' => 28.9167, 'lng' => 80.2667],
        ['name' => 'Lamki', 'lat' => 28.7333, 'lng' => 80.9500],
        ['name' => 'Attariya', 'lat' => 28.7167, 'lng' => 80.9667],
        ['name' => 'Ghodaghodi', 'lat' => 28.7500, 'lng' => 80.8833],
        ['name' => 'Daiji', 'lat' => 28.7333, 'lng' => 80.8667],
        ['name' => 'Banka', 'lat' => 28.7667, 'lng' => 80.9000],
        ['name' => 'Jhulaghat', 'lat' => 29.0333, 'lng' => 80.2333],
        ['name' => 'Mahakali', 'lat' => 29.6667, 'lng' => 80.5500],
        ['name' => 'Api Himal Base Camp', 'lat' => 29.9500, 'lng' => 80.9333],

        // Province 2 (already covered above)
        ['name' => 'Kurintar', 'lat' => 27.8500, 'lng' => 84.9833],
        ['name' => 'Naubise', 'lat' => 27.7833, 'lng' => 85.1167],
        ['name' => 'Thankot', 'lat' => 27.7167, 'lng' => 85.2167],
        ['name' => 'Chobar', 'lat' => 27.6667, 'lng' => 85.2667],
        ['name' => 'Nagarkot', 'lat' => 27.7167, 'lng' => 85.5167],
        ['name' => 'Changu', 'lat' => 27.7000, 'lng' => 85.4500],
        ['name' => 'Tokha', 'lat' => 27.7333, 'lng' => 85.3500],
        ['name' => 'Shankharapur', 'lat' => 27.7500, 'lng' => 85.4000],
        ['name' => 'Gokarneshwor', 'lat' => 27.7333, 'lng' => 85.3833],
        ['name' => 'Kageshwari', 'lat' => 27.7167, 'lng' => 85.3667],
        ['name' => 'Tkot', 'lat' => 27.6833, 'lng' => 85.3667],
        ['name' => 'Chandragiri', 'lat' => 27.7000, 'lng' => 85.2500],
        ['name' => 'Dakshinkali', 'lat' => 27.5833, 'lng' => 85.2500],
        ['name' => 'Nagarkot View Tower', 'lat' => 27.7150, 'lng' => 85.5100],
        ['name' => 'Lele', 'lat' => 27.5833, 'lng' => 85.3500],
        ['name' => 'Khokana', 'lat' => 27.6333, 'lng' => 85.2833],
        ['name' => 'Bungamati', 'lat' => 27.6333, 'lng' => 85.2833],
        ['name' => 'Dholalghat', 'lat' => 27.6167, 'lng' => 85.5000],
        ['name' => 'Panchkhal', 'lat' => 27.6500, 'lng' => 85.5333],
        ['name' => 'Dolalghat Bridge', 'lat' => 27.6167, 'lng' => 85.5000],
    ];

    public function handle(): int
    {
        set_time_limit(0);

        $radius = (int) $this->option('radius');
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');
        $retries = (int) $this->option('retries');
        $specificCity = $this->option('city');

        // Ensure "All" category exists for OSM places
        $allCat = PlaceCategories::firstOrCreate(
            ['name' => 'All'],
            ['name' => 'All', 'icon' => 'explore']
        );

        // Ensure other necessary categories exist
        $categories = [
            'Attractions', 'Hotels', 'Restaurants', 'Cafe',
            'Emergency', 'ATMs', 'Fuel', 'Activities',
            'Transport', 'Shopping', 'Services', 'Education',
            'Entertainment', 'Hospital', 'Clinic', 'Pharmacy',
            'Bank', 'Parking', 'Recreation', 'Nature',
        ];
        foreach ($categories as $catName) {
            PlaceCategories::firstOrCreate(
                ['name' => $catName],
                ['name' => $catName, 'icon' => strtolower($catName)]
            );
        }

        $cities = $specificCity
            ? [collect($this->nepalCities)->firstWhere('name', $specificCity)]
            : $this->nepalCities;

        if ($specificCity && !$cities[0]) {
            $this->error("City '$specificCity' not found in predefined list.");
            return 1;
        }

        $totalImported = 0;
        $totalSkipped = 0;

        $bar = $this->output->createProgressBar(count($cities));
        $bar->start();

        foreach ($cities as $i => $city) {
            if ($i > 0 && $delay > 0) {
                $this->line("  Waiting {$delay}s to avoid rate limit...");
                sleep($delay);
            }
            $result = $this->importCityPlaces($city, $radius, $limit, $retries);
            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Import complete: {$totalImported} imported, {$totalSkipped} skipped (duplicates).");

        return 0;
    }

    private function importCityPlaces(array $city, int $radius, int $limit, int $maxRetries = 3): array
    {
        $radiusMeters = $radius * 1000;
        $lat = $city['lat'];
        $lng = $city['lng'];
        $cityName = $city['name'];

        $this->line("\nFetching OSM data for {$cityName}...");

        $overpassQuery = $this->buildOverpassQuery($lat, $lng, $radiusMeters, $limit);

        $responseBody = null;
        $httpCode = 0;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: NepalSmartTravel/1.0",
                        'content' => 'data=' . urlencode($overpassQuery),
                        'timeout' => 120,
                        'ignore_errors' => true,
                    ]
                ];
                $context = stream_context_create($opts);
                $responseBody = @file_get_contents('https://overpass-api.de/api/interpreter', false, $context);

                if ($responseBody === false) {
                    if ($attempt < $maxRetries) {
                        $wait = $attempt * 5;
                        $this->warn("  ⚠ Connection failed for {$cityName}, retrying in {$wait}s ({$attempt}/{$maxRetries})...");
                        sleep($wait);
                        continue;
                    }
                    $this->warn("  ⚠ Overpass API connection failed for {$cityName}");
                    return ['imported' => 0, 'skipped' => 0];
                }

                $httpCode = 200;
                if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
                    $httpCode = (int)$m[0];
                }

                if ($httpCode === 429 || $httpCode === 504) {
                    if ($attempt < $maxRetries) {
                        $wait = $attempt * 5;
                        $this->warn("  ⚠ Overpass API returned {$httpCode} for {$cityName}, retrying in {$wait}s ({$attempt}/{$maxRetries})...");
                        sleep($wait);
                        continue;
                    }
                    $this->warn("  ⚠ Overpass API returned status {$httpCode} for {$cityName} (gave up after {$maxRetries} retries)");
                    return ['imported' => 0, 'skipped' => 0];
                }

                if ($httpCode !== 200) {
                    $this->warn("  ⚠ Overpass API returned status {$httpCode} for {$cityName}");
                    return ['imported' => 0, 'skipped' => 0];
                }

                break;

            } catch (\Exception $e) {
                if ($attempt < $maxRetries) {
                    $wait = $attempt * 5;
                    $this->warn("  ⚠ Error for {$cityName}: {$e->getMessage()}, retrying in {$wait}s ({$attempt}/{$maxRetries})...");
                    sleep($wait);
                    continue;
                }
                $this->error("  ✗ Overpass API error for {$cityName}: {$e->getMessage()}");
                return ['imported' => 0, 'skipped' => 0];
            }
        }

        try {
            $data = json_decode($responseBody, true);
            $elements = $data['elements'] ?? [];

            if (empty($elements)) {
                $this->warn("  ⚠ No OSM data returned for {$cityName}");
                return ['imported' => 0, 'skipped' => 0];
            }

            $imported = 0;
            $skipped = 0;

            foreach ($elements as $element) {
                $tags = $element['tags'] ?? [];
                $elemLat = $element['lat'] ?? null;
                $elemLng = $element['lon'] ?? null;

                if (!$elemLat || !$elemLng) continue;

                $name = $tags['name'] ?? $tags['name:en'] ?? null;
                if (!$name) continue;

                $osmId = $element['type'] . '/' . $element['id'];

                // Skip if already imported
                if (Place::where('osm_id', $osmId)->exists()) {
                    $skipped++;
                    continue;
                }

                // Legacy merge: legacy admin-created row (no osm_id) at the same spot
                // and same name gets the osm_id attached instead of inserting a duplicate.
                $legacy = Place::whereNull('osm_id')
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
                    ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= 0.2",
                        [$elemLat, $elemLng, $elemLat])
                    ->first();
                if ($legacy) {
                    $legacy->update(['osm_id' => $osmId, 'source' => $legacy->source === 'admin' ? 'osm' : $legacy->source]);
                    $imported++;
                    $this->line("  ↦ Linked osm_id {$osmId} to existing place #{$legacy->id} ('{$name}')");
                    continue;
                }

                $category = $this->osmToCategory($tags);
                $categoryId = $this->getCategoryId($category);

                $address = implode(', ', array_filter([
                    $tags['addr:street'] ?? null,
                    $tags['addr:city'] ?? $tags['addr:district'] ?? $cityName,
                ]));

                $phone = $tags['phone'] ?? $tags['contact:phone'] ?? null;
                $website = $tags['website'] ?? $tags['contact:website'] ?? null;
                $description = $tags['description'] ?? $tags['note'] ?? null;
                $rating = isset($tags['rating']) ? (float)$tags['rating'] : null;

                try {
                    Place::create([
                        'uuid' => (string) Str::uuid(),
                        'name' => $name,
                        'description' => $description,
                        'address' => $address ?: null,
                        'district' => $tags['addr:city'] ?? $tags['addr:district'] ?? $cityName,
                        'latitude' => $elemLat,
                        'longitude' => $elemLng,
                        'category_id' => $categoryId,
                        'phone' => $phone,
                        'website' => $website,
                        'average_rating' => $rating ?? 0.0,
                        'is_verified' => false,
                        'is_featured' => false,
                        'is_active' => true,
                        'source' => 'osm',
                        'osm_id' => $osmId,
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $this->warn("  ⚠ Failed to import '{$name}': {$e->getMessage()}");
                }
            }

            $this->info("  ✓ {$cityName}: {$imported} imported, {$skipped} skipped");
            return compact('imported', 'skipped');

        } catch (\Exception $e) {
            $this->error("  ✗ Overpass API error for {$cityName}: {$e->getMessage()}");
            return ['imported' => 0, 'skipped' => 0];
        }
    }

    private function buildOverpassQuery(float $lat, float $lng, int $radiusMeters, int $limit): string
    {
        $amenityTypes = [
            'restaurant', 'cafe', 'fast_food', 'pub', 'bar',
            'hotel', 'motel', 'hostel', 'guest_house',
            'hospital', 'clinic', 'pharmacy', 'doctors', 'blood_bank',
            'bank', 'atm', 'fuel', 'taxi',
            'police', 'fire_station',
            'bus_station', 'ferry_terminal', 'parking',
            'post_office', 'library', 'place_of_worship',
            'school', 'university', 'college',
            'marketplace', 'theatre', 'cinema', 'community_centre',
        ];

        $tourismTypes = [
            'attraction', 'hotel', 'motel', 'hostel',
            'guest_house', 'information', 'museum',
            'viewpoint', 'picnic_site', 'camp_site',
            'caravan_site', 'wilderness_hut', 'alpine_hut',
            'artwork', 'gallery', 'theme_park', 'zoo',
        ];

        $shopTypes = [
            'supermarket', 'convenience', 'mall', 'department_store',
            'clothes', 'electronics', 'gift', 'souvenir',
        ];

        $queries = [];

        // Amenities query
        $queries[] = "node[\"amenity\"~\"" . implode('|', $amenityTypes) . "\"](around:{$radiusMeters},{$lat},{$lng});";

        // Tourism query
        $queries[] = "node[\"tourism\"~\"" . implode('|', $tourismTypes) . "\"](around:{$radiusMeters},{$lat},{$lng});";

        // Shops query
        $queries[] = "node[\"shop\"~\"" . implode('|', $shopTypes) . "\"](around:{$radiusMeters},{$lat},{$lng});";

        // Leisure
        $queries[] = "node[\"leisure\"](around:{$radiusMeters},{$lat},{$lng});";

        // Historic
        $queries[] = "node[\"historic\"](around:{$radiusMeters},{$lat},{$lng});";

        // Natural viewpoints
        $queries[] = "node[\"natural\"~\"peak|volcano|bay|cape|beach\"](around:{$radiusMeters},{$lat},{$lng});";

        return "[out:json];(" . implode('', $queries) . ");out body {$limit};";
    }

    private function osmToCategory(array $tags): string
    {
        $amenity = $tags['amenity'] ?? null;
        $tourism = $tags['tourism'] ?? null;
        $shop = $tags['shop'] ?? null;
        $leisure = $tags['leisure'] ?? null;
        $historic = $tags['historic'] ?? null;

        if ($amenity) {
            return match($amenity) {
                'restaurant', 'fast_food' => 'Restaurants',
                'cafe' => 'Cafe',
                'pub', 'bar' => 'Restaurants',
                'hotel', 'motel', 'hostel', 'guest_house' => 'Hotels',
                'hospital', 'clinic', 'doctors' => 'Hospital',
                'pharmacy' => 'Pharmacy',
                'blood_bank' => 'Blood Bank',
                'bank' => 'Bank',
                'atm' => 'ATMs',
                'fuel' => 'Fuel',
                'taxi', 'bus_station', 'ferry_terminal' => 'Transport',
                'police', 'fire_station' => 'Emergency',
                'parking' => 'Parking',
                'marketplace' => 'Shopping',
                'theatre', 'cinema', 'community_centre' => 'Entertainment',
                'post_office', 'library' => 'Services',
                'school', 'university', 'college' => 'Education',
                'place_of_worship' => 'Attractions',
                default => 'Services',
            };
        }
        if ($tourism) {
            return match($tourism) {
                'hotel', 'motel', 'hostel', 'guest_house' => 'Hotels',
                'museum', 'attraction', 'artwork', 'gallery', 'theme_park', 'zoo' => 'Attractions',
                'viewpoint' => 'Nature',
                'camp_site', 'picnic_site' => 'Activities',
                'information' => 'Services',
                default => 'Attractions',
            };
        }
        if ($shop) return 'Shopping';
        if ($leisure) return 'Recreation';
        if ($historic) return 'Attractions';
        return 'Attractions';
    }

    private function getCategoryId(string $categoryName): ?int
    {
        $cat = PlaceCategories::where('name', $categoryName)->first();
        return $cat ? $cat->id : null;
    }
}