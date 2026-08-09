<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->default(0)->after('budget');
            $table->decimal('spent_amount', 10, 2)->default(0)->after('paid_amount');
            $table->string('payment_status')->default('unpaid')->after('spent_amount');
            $table->string('gateway')->nullable()->after('payment_status');
            $table->string('gateway_ref')->nullable()->after('gateway');
        });

        Schema::create('ad_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('travel_partners')->nullOnDelete();
            $table->foreignId('ad_campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('gateway'); // esewa, khalti
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, success, failed, refunded
            $table->string('transaction_id')->nullable();
            $table->string('reference')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('reward_offers', function (Blueprint $table) {
            $table->decimal('value_npr', 10, 2)->default(0)->after('discount_value');
        });

        Schema::table('offer_redemptions', function (Blueprint $table) {
            $table->decimal('value_npr', 10, 2)->nullable()->after('code');
            $table->decimal('commission_percent', 5, 2)->nullable()->after('value_npr');
            $table->decimal('admin_commission', 10, 2)->nullable()->after('commission_percent');
            $table->decimal('partner_earnings', 10, 2)->nullable()->after('admin_commission');
        });

        DB::table('game_settings')->updateOrInsert(
            ['key' => 'offer_commission_percent'],
            ['value' => '10', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('game_settings')->where('key', 'offer_commission_percent')->delete();
        Schema::dropIfExists('ad_payments');
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'spent_amount', 'payment_status', 'gateway', 'gateway_ref']);
        });
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->dropColumn('value_npr');
        });
        Schema::table('offer_redemptions', function (Blueprint $table) {
            $table->dropColumn(['value_npr', 'commission_percent', 'admin_commission', 'partner_earnings']);
        });
    }
};
