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
        Schema::create('road_conditions', function (Blueprint $table) {
            $table->id();

            $table->string('road_name');

            $table->enum('condition_type', [
                'blockage',
                'traffic',
                'construction',
                'landslide'
            ]);

            $table->enum('severity', [
                'low',
                'medium',
                'high',
                'critical'
            ]);

            $table->text('description');

            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            $table->string('district')->nullable();

            $table->boolean('is_active')
                ->default(true);

            $table->enum('source', [
                'community',
                'government',
                'ai'
            ]);

            $table->timestamp('expected_clear_at')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('road_conditions');
    }
};
