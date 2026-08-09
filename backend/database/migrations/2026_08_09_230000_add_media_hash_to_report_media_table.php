<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store a sha256 of each report image so identical photos reused across
     * reports can be detected automatically (anti fake-report layer).
     */
    public function up(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->string('media_hash', 64)->nullable()->index()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('report_media', function (Blueprint $table) {
            $table->dropColumn('media_hash');
        });
    }
};