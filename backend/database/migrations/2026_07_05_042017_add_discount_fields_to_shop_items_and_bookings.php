<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            $table->string('discount_type')->nullable()->after('reward_type');
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->default(0)->after('reward_pool_share');
        });
    }

    public function down(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });
    }
};
