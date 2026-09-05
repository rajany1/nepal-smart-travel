<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // System-generated account notices need a "system" alert type.
        DB::statement("ALTER TABLE alerts MODIFY alert_type ENUM('earthquake','flood','landslide','weather','strike','emergency','system') NOT NULL DEFAULT 'emergency'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE alerts MODIFY alert_type ENUM('earthquake','flood','landslide','weather','strike','emergency') NOT NULL DEFAULT 'emergency'");
    }
};