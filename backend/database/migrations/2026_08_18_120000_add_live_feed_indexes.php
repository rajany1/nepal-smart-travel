<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'places', 'place_reviews', 'reports', 'report_comments', 'alerts', 'users',
        'reward_offers', 'offer_redemptions', 'ad_campaigns', 'bookings', 'payouts',
        'audit_logs', 'place_corrections', 'travel_partners', 'user_subscriptions',
        'subscription_plans', 'roles', 'permissions', 'achievements', 'curated_routes',
        'ai_agents', 'ai_agent_tasks', 'translation_glossary',
    ];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (!Schema::hasTable($t)) continue;
            $name = 'idx_' . $t . '_updated_at';
            if (!Schema::hasIndex($t, $name)) {
                Schema::table($t, function (Blueprint $table) use ($name) {
                    $table->index('updated_at', $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (!Schema::hasTable($t)) continue;
            $name = 'idx_' . $t . '_updated_at';
            if (Schema::hasIndex($t, $name)) {
                Schema::table($t, function (Blueprint $table) use ($name) {
                    $table->dropIndex($name);
                });
            }
        }
    }
};
