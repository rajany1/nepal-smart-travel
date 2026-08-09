<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('travel_partners')->cascadeOnDelete();
            $table->string('title');
            $table->enum('offer_type', [
                'percentage_off',
                'fixed_off',
                'free_item',
                'buy_one_get_one',
            ]);
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('image')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paused'])->default('pending')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('usage_limit')->default(0);
            $table->integer('used_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_offers');
    }
};
