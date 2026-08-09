<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->unsignedInteger('price_xp')->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->dropColumn('price_xp');
        });
    }
};