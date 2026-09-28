<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location Integrity layer for report submissions.
 *
 * Purely additive + nullable: no existing report/place rows are touched and
 * older app versions that do not send the new fields keep working unchanged.
 *
 * - location_integrity_status: server-normalised evaluation
 *   (genuine | suspicious | cannot_determine) — never trusted straight
 *   from the client, always re-evaluated server-side.
 * - mock_location_detected: the raw client claim (evidence only, nullable
 *   because the client may be unable to determine it).
 * - location_integrity_source: which detector produced the client claim
 *   (e.g. "android", "unavailable").
 * - location_accuracy / location_timestamp: the fix metadata the report
 *   coordinates were captured with (used for sanity/consistency checks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (!Schema::hasColumn('reports', 'location_integrity_status')) {
                $table->string('location_integrity_status', 20)->nullable()->default(null)
                    ->after('gps_distance_km')
                    ->comment('genuine | suspicious | cannot_determine (server-evaluated)');
            }

            if (!Schema::hasColumn('reports', 'mock_location_detected')) {
                $table->boolean('mock_location_detected')->nullable()->default(null)
                    ->after('location_integrity_status')
                    ->comment('Client-claimed mock flag — evidence, not proof');
            }

            if (!Schema::hasColumn('reports', 'location_integrity_source')) {
                $table->string('location_integrity_source', 32)->nullable()->default(null)
                    ->after('mock_location_detected')
                    ->comment('Detector source, e.g. android / unavailable');
            }

            if (!Schema::hasColumn('reports', 'location_accuracy')) {
                $table->decimal('location_accuracy', 8, 2)->nullable()->default(null)
                    ->after('location_integrity_source')
                    ->comment('Reported GPS accuracy in metres');
            }

            if (!Schema::hasColumn('reports', 'location_timestamp')) {
                $table->timestamp('location_timestamp')->nullable()->default(null)
                    ->after('location_accuracy')
                    ->comment('Device timestamp of the reported GPS fix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            foreach ([
                'location_integrity_status',
                'mock_location_detected',
                'location_integrity_source',
                'location_accuracy',
                'location_timestamp',
            ] as $column) {
                if (Schema::hasColumn('reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
