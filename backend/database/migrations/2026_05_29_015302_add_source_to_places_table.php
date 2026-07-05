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
        Schema::table('places', function (Blueprint $table) {
            if (!Schema::hasColumn('places', 'source')) {
                $table->string('source', 20)->default('admin')->after('featured_until');
            }
            if (!Schema::hasColumn('places', 'osm_id')) {
                $table->string('osm_id', 50)->nullable()->unique()->after('source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $columns = ['source', 'osm_id'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('places', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
