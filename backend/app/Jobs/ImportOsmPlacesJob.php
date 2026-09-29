<?php

namespace App\Jobs;

use App\Console\Commands\ImportOsmPlaces;
use App\Services\PlacesCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs the long `places:import-osm` command off the HTTP request.
 *
 * The admin panel dispatches this instead of calling Artisan synchronously,
 * so a production-wide import can no longer hold a PHP-FPM worker until the
 * proxy returns 504. Progress is published by the command itself into the
 * Redis hash read by GET /admin/places/import-osm/status.
 */
class ImportOsmPlacesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * One pass per dispatch. Retries live inside the command (per city, on
     * top of OsmOverpassService' own mirror fallback), and a second pass
     * would only re-download everything the first one already imported.
     */
    public $tries = 1;

    public $timeout = 3600;

    /** Keep a stale unique lock from blocking re-dispatch for long. */
    public int $uniqueFor = 3600;

    public function __construct(public array $options = [])
    {
    }

    public function uniqueId(): string
    {
        return 'places:import-osm';
    }

    public function handle(): void
    {
        $exitCode = Artisan::call('places:import-osm', $this->options);

        $output = preg_replace('/\e\[[0-9;]*[A-Za-z]/', '', (string) Artisan::output());

        $fields = [
            'exit_code' => $exitCode,
            'completed_at' => now()->toIso8601String(),
            'output' => mb_substr($output, -4000),
        ];

        if ($exitCode === 0) {
            $fields['status'] = 'completed';
        } else {
            $fields['status'] = 'failed';
            $fields['message'] = "OSM import exited with code {$exitCode}.";
        }

        ImportOsmPlaces::writeStatus($fields);

        if ($exitCode === 0) {
            PlacesCache::bump();
            Log::info('OSM places import finished', ['options' => $this->options]);
        } else {
            Log::warning('OSM places import finished with errors', [
                'exit_code' => $exitCode,
                'options' => $this->options,
            ]);
        }
    }

    /**
     * The database queue re-offers a reserved job after `retry_after` (90s),
     * which happens while this import is still running. That duplicate copy
     * dies right away (tries = 1) while the original run keeps going, so it
     * must not overwrite the live progress.
     */
    public function failed(\Throwable $e): void
    {
        if ($this->job && $this->job->attempts() > 1) {
            Log::warning('OSM places import: duplicate queue reservation ignored', [
                'attempts' => $this->job->attempts(),
            ]);

            return;
        }

        ImportOsmPlaces::writeStatus([
            'status' => 'failed',
            'completed_at' => now()->toIso8601String(),
            'message' => 'OSM import failed: ' . $e->getMessage(),
        ]);

        Log::error('OSM places import failed', ['error' => $e->getMessage()]);
    }
}
