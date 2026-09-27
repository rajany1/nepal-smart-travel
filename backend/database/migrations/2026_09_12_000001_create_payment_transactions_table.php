<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_transactions')) {
            // Table exists — no action needed, indexes already present from manual creation
            return;
        }

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id');
            $table->string('method');
            $table->decimal('amount_npr', 12, 2);
            $table->json('account_details');
            $table->string('status')->default('pending');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('travel_partners')->nullOnDelete();
            $table->foreignId('withdrawal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_withdrawal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['reference_id', 'status']);
            $table->index(['gateway_transaction_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
