<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_key')->unique();
            $table->boolean('enabled')->default(true);
            $table->string('last_status', 20)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->text('last_output')->nullable();
            $table->text('last_error')->nullable();
            $table->string('last_trigger', 20)->nullable();
            $table->unsignedBigInteger('last_triggered_by')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('fail_count')->default(0);
            $table->timestamps();

            $table->index('enabled');
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('task_key')->index();
            $table->string('status', 20)->default('queued');
            $table->string('trigger', 20)->default('manual');
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['task_key', 'id']);
            $table->index('created_at');
        });

        Schema::create('automation_heartbeats', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->json('meta')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_heartbeats');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automation_tasks');
    }
};
