<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * BUG FIX: Coin precision mismatch.
     *
     * The canonical earning calculation rounds to 4 decimal places
     * (CoinService: gross x user_share_percent / coin_to_npr_rate, round(...,4)),
     * and the audit tables (ad_revenue_ledger, ad_reward_events) already store
     * DECIMAL(12,4). The wallet/transaction/withdrawal columns were DECIMAL(12,2),
     * so MySQL silently rounded e.g. 0.0235 -> 0.02 at write time, creating a
     * wallet-vs-ledger drift of 0.0035 Coins per impression.
     *
     * This migration widens coin-denominated columns to DECIMAL(12,4).
     * Existing stored values are preserved as-is (no value rewriting).
     * NPR money columns are deliberately NOT touched.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE oripori_coin_wallets MODIFY balance DECIMAL(12,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE oripori_coin_wallets MODIFY total_earned DECIMAL(12,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE oripori_coin_wallets MODIFY total_withdrawn DECIMAL(12,4) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE coin_transactions MODIFY amount DECIMAL(12,4) NOT NULL');

        // withdrawals.amount is coin-denominated ("X Coins" in WithdrawalController)
        DB::statement('ALTER TABLE withdrawals MODIFY amount DECIMAL(12,4) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE oripori_coin_wallets MODIFY balance DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE oripori_coin_wallets MODIFY total_earned DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE oripori_coin_wallets MODIFY total_withdrawn DECIMAL(12,2) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE coin_transactions MODIFY amount DECIMAL(12,2) NOT NULL');

        DB::statement('ALTER TABLE withdrawals MODIFY amount DECIMAL(12,2) NOT NULL');
    }
};
