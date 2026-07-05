<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('business_id')->nullable()->constrained('travel_partners')->nullOnDelete();
            $table->string('ad_type'); // banner, promoted_place, sponsored_card
            $table->text('content')->nullable();
            $table->string('image')->nullable();
            $table->string('target_url')->nullable();
            $table->string('target_district')->nullable();
            $table->string('target_category')->nullable();
            $table->decimal('budget', 10, 2)->default(0);
            $table->decimal('cost_per_view', 5, 2)->default(0.50);
            $table->integer('max_impressions')->default(0);
            $table->integer('current_impressions')->default(0);
            $table->string('status')->default('pending'); // pending, active, paused, completed, rejected
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('ad_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('viewed_at');
            $table->index(['ad_campaign_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_impressions');
        Schema::dropIfExists('ad_campaigns');
    }
};
