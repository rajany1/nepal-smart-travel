<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_codes', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete()->after('used_at');
            $table->timestamp('applied_at')->nullable()->after('booking_id');
            $table->timestamp('consumed_at')->nullable()->after('applied_at');
        });
    }

    public function down(): void
    {
        Schema::table('shop_codes', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn(['booking_id', 'applied_at', 'consumed_at']);
        });
    }
};
