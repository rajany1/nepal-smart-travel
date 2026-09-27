<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_wallet_transactions')) {
            return;
        }

        Schema::create('partner_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('travel_partners')->cascadeOnDelete();
            $table->string('type', 50); // credit, debit, withdrawal, topup, offer_earning, adjustment, backfill
            $table->decimal('amount', 12, 2); // positive for credit, negative for debit
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_type', 50)->nullable(); // PartnerPayment, PartnerWithdrawal, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // admin who performed
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['partner_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_wallet_transactions');
    }
};
