<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         * Step 1: Add columns.
         *
         * IMPORTANT:
         * category_group_id is added as a plain unsignedBigInteger first.
         * The foreign key is added separately below so this migration remains
         * safe if a previous attempt partially completed.
         */
        Schema::table('report_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('report_categories', 'slug')) {
                $table->string('slug')->nullable()->after('name');
            }

            if (!Schema::hasColumn('report_categories', 'description')) {
                $table->text('description')->nullable()->after('icon');
            }

            if (!Schema::hasColumn('report_categories', 'description_ne')) {
                $table->text('description_ne')->nullable()->after('description');
            }

            if (!Schema::hasColumn('report_categories', 'category_group_id')) {
                $table->unsignedBigInteger('category_group_id')
                    ->nullable()
                    ->after('description_ne');
            }

            if (!Schema::hasColumn('report_categories', 'sort_order')) {
                $table->unsignedInteger('sort_order')
                    ->default(0)
                    ->after('category_group_id');
            }

            if (!Schema::hasColumn('report_categories', 'is_active')) {
                $table->boolean('is_active')
                    ->default(true)
                    ->after('sort_order');
            }

            if (!Schema::hasColumn('report_categories', 'is_featured')) {
                $table->boolean('is_featured')
                    ->default(false)
                    ->after('is_active');
            }

            if (!Schema::hasColumn('report_categories', 'is_emergency')) {
                $table->boolean('is_emergency')
                    ->default(false)
                    ->after('is_featured');
            }

            if (!Schema::hasColumn('report_categories', 'usage_count')) {
                $table->unsignedInteger('usage_count')
                    ->default(0)
                    ->after('is_emergency');
            }

            if (!Schema::hasColumn('report_categories', 'icon_type')) {
                $table->string('icon_type')
                    ->default('material')
                    ->after('icon');
            }

            if (!Schema::hasColumn('report_categories', 'icon_color')) {
                $table->string('icon_color')
                    ->nullable()
                    ->after('icon_type');
            }

            if (!Schema::hasColumn('report_categories', 'icon_background')) {
                $table->string('icon_background')
                    ->nullable()
                    ->after('icon_color');
            }
        });

        /*
         * Step 2: Add the foreign key separately.
         *
         * This handles the VPS situation where category_group_id already
         * exists because the previous migration attempt partially succeeded.
         */
        $foreignKeyExists = DB::selectOne(
            "
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'report_categories'
              AND COLUMN_NAME = 'category_group_id'
              AND REFERENCED_TABLE_NAME = 'report_category_groups'
            LIMIT 1
            "
        );

        if (!$foreignKeyExists) {
            Schema::table('report_categories', function (Blueprint $table) {
                $table->foreign('category_group_id')
                    ->references('id')
                    ->on('report_category_groups')
                    ->nullOnDelete();
            });
        }

        /*
         * Step 3: Populate slugs for existing categories.
         */
        $categories = DB::table('report_categories')->get();

        foreach ($categories as $category) {
            $slug = Str::slug($category->name);

            if ($slug === '') {
                $slug = 'category-' . $category->id;
            }

            $originalSlug = $slug;
            $counter = 1;

            while (
                DB::table('report_categories')
                    ->where('slug', $slug)
                    ->where('id', '!=', $category->id)
                    ->exists()
            ) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            DB::table('report_categories')
                ->where('id', $category->id)
                ->update(['slug' => $slug]);
        }

        /*
         * Step 4: Add the unique index only if it does not already exist.
         */
        $uniqueIndexExists = DB::selectOne(
            "
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'report_categories'
              AND INDEX_NAME = 'report_categories_slug_unique'
            LIMIT 1
            "
        );

        if (!$uniqueIndexExists) {
            DB::statement(
                'ALTER TABLE `report_categories`
                 ADD UNIQUE INDEX `report_categories_slug_unique` (`slug`)'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Drop the foreign key first if it exists.
         */
        $foreignKeyExists = DB::selectOne(
            "
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'report_categories'
              AND COLUMN_NAME = 'category_group_id'
              AND REFERENCED_TABLE_NAME = 'report_category_groups'
            LIMIT 1
            "
        );

        if ($foreignKeyExists) {
            Schema::table('report_categories', function (Blueprint $table) {
                $table->dropForeign(['category_group_id']);
            });
        }

        /*
         * Drop the unique index if it exists.
         */
        $uniqueIndexExists = DB::selectOne(
            "
            SELECT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'report_categories'
              AND INDEX_NAME = 'report_categories_slug_unique'
            LIMIT 1
            "
        );

        if ($uniqueIndexExists) {
            DB::statement(
                'ALTER TABLE `report_categories`
                 DROP INDEX `report_categories_slug_unique`'
            );
        }

        /*
         * Drop columns that were added by this migration.
         */
        $columns = [
            'slug',
            'description',
            'description_ne',
            'category_group_id',
            'sort_order',
            'is_active',
            'is_featured',
            'is_emergency',
            'usage_count',
            'icon_type',
            'icon_color',
            'icon_background',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('report_categories', $column)) {
                Schema::table('report_categories', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};