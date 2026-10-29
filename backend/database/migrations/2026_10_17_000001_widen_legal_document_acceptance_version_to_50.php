<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * legal_documents.version is varchar(50) and the admin version generator
     * (nextVersion) can produce strings up to 50 chars. The acceptance table
     * must be able to store whatever version the user accepted, otherwise
     * recordConsent fails with "Data too long for column 'document_version'".
     */
    public function up(): void
    {
        Schema::table('legal_document_acceptances', function (Blueprint $table) {
            $table->string('document_version', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('legal_document_acceptances', function (Blueprint $table) {
            $table->string('document_version', 20)->change();
        });
    }
};
