<?php

namespace App\Jobs;

use App\Services\OsmImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportOsmDistrictJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;
    public $backoff = [60, 300];

    public function __construct(public string $district, public bool $refresh = true)
    {
    }

    public function handle(OsmImportService $service): void
    {
        $service->importDistrict($this->district, $this->refresh);
    }

    /**
     * One district failing must never take other districts down — the error
     * is recorded in the Redis status metadata and the job stops retrying.
     */
    public function failed(\Throwable $e): void
    {
        app(OsmImportService::class)->setStatus($this->district, 'failed', [
            'error' => $e->getMessage(),
            'completed_at' => now()->toIso8601String(),
        ]);
        \Illuminate\Support\Facades\Log::error('OSM import district failed', [
            'district' => $this->district,
            'error' => $e->getMessage(),
        ]);
    }
}