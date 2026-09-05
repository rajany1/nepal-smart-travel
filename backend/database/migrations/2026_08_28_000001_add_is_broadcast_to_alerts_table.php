<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // TRUE => this alert is sent to ALL users (no lat/lng scoping).
            // Admin created alerts can broadcast platform-wide.
            $table->boolean('is_broadcast')->default(false)->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('is_broadcast');
        });
    }
};
