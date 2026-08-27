<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('alerts')) {
            return;
        }

        Schema::table('alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('alerts', 'source_type')) {
                $table->string('source_type')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('alerts', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
        });

        if (!Schema::hasIndex('alerts', 'idx_alerts_source')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->unique(['source_type', 'source_id'], 'idx_alerts_source');
            });
        }

        if (!Schema::hasIndex('alerts', 'idx_alerts_lat_lng')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->index(['latitude', 'longitude'], 'idx_alerts_lat_lng');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('alerts')) {
            return;
        }

        if (Schema::hasIndex('alerts', 'idx_alerts_lat_lng')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->dropIndex('idx_alerts_lat_lng');
            });
        }
        if (Schema::hasIndex('alerts', 'idx_alerts_source')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->dropUnique('idx_alerts_source');
            });
        }
        Schema::table('alerts', function (Blueprint $table) {
            foreach (['source_id', 'source_type'] as $col) {
                if (Schema::hasColumn('alerts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
