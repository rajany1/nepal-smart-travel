<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('travel_partners')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method'); // esewa, khalti, bank
            $table->string('payment_id')->nullable(); // gateway transaction id
            $table->decimal('commission_percent', 5, 2)->default(10);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('partner_amount', 12, 2);
            $table->string('redeem_code', 20)->nullable()->unique();
            $table->text('qr_data')->nullable();
            $table->enum('status', ['pending', 'completed', 'expired', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['partner_id', 'status']);
            $table->index(['redeem_code']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payments');
    }
};
