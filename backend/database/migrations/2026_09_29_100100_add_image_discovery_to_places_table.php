<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Image-discovery state machine + OSM/Wikimedia identity columns on `places`.
 *
 * `image_discovery_status` defaults to 'pending' for every existing row, so a
 * fresh backfill run can select work with a plain indexed WHERE clause.
 * These columns are pure bookkeeping — no existing place data is modified.
 */
return new class extends Migration
{
    private const TABLE = 'places';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'wikidata_id')) {
                $table->string('wikidata_id', 20)->nullable()->after('imported_at');
            }
            // Explicit 255: AppServiceProvider sets defaultStringLength(100),
            // which is too short for Commons category names / wiki page titles.
            if (!Schema::hasColumn(self::TABLE, 'wikipedia_title')) {
                $table->string('wikipedia_title', 255)->nullable()->after('wikidata_id');
            }
            if (!Schema::hasColumn(self::TABLE, 'commons_category')) {
                $table->string('commons_category', 255)->nullable()->after('wikipedia_title');
            }

            if (!Schema::hasColumn(self::TABLE, 'image_discovery_status')) {
                $table->string('image_discovery_status', 20)->default('pending')->after('commons_category');
            }
            if (!Schema::hasColumn(self::TABLE, 'image_discovered_at')) {
                $table->timestamp('image_discovered_at')->nullable()->after('image_discovery_status');
            }
            if (!Schema::hasColumn(self::TABLE, 'image_attempt_count')) {
                $table->unsignedInteger('image_attempt_count')->default(0)->after('image_discovered_at');
            }
            if (!Schema::hasColumn(self::TABLE, 'image_next_retry_at')) {
                $table->timestamp('image_next_retry_at')->nullable()->after('image_attempt_count');
            }
        });

        $existing = array_map(
            fn ($row) => $row->n ?? $row->N ?? $row->index_name ?? $row->INDEX_NAME,
            DB::select(
                'SELECT DISTINCT index_name AS n FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ?',
                [self::TABLE]
            )
        );

        Schema::table(self::TABLE, function (Blueprint $table) use ($existing) {
            if (!in_array('places_image_discovery_idx', $existing, true)) {
                $table->index(['image_discovery_status', 'image_next_retry_at'], 'places_image_discovery_idx');
            }
            if (!in_array('places_wikidata_id_idx', $existing, true)) {
                $table->index(['wikidata_id'], 'places_wikidata_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (['places_image_discovery_idx', 'places_wikidata_id_idx'] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                    // Index may not exist on a partially rolled-back schema.
                }
            }
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $columns = [
                'wikidata_id', 'wikipedia_title', 'commons_category',
                'image_discovery_status', 'image_discovered_at',
                'image_attempt_count', 'image_next_retry_at',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn(self::TABLE, $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
