<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate idempotency_keys, keeping only the earliest one
        DB::statement('
            DELETE ade1 FROM ad_reward_events ade1
            INNER JOIN ad_reward_events ade2
            WHERE ade1.idempotency_key = ade2.idempotency_key
            AND ade1.id > ade2.id
        ');

        DB::statement('ALTER TABLE ad_reward_events MODIFY idempotency_key VARCHAR(100) NOT NULL');
        DB::statement('ALTER TABLE ad_reward_events ADD UNIQUE INDEX idx_reward_idempotency_key (idempotency_key)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ad_reward_events DROP INDEX idx_reward_idempotency_key');
        DB::statement('ALTER TABLE ad_reward_events MODIFY idempotency_key VARCHAR(255) NOT NULL');
    }
};
