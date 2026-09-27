<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_document_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_document_id')->nullable()->constrained('legal_documents')->nullOnDelete();
            $table->string('document_type', 50); // privacy_policy, terms_conditions, etc.
            $table->string('document_version', 20); // e.g. '1.0'
            $table->string('document_hash', 64); // SHA-256 of accepted content
            $table->timestamp('accepted_at');
            $table->string('app_version', 20)->nullable();
            $table->string('platform', 20)->nullable(); // ios, android, web
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'document_type']);
            $table->index(['document_type', 'document_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_acceptances');
    }
};
