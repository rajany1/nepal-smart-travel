<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained('sos_alerts')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->default('false_alarm');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['sos_alert_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_reports');
    }
};
