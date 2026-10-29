<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Places command center filters and groups by verification state and
     * district. Both were unindexed on a 70k-row MyISAM table, so a single
     * verified count or district breakdown scanned the whole table. These
     * indexes keep the analytics snapshot and the pending tab fast.
     */
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->index('is_verified');
            $table->index('district');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['is_verified']);
            $table->dropIndex(['district']);
        });
    }
};
