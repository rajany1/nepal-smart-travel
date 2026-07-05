<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add EXIF photo GPS verification fields to reports table.
     * This enables the anti-fake-report system that validates
     * the photo's GPS coordinates match the user's reported location.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'photo_gps_lat')) {
                $table->decimal('photo_gps_lat', 10, 7)->nullable()->after('longitude')
                      ->comment('GPS latitude extracted from photo EXIF');
            }
            if (!Schema::hasColumn('reports', 'photo_gps_lng')) {
                $table->decimal('photo_gps_lng', 10, 7)->nullable()->after('photo_gps_lat')
                      ->comment('GPS longitude extracted from photo EXIF');
            }
            if (!Schema::hasColumn('reports', 'gps_verification_status')) {
                $table->string('gps_verification_status', 20)->default('none')->after('photo_gps_lng')
                      ->comment('GPS verification status: none, verified, mismatched, no_gps_data');
            }
            if (!Schema::hasColumn('reports', 'gps_distance_km')) {
                $table->decimal('gps_distance_km', 8, 3)->nullable()->after('gps_verification_status')
                      ->comment('Distance in km between photo GPS and report location');
            }
            if (!Schema::hasColumn('reports', 'photo_captured_at')) {
                $table->timestamp('photo_captured_at')->nullable()->after('gps_distance_km')
                      ->comment('Timestamp when photo was captured (from EXIF or client)');
            }
            if (!Schema::hasColumn('reports', 'is_live_capture')) {
                $table->boolean('is_live_capture')->default(false)->after('photo_captured_at')
                      ->comment('Whether photo was taken via in-app camera (true) or uploaded (false)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $columns = [
                'photo_gps_lat',
                'photo_gps_lng',
                'gps_verification_status',
                'gps_distance_km',
                'photo_captured_at',
                'is_live_capture',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};