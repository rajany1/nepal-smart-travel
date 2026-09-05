<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('sos_false_count')->default(0)->after('profile_completed');
            $table->timestamp('sos_restricted_until')->nullable()->after('sos_false_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sos_false_count', 'sos_restricted_until']);
        });
    }
};
