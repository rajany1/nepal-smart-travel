<?php

namespace App\Jobs;

use App\Services\AutomationService;
use App\Support\AutomationRegistry;
use App\Support\AutomationState;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes one scheduled task on demand from the admin panel.
 *
 * The command runs inside the queue worker so a slow task (OSM refresh, purge)
 * never blocks the admin HTTP request. Progress is written to automation_runs
 * and the task's last-run state, which the page polls.
 */
class RunAutomationTask implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /** Scheduled copies of the same task must not pile up in the queue. */
    public int $uniqueFor = 900;

    public function __construct(
        public string $taskKey,
        public ?int $userId = null,
        public ?int $runId = null,
        public string $trigger = 'manual',
    ) {
    }

    public function uniqueId(): string
    {
        // Manual runs get their own unique id (the run row already prevents
        // duplicate executions); scheduled runs are unique per task.
        return $this->trigger.':'.$this->taskKey.($this->runId ? ':'.$this->runId : '');
    }

    public function middleware(): array
    {
        if ($this->trigger !== 'schedule') {
            return [];
        }

        return [(new WithoutOverlapping('automation-task:'.$this->taskKey, 1800))->dontRelease()];
    }

    public function handle(AutomationService $service): void
    {
        AutomationState::heartbeat('worker', [
            'source' => 'automation-task',
            'task' => $this->taskKey,
            'queue' => $this->queue ?: 'default',
            'at' => now()->toIso8601String(),
        ]);

        $definition = AutomationRegistry::find($this->taskKey);
        $run = $this->runId ? \App\Models\AutomationRun::query()->find($this->runId) : null;

        if (!$definition) {
            $this->failRun($run, $this->taskKey, 'Unknown automation task.');
            return;
        }

        $lock = null;
        try {
            $lock = Cache::lock('automation:run:'.$this->taskKey, 1800);
            if (!$lock->get()) {
                $this->failRun($run, $this->taskKey, 'This task is already running. Wait for it to finish or try again.');
                return;
            }
        } catch (Throwable $e) {
            // If the cache store is unavailable, still allow the run.
            $lock = null;
        }

        $startedAt = now();
        $started = microtime(true);
        $output = null;

        AutomationState::markStarted($this->taskKey, $this->trigger, $this->userId);
        if ($run) {
            $run->forceFill([
                'status' => AutomationState::STATUS_RUNNING,
                'started_at' => $startedAt,
                'error' => null,
            ])->save();
        }

        try {
            if (!empty($definition['job'])) {
                $class = $definition['job'];
                $job = new $class(...($definition['job_args'] ?? []));
                app()->call([$job, 'handle']);
                $output = class_basename($class).' completed.';
            } else {
                $exitCode = Artisan::call($definition['command'], $definition['args'] ?? []);
                $output = trim(Artisan::output());

                if ($exitCode !== 0) {
                    throw new \RuntimeException("Command exited with code {$exitCode}.");
                }
            }

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            AutomationState::markFinished($this->taskKey, true, $output, null, $durationMs);
            $this->finishRun($run, true, $output, null, $durationMs);
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $message = Str::limit($e->getMessage() ?: class_basename($e), 2000);
            AutomationState::markFinished($this->taskKey, false, $output, $message, $durationMs);
            $this->finishRun($run, false, $output, $message, $durationMs);

            Log::error('Automation task failed', [
                'task' => $this->taskKey,
                'error' => $e->getMessage(),
            ]);
        } finally {
            try {
                $lock?->release();
            } catch (Throwable $e) {
                // Lock expires on its own.
            }
        }
    }

    private function finishRun(?object $run, bool $success, ?string $output, ?string $error, int $durationMs): void
    {
        if (!$run) {
            return;
        }

        $run->forceFill([
            'status' => $success ? AutomationState::STATUS_SUCCESS : AutomationState::STATUS_FAILED,
            'finished_at' => now(),
            'duration_ms' => $durationMs,
            'output' => $output !== null && trim($output) !== '' ? Str::limit(trim($output), 6000) : null,
            'error' => $error !== null ? Str::limit($error, 6000) : null,
        ])->save();
    }

    private function failRun(?object $run, string $taskKey, string $message): void
    {
        AutomationState::markFinished($taskKey, false, null, $message, 0);

        if ($run) {
            $run->forceFill([
                'status' => AutomationState::STATUS_FAILED,
                'finished_at' => now(),
                'error' => $message,
            ])->save();
        }
    }
}
