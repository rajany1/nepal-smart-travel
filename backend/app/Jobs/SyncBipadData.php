<?php

namespace App\Jobs;

use App\Services\BipadSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncBipadData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    protected string $endpoint;

    public function __construct(string $endpoint = 'both')
    {
        $this->endpoint = $endpoint;
        $this->onQueue('bipad-sync');
    }

    public function handle(BipadSyncService $syncService): void
    {
        Log::info('Starting BIPAD sync job', ['endpoint' => $this->endpoint]);

        try {
            if ($this->endpoint === 'incident' || $this->endpoint === 'both') {
                $log = $syncService->syncIncidents();
                Log::info('BIPAD incidents sync completed', [
                    'status' => $log->status,
                    'fetched' => $log->records_fetched,
                    'created' => $log->records_created,
                    'updated' => $log->records_updated,
                    'duration_ms' => $log->duration_ms,
                ]);
            }

            if ($this->endpoint === 'alert' || $this->endpoint === 'both') {
                $log = $syncService->syncAlerts();
                Log::info('BIPAD alerts sync completed', [
                    'status' => $log->status,
                    'fetched' => $log->records_fetched,
                    'created' => $log->records_created,
                    'updated' => $log->records_updated,
                    'duration_ms' => $log->duration_ms,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('BIPAD sync job failed', [
                'endpoint' => $this->endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    public function tags(): array
    {
        return ['bipad', 'sync', $this->endpoint];
    }
}