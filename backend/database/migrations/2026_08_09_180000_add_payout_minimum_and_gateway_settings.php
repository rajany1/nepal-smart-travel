<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function defaults(): array
    {
        return [
            // Per-method payout minimums (Rs.)
            'payout_min_esewa' => '100',
            'payout_min_khalti' => '100',
            'payout_min_bank' => '500',
            // eSewa gateway (editable from admin Settings - no code changes needed)
            'gateway_esewa_merchant_code' => env('ESEWA_MERCHANT_CODE', 'EPAYTEST'),
            'gateway_esewa_secret_key' => env('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'),
            'gateway_esewa_sandbox' => env('ESEWA_SANDBOX', true) ? '1' : '0',
            // Khalti gateway
            'gateway_khalti_secret_key' => env('KHALTI_SECRET_KEY', ''),
            'gateway_khalti_public_key' => env('KHALTI_PUBLIC_KEY', ''),
            'gateway_khalti_sandbox' => env('KHALTI_SANDBOX', true) ? '1' : '0',
        ];
    }

    public function up(): void
    {
        foreach ($this->defaults() as $key => $value) {
            DB::table('game_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('game_settings')->whereIn('key', array_keys($this->defaults()))->delete();
    }
};
