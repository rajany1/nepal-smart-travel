<?php

namespace Database\Seeders;

use App\Models\LegalDocumentType;
use Illuminate\Database\Seeder;

class LegalDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['slug' => 'privacy_policy', 'label' => 'Privacy Policy', 'sort_order' => 1],
            ['slug' => 'terms_conditions', 'label' => 'Terms & Conditions', 'sort_order' => 2],
            ['slug' => 'about', 'label' => 'About', 'sort_order' => 3],
            ['slug' => 'community_guidelines', 'label' => 'Community Guidelines', 'sort_order' => 4],
            ['slug' => 'content_policy', 'label' => 'Content Policy', 'sort_order' => 5],
            ['slug' => 'emergency_policy', 'label' => 'Emergency Policy', 'sort_order' => 6],
        ];

        foreach ($types as $type) {
            LegalDocumentType::firstOrCreate(
                ['slug' => $type['slug']],
                ['label' => $type['label'], 'sort_order' => $type['sort_order']]
            );
        }
    }
}
