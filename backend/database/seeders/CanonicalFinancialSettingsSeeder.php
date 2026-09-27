<?php

namespace Database\Seeders;

use App\Models\CoinSetting;
use App\Models\GameSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CanonicalFinancialSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Updating financial settings to canonical values...');

        // ── Game Settings (ad pricing) ──
        $this->upsertGameSetting('ad_cpm', '50', 'CPM in NPR (advertiser cost per 1000 impressions)');
        $this->upsertGameSetting('ad_cpc', '0.50', 'CPC in NPR (advertiser cost per click)');

        // ── Coin Settings (user rewards) ──
        // Canonical: impression_value and click_value are DERIVED from CPM/CPC × user_share%
        // But we store them explicitly for backward compatibility
        $this->upsertCoinSetting('impression_value', '0.0235', 'Coins earned per ad impression (Rs.0.05 × 47%)');
        $this->upsertCoinSetting('click_value', '0.235', 'Coins earned per ad click (Rs.0.50 × 47%)');
        $this->upsertCoinSetting('user_share_percent', '47', 'User share of ad revenue (%)');
        $this->upsertCoinSetting('admin_share_percent', '53', 'Platform share of ad revenue (%)');
        $this->upsertCoinSetting('coin_to_npr_rate', '1', 'Conversion rate: 1 coin = X NPR');
        $this->upsertCoinSetting('daily_earning_cap', '500', 'Max coins per user per day');
        $this->upsertCoinSetting('daily_impression_cap', '1000', 'Max coin-earning impressions per report per day');
        $this->upsertCoinSetting('impression_cooldown_minutes', '10', 'Minutes between same user/ad coin credit');
        $this->upsertCoinSetting('min_withdrawal_esewa', '100', 'Minimum withdrawal via eSewa (coins)');
        $this->upsertCoinSetting('min_withdrawal_khalti', '100', 'Minimum withdrawal via Khalti (coins)');
        $this->upsertCoinSetting('min_withdrawal_bank', '500', 'Minimum withdrawal via Bank (coins)');

        $this->command->info('Canonical financial settings updated.');
    }

    private function upsertGameSetting(string $key, string $value, string $description = ''): void
    {
        $existing = GameSetting::where('key', $key)->first();
        if ($existing) {
            if ($existing->value != $value) {
                $this->command->info("  game_settings.{$key}: {$existing->value} → {$value}");
                $existing->update(['value' => $value]);
            } else {
                $this->command->info("  game_settings.{$key}: already {$value}");
            }
        } else {
            GameSetting::create(['key' => $key, 'value' => $value]);
            $this->command->info("  game_settings.{$key}: created with {$value}");
        }
    }

    private function upsertCoinSetting(string $key, string $value, string $description = ''): void
    {
        $existing = CoinSetting::where('key', $key)->first();
        if ($existing) {
            if ($existing->value != $value) {
                $this->command->info("  coin_settings.{$key}: {$existing->value} → {$value}");
                $existing->update(['value' => $value]);
            } else {
                $this->command->info("  coin_settings.{$key}: already {$value}");
            }
        } else {
            CoinSetting::create([
                'key' => $key,
                'value' => $value,
                'description' => $description,
            ]);
            $this->command->info("  coin_settings.{$key}: created with {$value}");
        }
    }
}
