<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();

            $table->uuid()->unique();

            $table->string('title');

            $table->text('description');

            $table->enum('alert_type', [
                'earthquake',
                'flood',
                'landslide',
                'weather',
                'strike',
                'emergency'
            ]);

            $table->enum('severity', [
                'info',
                'medium',
                'high',
                'critical'
            ]);

            $table->decimal('latitude', 10, 7)
                ->nullable();

            $table->decimal('longitude', 10, 7)
                ->nullable();

            $table->timestamp('expires_at')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
