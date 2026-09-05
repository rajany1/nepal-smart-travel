<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // privacy_policy, terms_conditions, about, etc.
            $table->string('title');
            $table->longText('content'); // HTML content
            $table->string('version', 50)->nullable()->default('1.0');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->timestamps();

            $table->foreign('last_edited_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
