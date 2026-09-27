<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_reward_events', function (Blueprint $table) {
            $table->string('event_status', 20)->default('validated')->after('event_type');
            $table->index(['event_status', 'ad_campaign_id'], 'idx_reward_status_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('ad_reward_events', function (Blueprint $table) {
            $table->dropIndex('idx_reward_status_campaign');
            $table->dropColumn('event_status');
        });
    }
};
