<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offer_redemptions', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('user_id')
                ->constrained('bookings')->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->after('used_at');
            $table->timestamp('consumed_at')->nullable()->after('applied_at');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('consumed_at');
        });
    }

    public function down(): void
    {
        Schema::table('offer_redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn(['applied_at', 'consumed_at', 'discount_amount']);
        });
    }
};