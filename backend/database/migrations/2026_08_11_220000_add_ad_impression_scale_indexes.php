<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_impressions', function (Blueprint $table) {
            $table->index(['user_id', 'viewed_at'], 'ad_impressions_user_viewed_idx');
            $table->index(['ip_address', 'viewed_at'], 'ad_impressions_ip_viewed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ad_impressions', function (Blueprint $table) {
            $table->dropIndex('ad_impressions_user_viewed_idx');
            $table->dropIndex('ad_impressions_ip_viewed_idx');
        });
    }
};
