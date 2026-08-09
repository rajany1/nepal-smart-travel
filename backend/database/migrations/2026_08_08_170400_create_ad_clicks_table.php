<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('clicked_at');

            $table->index(['ad_campaign_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_clicks');
    }
};