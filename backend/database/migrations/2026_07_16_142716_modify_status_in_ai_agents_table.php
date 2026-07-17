<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ai_agents MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'idle'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ai_agents MODIFY COLUMN status ENUM('active','inactive','error') NOT NULL DEFAULT 'active'");
    }
};
