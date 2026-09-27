<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_reward_events')) {
            return;
        }

        Schema::create('ad_reward_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 20); // impression or click
            $table->decimal('gross_amount', 12, 4);
            $table->decimal('user_share', 12, 4);
            $table->decimal('admin_share', 12, 4);
            $table->decimal('coins_credited', 12, 4);
            $table->decimal('coin_to_npr_rate', 12, 4); // snapshot of rate at time of event
            $table->decimal('user_share_percent', 5, 2); // snapshot
            $table->string('idempotency_key', 100)->unique(); // user_id + campaign_id + event_type + time_window
            $table->json('metadata')->nullable();
            $table->timestamp('event_time');
            $table->timestamps();

            $table->index(['user_id', 'ad_campaign_id', 'event_type', 'event_time'], 'idx_reward_user_campaign');
            $table->index(['ad_campaign_id', 'event_type', 'event_time'], 'idx_reward_campaign_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_reward_events');
    }
};
