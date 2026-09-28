<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * BUG FIX: ad/coin tables were MyISAM, so DB::transaction() and
     * lockForUpdate() were no-ops (MyISAM autocommits every statement and
     * cannot roll back). That made the impression/click settlement
     * non-atomic: a crash between the reward-event insert and the impression
     * insert left an orphan "validated" event behind, and the settlement
     * transaction could never actually undo anything.
     *
     * Converts the tables involved in the ad settlement + coin ledger flows
     * to InnoDB so a single DB::transaction is a real atomic unit and
     * row locks in FinancialLedgerService are real locks.
     */
    public function up(): void
    {
        foreach ([
            'ad_campaigns',
            'ad_impressions',
            'ad_clicks',
            'ad_reward_events',
            'ad_revenue_ledger',
            'coin_transactions',
            'oripori_coin_wallets',
            'withdrawals',
        ] as $table) {
            DB::statement("ALTER TABLE {$table} ENGINE=InnoDB");
        }
    }

    public function down(): void
    {
        foreach ([
            'withdrawals',
            'oripori_coin_wallets',
            'coin_transactions',
            'ad_revenue_ledger',
            'ad_reward_events',
            'ad_clicks',
            'ad_impressions',
            'ad_campaigns',
        ] as $table) {
            DB::statement("ALTER TABLE {$table} ENGINE=MyISAM");
        }
    }
};
