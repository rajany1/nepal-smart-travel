<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_partners', function (Blueprint $table) {
            $table->decimal('value_npr', 12, 2)->default(0)->after('commission_fixed');
        });
    }

    public function down(): void
    {
        Schema::table('travel_partners', function (Blueprint $table) {
            $table->dropColumn('value_npr');
        });
    }
};
