<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bipad_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 32); // 'incident' or 'alert'
            $table->string('status', 16); // 'started', 'success', 'failed', 'partial'
            $table->unsignedInteger('records_fetched')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // offset, page, duration_ms, etc.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['endpoint', 'status'], 'bipad_sync_endpoint_status_idx');
            $table->index(['created_at'], 'bipad_sync_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bipad_sync_logs');
    }
};