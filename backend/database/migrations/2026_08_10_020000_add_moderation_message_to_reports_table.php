<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Human-readable AI moderation message — the reason a report was approved,
 * rejected, or sent to manual review (shown in the admin panel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'moderation_message')) {
                $table->text('moderation_message')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'moderation_message')) {
                $table->dropColumn('moderation_message');
            }
        });
    }
};