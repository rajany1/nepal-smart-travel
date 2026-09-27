<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->string('fingerprint_hash', 64)->nullable()->after('media_hash');
            $table->index('fingerprint_hash');
        });
    }

    public function down(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->dropIndex(['fingerprint_hash']);
            $table->dropColumn('fingerprint_hash');
        });
    }
};
