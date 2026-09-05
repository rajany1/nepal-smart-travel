<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('location_accuracy', 8, 2)->nullable();
            $table->enum('status', ['active', 'resolved', 'cancelled', 'expired'])->default('active');
            $table->enum('emergency_type', ['medical', 'accident', 'flood', 'other'])->default('other');
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_location_update_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'latitude', 'longitude']);
            $table->index(['user_id', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alerts');
    }
};
