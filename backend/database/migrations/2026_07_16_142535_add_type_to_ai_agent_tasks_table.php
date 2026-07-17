<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_agent_tasks', 'type')) {
                $table->string('type')->after('ai_agent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('ai_agent_tasks', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
