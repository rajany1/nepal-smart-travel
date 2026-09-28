<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy MyISAM table caps index keys at 1000 bytes (utf8mb4 → 250
        // chars). Convert to InnoDB so the unique term key can grow.
        DB::statement('ALTER TABLE translation_glossary ENGINE=InnoDB');

        Schema::table('translation_glossary', function (Blueprint $table) {
            // Long UI phrases (full-sentence confirmation keys) exceed the
            // original VARCHAR(100) and broke seeding.
            $table->string('term', 500)->change();
            $table->string('nepali', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('translation_glossary', function (Blueprint $table) {
            $table->string('term', 100)->change();
            $table->string('nepali', 100)->change();
        });
    }
};
