<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformExpense;

class PlatformExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $expenses = [
            ['name' => 'AWS Hosting', 'category' => 'hosting', 'provider' => 'AWS', 'amount' => 8000, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(15), 'status' => 'active', 'notes' => 'EC2 + RDS production'],
            ['name' => 'Vercel Pro', 'category' => 'hosting', 'provider' => 'Vercel', 'amount' => 2000, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(20), 'status' => 'active', 'notes' => 'Frontend hosting'],
            ['name' => 'MySQL RDS', 'category' => 'database', 'provider' => 'AWS RDS', 'amount' => 5000, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(15), 'status' => 'active', 'notes' => 'db.t3.medium instance'],
            ['name' => 'nepalsmarttravel.com', 'category' => 'domain', 'provider' => 'Namecheap', 'amount' => 2000, 'billing_cycle' => 'yearly', 'next_renewal_date' => now()->addMonths(8), 'last_paid_date' => now()->subMonths(4), 'status' => 'active', 'notes' => 'Domain renewal'],
            ['name' => 'Google Maps API', 'category' => 'map_api', 'provider' => 'Google Cloud', 'amount' => 4000, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(12), 'status' => 'active', 'notes' => 'Maps + Geocoding + Places'],
            ['name' => 'SendGrid Email', 'category' => 'email', 'provider' => 'SendGrid', 'amount' => 1500, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(25), 'status' => 'active', 'notes' => 'Transactional emails'],
            ['name' => 'SMS Service', 'category' => 'sms', 'provider' => 'Twilio', 'amount' => 1000, 'billing_cycle' => 'pay_as_you_go', 'status' => 'active', 'notes' => 'OTP and notifications'],
            ['name' => 'Cloudflare CDN', 'category' => 'cdn', 'provider' => 'Cloudflare', 'amount' => 0, 'billing_cycle' => 'monthly', 'status' => 'active', 'notes' => 'Free tier'],
            ['name' => 'AWS S3 Storage', 'category' => 'storage', 'provider' => 'AWS S3', 'amount' => 1500, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(15), 'status' => 'active', 'notes' => 'Image and media storage'],
            ['name' => 'OpenAI API', 'category' => 'ai_api', 'provider' => 'OpenAI', 'amount' => 3000, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(8), 'status' => 'active', 'notes' => 'Content moderation AI'],
            ['name' => 'Apple Developer', 'category' => 'apple_developer', 'provider' => 'Apple', 'amount' => 2000, 'billing_cycle' => 'yearly', 'next_renewal_date' => now()->addMonths(10), 'status' => 'active', 'notes' => 'iOS app distribution'],
            ['name' => 'Google Play Console', 'category' => 'google_play', 'provider' => 'Google', 'amount' => 2000, 'billing_cycle' => 'one_time', 'status' => 'active', 'notes' => 'One-time registration fee'],
            ['name' => 'Google Ads', 'category' => 'advertising', 'provider' => 'Google', 'amount' => 5000, 'billing_cycle' => 'monthly', 'next_renewal_date' => now()->addDays(5), 'status' => 'active', 'notes' => 'User acquisition campaigns'],
        ];

        foreach ($expenses as $data) {
            PlatformExpense::create($data);
        }
    }
}
