<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('push_tokens', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('device_type');
            }
            if (!Schema::hasColumn('push_tokens', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('push_tokens', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
