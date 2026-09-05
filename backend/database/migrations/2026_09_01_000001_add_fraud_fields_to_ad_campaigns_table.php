<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->integer('fraud_score')->default(0)->after('rejection_reason');
            $table->json('fraud_flags')->nullable()->after('fraud_score');
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['fraud_score', 'fraud_flags']);
        });
    }
};
