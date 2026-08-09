<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('reward_offers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->enum('status', ['claimed', 'used', 'expired'])->default('claimed');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->unique(['offer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_redemptions');
    }
};
