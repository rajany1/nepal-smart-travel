<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'place_id')) {
                $table->foreignId('place_id')->nullable()->constrained()->nullOnDelete()->after('category_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'place_id')) {
                $table->dropForeign(['place_id']);
                $table->dropColumn('place_id');
            }
        });
    }
};
