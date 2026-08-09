<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->boolean('value_npr_locked')->default(false)->after('value_npr');
        });
    }

    public function down(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->dropColumn('value_npr_locked');
        });
    }
};
