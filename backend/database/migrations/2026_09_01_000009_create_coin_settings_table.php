<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->decimal('value', 12, 2);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Default settings
        $settings = [
            ['key' => 'impression_value', 'value' => 0.05, 'description' => 'Coins per ad impression on report'],
            ['key' => 'click_value', 'value' => 0.50, 'description' => 'Coins per ad click on report'],
            ['key' => 'user_share_percent', 'value' => 70, 'description' => 'User gets 70% of ad revenue in Coins'],
            ['key' => 'admin_share_percent', 'value' => 30, 'description' => 'Admin keeps 30% of ad revenue'],
            ['key' => 'coin_to_npr_rate', 'value' => 1, 'description' => '1 Coin = 1 NPR for withdrawal'],
            ['key' => 'min_withdrawal_esewa', 'value' => 100, 'description' => 'Minimum withdrawal via eSewa (Coins)'],
            ['key' => 'min_withdrawal_khalti', 'value' => 100, 'description' => 'Minimum withdrawal via Khalti (Coins)'],
            ['key' => 'min_withdrawal_bank', 'value' => 500, 'description' => 'Minimum withdrawal via Bank (Coins)'],
            ['key' => 'daily_earning_cap', 'value' => 500, 'description' => 'Max coins per user per day'],
            ['key' => 'daily_impression_cap', 'value' => 1000, 'description' => 'Max coin-earning impressions per report per day'],
            ['key' => 'impression_cooldown_minutes', 'value' => 10, 'description' => 'Minutes before same user can earn again'],
        ];

        foreach ($settings as $setting) {
            DB::table('coin_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_settings');
    }
};
