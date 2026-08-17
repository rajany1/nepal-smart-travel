<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curated_routes', function (Blueprint $table) {
            $table->string('route_type')->default('itinerary')->index()->after('slug');
            $table->string('difficulty')->nullable()->index()->after('route_type');
            $table->unsignedInteger('max_altitude_m')->nullable()->after('best_season');
            $table->decimal('total_distance_km', 8, 2)->nullable()->after('max_altitude_m');
            $table->unsignedInteger('elevation_gain_m')->nullable()->after('total_distance_km');
            $table->string('starting_point')->nullable()->after('elevation_gain_m');
            $table->string('ending_point')->nullable()->after('starting_point');
            $table->json('track')->nullable()->after('waypoints');
        });
    }

    public function down(): void
    {
        Schema::table('curated_routes', function (Blueprint $table) {
            $table->dropColumn([
                'route_type', 'difficulty', 'max_altitude_m', 'total_distance_km',
                'elevation_gain_m', 'starting_point', 'ending_point', 'track',
            ]);
        });
    }
};
