<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_grid', function (Blueprint $table) {
            $table->id();
            $table->double('grid_lat', 7);
            $table->double('grid_lng', 7);
            $table->unsignedSmallInteger('weather_code');
            $table->double('temperature')->nullable();
            $table->double('precipitation')->nullable();
            $table->double('wind_speed')->nullable();
            $table->double('humidity')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['grid_lat', 'grid_lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_grid');
    }
};
