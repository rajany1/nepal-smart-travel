<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('admin_removed_reason')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('reward_offers', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('admin_removed_reason');
        });
    }
};
