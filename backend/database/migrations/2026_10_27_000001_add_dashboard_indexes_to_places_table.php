<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin dashboard aggregates the places catalog by date, active state,
     * featured state and source. Without these indexes each aggregate scans the
     * whole table, which made a cold dashboard load take several seconds.
     */
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['source']);
        });
    }
};
