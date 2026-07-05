<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        if (! Schema::hasColumn('reports', 'is_live_capture')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->boolean('is_live_capture')->default(false)->after('district');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'is_live_capture')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('is_live_capture');
            });
        }
    }
};
