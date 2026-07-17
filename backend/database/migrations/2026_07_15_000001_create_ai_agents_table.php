<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('idle');
            $table->string('model')->default('gemini-2.0-flash');
            $table->string('prompt_template')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('config')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
