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
        // Step 1: Add columns without unique constraint on slug
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
                $table->foreignId('category_group_id')->nullable()->constrained('report_category_groups')->nullOnDelete()->after('description_ne');
            }
            if (!Schema::hasColumn('report_categories', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('category_group_id');
            }
            if (!Schema::hasColumn('report_categories', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('sort_order');
            }
            if (!Schema::hasColumn('report_categories', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('report_categories', 'is_emergency')) {
                $table->boolean('is_emergency')->default(false)->after('is_featured');
            }
            if (!Schema::hasColumn('report_categories', 'usage_count')) {
                $table->unsignedInteger('usage_count')->default(0)->after('is_emergency');
            }
            if (!Schema::hasColumn('report_categories', 'icon_type')) {
                $table->string('icon_type')->default('material')->after('icon');
            }
            if (!Schema::hasColumn('report_categories', 'icon_color')) {
                $table->string('icon_color')->nullable()->after('icon_type');
            }
            if (!Schema::hasColumn('report_categories', 'icon_background')) {
                $table->string('icon_background')->nullable()->after('icon_color');
            }
        });

        // Step 2: Populate slugs for existing categories using raw SQL
        $categories = DB::table('report_categories')->get();
        foreach ($categories as $category) {
            $slug = Str::slug($category->name);
            // Ensure uniqueness
            $originalSlug = $slug;
            $counter = 1;
            while (DB::table('report_categories')->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            DB::table('report_categories')->where('id', $category->id)->update(['slug' => $slug]);
        }

        // Step 3: Add unique index on slug
        DB::statement('ALTER TABLE `report_categories` ADD UNIQUE INDEX `report_categories_slug_unique` (`slug`)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_categories', function (Blueprint $table) {
            $columns = ['slug', 'description', 'description_ne', 'category_group_id', 'sort_order', 'is_active', 'is_featured', 'is_emergency', 'usage_count', 'icon_type', 'icon_color', 'icon_background'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('report_categories', $col)) {
                    if ($col === 'category_group_id') {
                        $table->dropForeign(['category_group_id']);
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};