<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * BUG FIX: user_agent varchar(100) caused HTTP 500 on realistic UAs.
     *
     * AppServiceProvider sets Schema::defaultStringLength(100), so every
     * $table->string('user_agent') was created as VARCHAR(100). A normal
     * 111-character Chrome UA overflows it (MySQL error 1406) and, in the ad
     * tracking endpoints, used to crash AFTER the reward event insert.
     *
     * Widens all user_agent columns to VARCHAR(500) — matching the existing
     * codebase precedent (legal_document_acceptances.user_agent is varchar(500)
     * and AuthController truncates UAs with substr($ua, 0, 500)).
     */
    public function up(): void
    {
        foreach ([
            'ad_impressions',
            'ad_clicks',
            'ad_fraud_logs',
            'reports',
            'place_reviews',
            'report_security_logs',
        ] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY user_agent VARCHAR(500) NULL");
        }
    }

    public function down(): void
    {
        foreach ([
            'ad_impressions',
            'ad_clicks',
            'ad_fraud_logs',
            'reports',
            'place_reviews',
            'report_security_logs',
        ] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY user_agent VARCHAR(100) NULL");
        }
    }
};
