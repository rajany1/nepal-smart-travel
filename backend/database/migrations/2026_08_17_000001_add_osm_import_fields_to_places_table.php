<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            if (!Schema::hasColumn('places', 'osm_type')) {
                $table->string('osm_type', 20)->nullable()->after('osm_id');
            }
            if (!Schema::hasColumn('places', 'imported_at')) {
                $table->timestamp('imported_at')->nullable()->after('osm_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            foreach (['osm_type', 'imported_at'] as $col) {
                if (Schema::hasColumn('places', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};