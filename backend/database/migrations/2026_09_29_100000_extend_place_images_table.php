<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend place_images from (place_id, image_url) into a full provenance-aware
 * gallery table for the automatic Wikimedia-backed image discovery system.
 *
 * Existing rows are preserved verbatim: they keep image_url, get
 * source='upload' (local admin/user uploads), and NULL on every new nullable
 * column — which both new UNIQUE indexes accept (MySQL treats NULLs as
 * distinct), so no historical row can violate them.
 *
 * ENGINE NOTE: this schema's default engine is MyISAM, which caps any index
 * at 1000 bytes — (place_id, source, source_id) with utf8mb4 alone needs
 * ~1100 — and silently IGNORED the `cascadeOnDelete()` foreign key that the
 * original 2026_05_16 migration declared (only a plain index was created).
 * Converting to InnoDB fixes both, mirroring the existing
 * `2026_09_28_000003_convert_ad_coin_tables_to_innodb.php` precedent.
 */
return new class extends Migration
{
    private const TABLE = 'place_images';

    private const INDEXES = [
        'pi_place_source_source_uq',
        'pi_place_sha1_uq',
        'pi_place_status_primary_idx',
        'pi_source_source_id_idx',
        'pi_status_idx',
    ];

    public function up(): void
    {
        // 1) Engine first — every index below depends on the 3072-byte InnoDB
        //    key limit. No-op when the table is already InnoDB.
        $engine = DB::selectOne(
            "SELECT ENGINE AS engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
            [self::TABLE]
        );
        if ($engine && strtoupper((string) $engine->engine) !== 'INNODB') {
            DB::statement('ALTER TABLE `' . self::TABLE . '` ENGINE=InnoDB');
        }

        // 2) Columns (idempotent — a partially applied run must be resumable).
        Schema::table(self::TABLE, function (Blueprint $table) {
            // Thumb/original URLs are long (Commons file names are up to 255
            // bytes on top of the path) — varchar(255) would truncate.
            if (!Schema::hasColumn(self::TABLE, 'thumbnail_url')) {
                $table->text('thumbnail_url')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn(self::TABLE, 'original_url')) {
                $table->text('original_url')->nullable()->after('thumbnail_url');
            }

            if (!Schema::hasColumn(self::TABLE, 'source')) {
                $table->string('source', 20)->default('upload')->after('place_id');
            }
            if (!Schema::hasColumn(self::TABLE, 'source_id')) {
                $table->string('source_id', 255)->nullable()->after('source');
            }
            if (!Schema::hasColumn(self::TABLE, 'source_page_url')) {
                $table->text('source_page_url')->nullable()->after('source_id');
            }

            if (!Schema::hasColumn(self::TABLE, 'title')) {
                $table->string('title', 255)->nullable()->after('source_page_url');
            }
            if (!Schema::hasColumn(self::TABLE, 'author')) {
                $table->string('author', 255)->nullable()->after('title');
            }
            if (!Schema::hasColumn(self::TABLE, 'license')) {
                $table->string('license', 50)->nullable()->after('author');
            }
            if (!Schema::hasColumn(self::TABLE, 'license_url')) {
                $table->string('license_url', 255)->nullable()->after('license');
            }
            if (!Schema::hasColumn(self::TABLE, 'attribution')) {
                $table->text('attribution')->nullable()->after('license_url');
            }

            if (!Schema::hasColumn(self::TABLE, 'width')) {
                $table->unsignedInteger('width')->nullable()->after('attribution');
            }
            if (!Schema::hasColumn(self::TABLE, 'height')) {
                $table->unsignedInteger('height')->nullable()->after('width');
            }
            if (!Schema::hasColumn(self::TABLE, 'sha1')) {
                $table->char('sha1', 40)->nullable()->after('height');
            }

            if (!Schema::hasColumn(self::TABLE, 'photo_latitude')) {
                $table->decimal('photo_latitude', 10, 7)->nullable()->after('sha1');
            }
            if (!Schema::hasColumn(self::TABLE, 'photo_longitude')) {
                $table->decimal('photo_longitude', 10, 7)->nullable()->after('photo_latitude');
            }

            if (!Schema::hasColumn(self::TABLE, 'match_score')) {
                $table->decimal('match_score', 4, 3)->nullable()->after('photo_longitude');
            }
            if (!Schema::hasColumn(self::TABLE, 'matched_via')) {
                $table->string('matched_via', 30)->nullable()->after('match_score');
            }

            if (!Schema::hasColumn(self::TABLE, 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('matched_via');
            }
            if (!Schema::hasColumn(self::TABLE, 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_primary');
            }
            if (!Schema::hasColumn(self::TABLE, 'status')) {
                $table->string('status', 20)->default('approved')->after('sort_order');
            }

            if (!Schema::hasColumn(self::TABLE, 'discovered_at')) {
                $table->timestamp('discovered_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn(self::TABLE, 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('discovered_at');
            }
        });

        // 3) Normalize widths: AppServiceProvider sets Schema::defaultStringLength(100),
        //    so an interrupted earlier run may have created these narrower than the
        //    255 the clean-path above declares. Commons titles/attribution URLs need 255.
        $narrow = DB::select(
            "SELECT column_name AS col, character_maximum_length AS len
               FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ?
                AND column_name IN ('title','author','license_url')
                AND character_maximum_length < 255",
            [self::TABLE]
        );
        foreach ($narrow as $column) {
            $name = $column->col ?? $column->COLUMN_NAME;
            Schema::table(self::TABLE, function (Blueprint $table) use ($name) {
                $table->string($name, 255)->nullable()->change();
            });
        }

        // 4) image_url: varchar(255) -> text (Commons thumb URLs exceed 255).
        //    getColumnType() keeps this resumable — doctrine/dbal is gone on
        //    Laravel 12/13.
        if (in_array(Schema::getColumnType(self::TABLE, 'image_url'), ['string', 'varchar'], true)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->text('image_url')->change();
            });
        }

        // 5) Indexes — only when missing, so a crashed run can be re-run.
        $existing = array_map(
            fn ($row) => $row->n,
            DB::select(
                'SELECT DISTINCT index_name AS n FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ?',
                [self::TABLE]
            )
        );

        Schema::table(self::TABLE, function (Blueprint $table) use ($existing) {
            // Provider-level duplicate guard (NULL source_id on legacy/local
            // uploads keeps them unrestricted, exactly as before).
            if (!in_array('pi_place_source_source_uq', $existing, true)) {
                $table->unique(['place_id', 'source', 'source_id'], 'pi_place_source_source_uq');
            }
            // URL-level duplicate guard across providers.
            if (!in_array('pi_place_sha1_uq', $existing, true)) {
                $table->unique(['place_id', 'sha1'], 'pi_place_sha1_uq');
            }
            // Gallery read path: primary/approved image lookups.
            if (!in_array('pi_place_status_primary_idx', $existing, true)) {
                $table->index(['place_id', 'status', 'is_primary'], 'pi_place_status_primary_idx');
            }
            // Cross-place dedupe / provenance lookups.
            if (!in_array('pi_source_source_id_idx', $existing, true)) {
                $table->index(['source', 'source_id'], 'pi_source_source_id_idx');
            }
            if (!in_array('pi_status_idx', $existing, true)) {
                $table->index(['status'], 'pi_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (self::INDEXES as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                    // Index may not exist on a partially rolled-back schema.
                }
            }
        });

        Schema::table(self::TABLE, function (Blueprint $table) {
            $columns = [
                'thumbnail_url', 'original_url', 'source', 'source_id', 'source_page_url',
                'title', 'author', 'license', 'license_url', 'attribution',
                'width', 'height', 'sha1', 'photo_latitude', 'photo_longitude',
                'match_score', 'matched_via', 'is_primary', 'sort_order', 'status',
                'discovered_at', 'last_checked_at',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn(self::TABLE, $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Restore the declared (but previously ignored) MyISAM behaviour.
        DB::statement('ALTER TABLE `' . self::TABLE . '` ENGINE=MyISAM');
    }
};
