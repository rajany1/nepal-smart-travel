<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->string('paused_by')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->dropColumn('paused_by');
        });
    }
};
