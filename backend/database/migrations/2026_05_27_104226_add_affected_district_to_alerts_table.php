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
        if (!Schema::hasColumn('alerts', 'affected_district')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->string('affected_district')->nullable()->after('severity');
            });
        }

        if (!Schema::hasColumn('alerts', 'created_by')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('longitude');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn('affected_district');
        });

        if (Schema::hasColumn('alerts', 'created_by')) {
            Schema::table('alerts', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            });
        }
    }
};
