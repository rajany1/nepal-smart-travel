<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overall AI authenticity trust score (0.00 - 1.00) for a report,
 * combining text legitimacy, location/GPS check, and vision verdict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'authenticity_score')) {
                $table->decimal('authenticity_score', 4, 2)->nullable()->after('moderation_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'authenticity_score')) {
                $table->dropColumn('authenticity_score');
            }
        });
    }
};