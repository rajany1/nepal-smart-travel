<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('alerts', 'description_ne')) {
            return;
        }

        Schema::table('alerts', function (Blueprint $table) {
            $table->text('description_ne')->nullable()->after('title_ne');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('alerts', 'description_ne')) {
            return;
        }

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('description_ne');
        });
    }
};
