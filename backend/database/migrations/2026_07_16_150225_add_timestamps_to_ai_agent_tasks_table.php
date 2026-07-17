<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_agent_tasks', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('error_message');
            }
            if (!Schema::hasColumn('ai_agent_tasks', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('ai_agent_tasks', 'started_at')) {
                $drop[] = 'started_at';
            }
            if (Schema::hasColumn('ai_agent_tasks', 'completed_at')) {
                $drop[] = 'completed_at';
            }
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
