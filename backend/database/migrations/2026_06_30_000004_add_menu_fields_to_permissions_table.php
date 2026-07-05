<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('menu_label', 100)->nullable()->after('group');
            $table->string('menu_icon', 50)->nullable()->after('menu_label');
            $table->integer('menu_order')->default(0)->after('menu_icon');
            $table->string('route_name', 100)->nullable()->after('menu_order');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['menu_label', 'menu_icon', 'menu_order', 'route_name']);
        });
    }
};
