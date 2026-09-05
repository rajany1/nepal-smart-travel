<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('provider')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NPR');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time', 'pay_as_you_go'])->default('monthly');
            $table->date('next_renewal_date')->nullable();
            $table->date('last_paid_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'cancelled', 'expired'])->default('active');
            $table->text('notes')->nullable();
            $table->boolean('renewal_alert_sent')->default(false);
            $table->integer('alert_days_before')->default(7);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('status');
            $table->index('next_renewal_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_expenses');
    }
};
