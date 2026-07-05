<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // hotel, vehicle_rental, guide, adventure
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10.00); // percentage
            $table->decimal('commission_fixed', 10, 2)->default(0); // fixed amount per booking
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('commission_earned', 10, 2)->default(0);
            $table->decimal('reward_pool_share', 10, 2)->default(0);
            $table->string('status')->default('pending'); // pending, confirmed, completed, cancelled
            $table->text('notes')->nullable();
            $table->timestamp('booked_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['travel_partner_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('commission_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_commission', 10, 2);
            $table->decimal('reward_pool_contribution', 10, 2);
            $table->decimal('platform_revenue', 10, 2);
            $table->string('status')->default('pending'); // pending, paid, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_transactions');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('travel_partners');
    }
};
