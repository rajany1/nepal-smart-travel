<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            $table->foreignId('sponsor_id')->nullable()->constrained('sponsors')->nullOnDelete()->after('is_active');
            $table->string('reward_type')->default('voucher')->after('sponsor_id');
            $table->text('terms')->nullable()->after('reward_type');
            $table->integer('expiry_days')->nullable()->after('terms');
            $table->integer('usage_limit_per_user')->nullable()->after('expiry_days');
            $table->text('redemption_instructions')->nullable()->after('usage_limit_per_user');

            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            $table->dropForeign(['sponsor_id']);
            $table->dropColumn([
                'sponsor_id', 'reward_type', 'terms',
                'expiry_days', 'usage_limit_per_user', 'redemption_instructions',
            ]);
            $table->string('category')->default('recharge');
        });
    }
};
