<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->foreignId('travel_partner_id')->nullable()->constrained('travel_partners')->nullOnDelete()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('sponsors', function (Blueprint $table) {
            $table->dropForeign(['travel_partner_id']);
            $table->dropColumn('travel_partner_id');
        });
    }
};
