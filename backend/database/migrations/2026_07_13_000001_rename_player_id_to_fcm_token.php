<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('push_tokens', 'player_id')) {
            Schema::table('push_tokens', function (Blueprint $table) {
                $table->renameColumn('player_id', 'fcm_token');
            });
        }
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->string('device_type')->nullable()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->dropColumn('device_type');
        });
        if (Schema::hasColumn('push_tokens', 'fcm_token')) {
            Schema::table('push_tokens', function (Blueprint $table) {
                $table->renameColumn('fcm_token', 'player_id');
            });
        }
    }
};
