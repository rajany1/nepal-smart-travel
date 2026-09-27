<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('bipad_id')->nullable()->unique()->after('source_id');
            $table->unsignedInteger('bipad_hazard_id')->nullable()->after('bipad_id');
            $table->string('bipad_source')->nullable()->after('bipad_hazard_id');
            $table->string('title_ne')->nullable()->after('description');
            $table->json('bipad_raw')->nullable()->after('title_ne');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn(['bipad_id', 'bipad_hazard_id', 'bipad_source', 'title_ne', 'bipad_raw']);
        });
    }
};