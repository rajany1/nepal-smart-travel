<?php

namespace App\Jobs;

use App\Support\AutomationState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lightweight probe used by the "Test queue worker" button.
 * If a worker is alive it updates the worker heartbeat within seconds.
 */
class PingQueueWorker implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public ?int $requestedBy = null)
    {
    }

    public function handle(): void
    {
        AutomationState::heartbeat('worker', [
            'source' => 'manual-test',
            'job' => self::class,
            'queue' => $this->queue ?? 'default',
            'requested_by' => $this->requestedBy,
            'at' => now()->toIso8601String(),
        ]);
    }
}
