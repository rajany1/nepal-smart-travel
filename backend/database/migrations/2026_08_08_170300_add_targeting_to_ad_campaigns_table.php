<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->json('contexts')->nullable()->after('target_category');
            $table->decimal('cost_per_click', 5, 2)->default(0)->after('cost_per_view');
            $table->unsignedInteger('current_clicks')->default(0)->after('current_impressions');
            $table->text('rejection_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['contexts', 'cost_per_click', 'current_clicks', 'rejection_reason']);
        });
    }
};