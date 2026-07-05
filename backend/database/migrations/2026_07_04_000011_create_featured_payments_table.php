<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->string('featured_type'); // featured, top_recommendation, sponsored
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('NPR');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending'); // pending, paid, refunded
            $table->integer('duration_months')->default(1);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('featured_payments');
    }
};
