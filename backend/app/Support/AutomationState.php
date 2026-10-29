<?php

namespace App\Support;

use App\Models\AutomationHeartbeat;
use App\Models\AutomationTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persists the runtime state of scheduled tasks and worker heartbeats.
 *
 * Every method is defensive: the admin panel must keep rendering even before
 * the automation migration has run or if the database hiccups.
 */
final class AutomationState
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public static function enabled(string $key): bool
    {
        try {
            return (bool) Cache::remember(
                'automation:enabled:'.$key,
                20,
                fn () => AutomationTask::query()->where('task_key', $key)->value('enabled') ?? true,
            );
        } catch (Throwable $e) {
            return true;
        }
    }

    public static function forget(string $key): void
    {
        try {
            Cache::forget('automation:enabled:'.$key);
        } catch (Throwable $e) {
            // Cache is optional for this control.
        }
    }

    public static function markQueued(string $key, string $trigger = 'schedule', ?int $userId = null, ?string $note = null): void
    {
        self::write($key, [
            'last_status' => self::STATUS_QUEUED,
            'last_run_at' => now(),
            'last_finished_at' => null,
            'last_duration_ms' => null,
            'last_trigger' => $trigger,
            'last_triggered_by' => $userId,
            'last_output' => $note ? Str::limit($note, 6000) : null,
            'last_error' => null,
        ]);
    }

    public static function markStarted(string $key, string $trigger = 'schedule', ?int $userId = null): void
    {
        self::write($key, [
            'last_status' => self::STATUS_RUNNING,
            'last_run_at' => now(),
            'last_finished_at' => null,
            'last_duration_ms' => null,
            'last_trigger' => $trigger,
            'last_triggered_by' => $userId,
            'last_error' => null,
        ]);
    }

    public static function markFinished(
        string $key,
        bool $success,
        ?string $output = null,
        ?string $error = null,
        ?int $durationMs = null,
    ): void {
        try {
            $task = AutomationTask::query()->firstOrCreate(['task_key' => $key]);
            $task->forceFill([
                'last_status' => $success ? self::STATUS_SUCCESS : self::STATUS_FAILED,
                'last_finished_at' => now(),
                'last_duration_ms' => $durationMs,
                'last_output' => $output !== null && trim($output) !== '' ? Str::limit(trim($output), 6000) : null,
                'last_error' => $success ? null : Str::limit($error ?: 'Unknown error', 6000),
                'run_count' => $task->run_count + 1,
                'fail_count' => $task->fail_count + ($success ? 0 : 1),
            ])->save();
        } catch (Throwable $e) {
            Log::debug('Automation state update failed', ['task' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function write(string $key, array $values): void
    {
        try {
            $task = AutomationTask::query()->firstOrCreate(['task_key' => $key]);
            $task->forceFill($values)->save();
        } catch (Throwable $e) {
            Log::debug('Automation state write failed', ['task' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Record that a process is alive.
     *
     * @param array<string, mixed> $meta
     */
    public static function heartbeat(string $name, array $meta = []): void
    {
        try {
            DB::table('automation_heartbeats')->updateOrInsert(
                ['name' => $name],
                ['meta' => json_encode($meta), 'updated_at' => now()],
            );
        } catch (Throwable $e) {
            Log::debug('Automation heartbeat failed', ['name' => $name, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{status: string, label: string, tone: string, age_seconds: ?int, last_at: ?string, human: string, meta: array<string, mixed>}
     */
    public static function heartbeatState(string $name, int $freshSeconds = 150, int $delayedSeconds = 600): array
    {
        try {
            $row = AutomationHeartbeat::query()->find($name);
        } catch (Throwable $e) {
            $row = null;
        }

        if (!$row || !$row->updated_at) {
            return [
                'status' => 'never',
                'label' => 'No heartbeat',
                'tone' => 'rose',
                'age_seconds' => null,
                'last_at' => null,
                'human' => 'Never',
                'meta' => [],
            ];
        }

        $age = max(0, (int) $row->updated_at->diffInSeconds(now()));
        $status = $age <= $freshSeconds ? 'online' : ($age <= $delayedSeconds ? 'delayed' : 'offline');
        $label = match ($status) {
            'online' => 'Online',
            'delayed' => 'Delayed',
            default => 'Offline',
        };
        $tone = match ($status) {
            'online' => 'emerald',
            'delayed' => 'amber',
            default => 'rose',
        };

        return [
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'age_seconds' => $age,
            'last_at' => $row->updated_at->toIso8601String(),
            'human' => $row->updated_at->diffForHumans(),
            'meta' => $row->meta ?: [],
        ];
    }

    public static function formatDuration(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return '—';
        }

        if ($milliseconds < 1000) {
            return max(1, $milliseconds).' ms';
        }

        if ($milliseconds < 60000) {
            return number_format($milliseconds / 1000, 1).' s';
        }

        $minutes = intdiv($milliseconds, 60000);
        $seconds = (int) round(($milliseconds % 60000) / 1000);

        return $minutes.'m'.($seconds > 0 ? ' '.$seconds.'s' : '');
    }

    public static function humanAge(?Carbon $at): string
    {
        return $at ? $at->diffForHumans() : 'Never';
    }
}
