<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('curated_routes', function (Blueprint $table) {
            $table->json('track_segment_modes')->nullable()->after('track');
        });
    }

    public function down(): void
    {
        Schema::table('curated_routes', function (Blueprint $table) {
            $table->dropColumn('track_segment_modes');
        });
    }
};
