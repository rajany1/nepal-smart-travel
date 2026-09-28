<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('report_categories', 'name_ne')) {
                $table->string('name_ne')->nullable()->after('name');
            }
        });

        Schema::table('report_category_options', function (Blueprint $table) {
            if (! Schema::hasColumn('report_category_options', 'severity')) {
                // low | medium | high | critical — drives green/blue/red outline in app
                $table->string('severity', 16)->default('medium')->after('icon_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_categories', function (Blueprint $table) {
            if (Schema::hasColumn('report_categories', 'name_ne')) {
                $table->dropColumn('name_ne');
            }
        });

        Schema::table('report_category_options', function (Blueprint $table) {
            if (Schema::hasColumn('report_category_options', 'severity')) {
                $table->dropColumn('severity');
            }
        });
    }
};
