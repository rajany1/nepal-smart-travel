<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE user_subscriptions
            INNER JOIN subscription_plans ON user_subscriptions.subscription_plan_id = subscription_plans.id
            SET user_subscriptions.ends_at = NULL
            WHERE subscription_plans.slug = ? AND user_subscriptions.status = ?',
            ['free', 'active']
        );
    }

    public function down(): void
    {
        // No reliable way to restore old dates
    }
};
