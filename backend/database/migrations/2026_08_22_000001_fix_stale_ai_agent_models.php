<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Groq decommissioned llama-3.3-70b-versatile and Google retired
     * gemini-2.0-flash — every agent row still pointed at them, so all
     * AI calls failed with model_not_found. Point agents at models that
     * are live on the configured providers.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ai_agents')) {
            return;
        }

        DB::table('ai_agents')
            ->where('provider', 'groq')
            ->where('model', 'llama-3.3-70b-versatile')
            ->update(['model' => 'qwen/qwen3.6-27b']);

        DB::table('ai_agents')
            ->where('provider', 'gemini')
            ->where('model', 'gemini-2.0-flash')
            ->update(['model' => 'gemini-3.6-flash']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('ai_agents')) {
            return;
        }

        DB::table('ai_agents')
            ->where('provider', 'groq')
            ->where('model', 'qwen/qwen3.6-27b')
            ->update(['model' => 'llama-3.3-70b-versatile']);

        DB::table('ai_agents')
            ->where('provider', 'gemini')
            ->where('model', 'gemini-3.6-flash')
            ->update(['model' => 'gemini-2.0-flash']);
    }
};
