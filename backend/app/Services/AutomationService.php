<?php

namespace App\Services;

use App\Models\AutomationRun;
use App\Models\AutomationTask;
use App\Support\AutomationRegistry;
use App\Support\AutomationState;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the automation control-centre payload (tasks, queues, failed jobs,
 * heartbeats) and exposes small helpers used by the admin actions.
 */
class AutomationService
{
    public const WORKER_COMMAND = 'php artisan queue:work database --queue=default,bipad-sync --tries=3 --timeout=300 --sleep=1';

    public const SCHEDULER_COMMAND = 'php artisan schedule:work';

    public const SERVE_ALL_COMMAND = 'php artisan serve:all';

    public function build(): array
    {
        $tasks = $this->tasks();
        $queue = $this->queueStats();
        $status = $this->statusPayload($tasks, $queue);
        $failed = $this->failedJobs();

        return [
            'generated_at' => now()->toIso8601String(),
            'scheduler' => $status['scheduler'],
            'worker' => $status['worker'],
            'queue' => $queue,
            'summary' => $status['summary'],
            'tasks' => $tasks,
            'queues' => $queue['queues'],
            'failed_jobs' => $failed['items'],
            'failed_total' => $failed['total'],
            'recent_runs' => $this->recentRuns(),
            'commands' => [
                'worker' => self::WORKER_COMMAND,
                'scheduler' => self::SCHEDULER_COMMAND,
                'all' => self::SERVE_ALL_COMMAND,
            ],
            'permissions' => [
                'manage' => true,
            ],
        ];
    }

    /** Lightweight version used by the main dashboard card. */
    public function overview(): array
    {
        $tasks = $this->tasks();
        $queue = $this->queueStats();
        $status = $this->statusPayload($tasks, $queue);

        return [
            'scheduler' => $status['scheduler'],
            'worker' => $status['worker'],
            'queue' => $queue,
            'summary' => $status['summary'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @param array<string, mixed> $queue
     * @return array<string, mixed>
     */
    private function statusPayload(array $tasks, array $queue): array
    {
        $scheduler = AutomationState::heartbeatState('scheduler', 150, 600);
        if (in_array($scheduler['status'], ['never', 'offline'], true)) {
            $scheduler['label'] = 'Not running';
            $scheduler['tone'] = 'rose';
        }

        $worker = AutomationState::heartbeatState('worker', 180, 900);
        if ($worker['status'] === 'offline' || $worker['status'] === 'never') {
            $worker['label'] = $queue['pending_jobs'] > 0 ? 'Queue stalled' : 'Idle / not detected';
            $worker['tone'] = $queue['pending_jobs'] > 0 ? 'rose' : 'amber';
        }

        $enabledTasks = array_values(array_filter($tasks, fn ($task) => $task['enabled']));
        $nextTask = $this->earliestNext($enabledTasks);
        $lastTask = $this->latestFinished($tasks);
        $failedTasks = array_values(array_filter($tasks, fn ($task) => $task['status'] === 'failed'));
        $staleTasks = array_values(array_filter($tasks, fn ($task) => $task['status'] === 'interrupted'));

        return [
            'scheduler' => $scheduler,
            'worker' => $worker,
            'summary' => [
                'tasks_total' => count($tasks),
                'tasks_enabled' => count($enabledTasks),
                'tasks_disabled' => count($tasks) - count($enabledTasks),
                'tasks_failed' => count($failedTasks),
                'tasks_stale' => count($staleTasks),
                'next_task' => $nextTask['name'] ?? null,
                'next_task_at' => $nextTask['next_run_at'] ?? null,
                'next_run_human' => $nextTask['next_run_human'] ?? 'No enabled task',
                'last_task' => $lastTask['name'] ?? null,
                'last_run_human' => $lastTask['last_finished_human'] ?? 'Never',
                'attention' => count($failedTasks) + count($staleTasks) + ($scheduler['status'] !== 'online' ? 1 : 0),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tasks(): array
    {
        $definitions = AutomationRegistry::all();

        try {
            $rows = AutomationTask::query()->get()->keyBy('task_key');
        } catch (Throwable $e) {
            $rows = collect();
        }

        $userIds = $rows->pluck('last_triggered_by')->filter()->unique()->values();
        $userNames = $userIds->isEmpty()
            ? collect()
            : DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');

        $tasks = [];
        foreach ($definitions as $key => $definition) {
            /** @var AutomationTask|null $row */
            $row = $rows->get($key);
            $enabled = $row ? (bool) $row->enabled : true;
            $status = $row?->last_status ?: 'never';

            // A worker restart can leave a task stuck in "running". Treat it as
            // interrupted after 30 minutes instead of pretending it is alive.
            if ($status === AutomationState::STATUS_RUNNING
                && $row?->last_run_at
                && $row->last_run_at->lt(now()->subMinutes(30))) {
                $status = 'interrupted';
            }
            if ($status === AutomationState::STATUS_QUEUED
                && $row?->last_run_at
                && $row->last_run_at->lt(now()->subMinutes(30))) {
                $status = 'stuck';
            }

            $nextRun = $enabled ? $this->nextRun($definition['cron']) : null;
            $statusMeta = $this->statusMeta($status);

            $tasks[] = [
                'key' => $key,
                'name' => $definition['name'],
                'category' => $definition['category'],
                'icon' => $definition['icon'],
                'color' => $definition['color'],
                'description' => $definition['description'],
                'impact' => $definition['impact'],
                'schedule' => $definition['schedule'],
                'cron' => $definition['cron'],
                'command_preview' => AutomationRegistry::commandPreview($definition),
                'is_job' => AutomationRegistry::isQueuedJob($definition),
                'queue' => $definition['queue'] ?? null,
                'enabled' => $enabled,
                'status' => $status,
                'status_label' => $statusMeta['label'],
                'status_tone' => $statusMeta['tone'],
                'last_run_at' => $row?->last_run_at?->toIso8601String(),
                'last_run_human' => AutomationState::humanAge($row?->last_run_at),
                'last_finished_at' => $row?->last_finished_at?->toIso8601String(),
                'last_finished_human' => AutomationState::humanAge($row?->last_finished_at),
                'duration_ms' => $row?->last_duration_ms,
                'duration_human' => AutomationState::formatDuration($row?->last_duration_ms),
                'last_output' => $row?->last_output,
                'last_error' => $row?->last_error,
                'last_trigger' => $row?->last_trigger,
                'last_triggered_by' => $row?->last_triggered_by
                    ? ($userNames[$row->last_triggered_by] ?? 'Admin #'.$row->last_triggered_by)
                    : null,
                'run_count' => (int) ($row?->run_count ?? 0),
                'fail_count' => (int) ($row?->fail_count ?? 0),
                'next_run_at' => $nextRun?->toIso8601String(),
                'next_run_human' => $nextRun ? $nextRun->diffForHumans() : ($enabled ? 'Not scheduled' : 'Paused'),
                'next_run_clock' => $nextRun?->format('M j, g:i A'),
                'is_running' => $status === AutomationState::STATUS_RUNNING,
                // Manual runs are allowed even when the schedule is paused:
                // an admin may want a one-off check without re-enabling the
                // recurring task. The controller and the job still guard
                // against duplicate concurrent runs.
                'can_run' => !in_array($status, [AutomationState::STATUS_RUNNING, AutomationState::STATUS_QUEUED], true),
            ];
        }

        return $tasks;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function task(string $key): ?array
    {
        foreach ($this->tasks() as $task) {
            if ($task['key'] === $key) {
                return $task;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function queueStats(): array
    {
        try {
            $queues = DB::table('jobs')
                ->selectRaw('queue, COUNT(*) as total, SUM(CASE WHEN reserved_at IS NULL THEN 0 ELSE 1 END) as processing, MIN(created_at) as oldest')
                ->groupBy('queue')
                ->orderByDesc('total')
                ->get()
                ->map(function ($row) {
                    $oldest = $row->oldest ? Carbon::parse($row->oldest) : null;

                    return [
                        'queue' => $row->queue ?: 'default',
                        'total' => (int) $row->total,
                        'processing' => (int) $row->processing,
                        'oldest_human' => $oldest ? $oldest->diffForHumans() : '—',
                        'oldest_minutes' => $oldest ? (int) $oldest->diffInMinutes(now()) : 0,
                    ];
                })
                ->all();

            $total = (int) array_sum(array_column($queues, 'total'));
            $processing = (int) array_sum(array_column($queues, 'processing'));
            $oldest = DB::table('jobs')->min('created_at');
            $oldestAt = $oldest ? Carbon::parse($oldest) : null;
            $failed = (int) DB::table('failed_jobs')->count();
        } catch (Throwable $e) {
            Log::debug('Automation queue stats failed', ['error' => $e->getMessage()]);
            $queues = [];
            $total = $processing = $failed = 0;
            $oldestAt = null;
        }

        return [
            'queues' => $queues,
            'pending_jobs' => $total,
            'processing_jobs' => $processing,
            'waiting_jobs' => max(0, $total - $processing),
            'failed_jobs' => $failed,
            'oldest_human' => $oldestAt ? $oldestAt->diffForHumans() : 'None waiting',
            'oldest_minutes' => $oldestAt ? (int) $oldestAt->diffInMinutes(now()) : null,
        ];
    }

    /**
     * @return array{total: int, items: array<int, array<string, mixed>>}
     */
    public function failedJobs(): array
    {
        try {
            $total = (int) DB::table('failed_jobs')->count();
            $rows = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(8)->get();
        } catch (Throwable $e) {
            return ['total' => 0, 'items' => []];
        }

        $items = $rows->map(function ($row) {
            $payload = json_decode($row->payload, true) ?: [];
            $jobClass = $payload['displayName'] ?? $payload['job'] ?? 'Unknown job';
            $exception = Str::of((string) $row->exception)->replace("\r", '')->explode("\n")->first();

            return [
                'uuid' => $row->uuid,
                'job' => class_basename($jobClass),
                'job_full' => $jobClass,
                'queue' => $row->queue ?: 'default',
                'exception' => Str::limit(trim((string) $exception), 220),
                'failed_at' => $row->failed_at ? Carbon::parse($row->failed_at)->diffForHumans() : '—',
            ];
        })->all();

        return ['total' => $total, 'items' => $items];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentRuns(): array
    {
        try {
            $rows = DB::table('automation_runs as runs')
                ->leftJoin('users', 'users.id', '=', 'runs.triggered_by')
                ->orderByDesc('runs.id')
                ->limit(8)
                ->get([
                    'runs.*',
                    'users.name as user_name',
                ]);
        } catch (Throwable $e) {
            return [];
        }

        $definitions = AutomationRegistry::all();

        return $rows->map(function ($row) use ($definitions) {
            $task = $definitions[$row->task_key] ?? null;
            $status = $this->statusMeta((string) $row->status);

            return [
                'id' => (int) $row->id,
                'task_key' => $row->task_key,
                'name' => $task['name'] ?? $row->task_key,
                'status' => $row->status,
                'status_label' => $status['label'],
                'status_tone' => $status['tone'],
                'trigger' => $row->trigger,
                'user' => $row->user_name ?: 'System',
                'when' => $row->created_at ? Carbon::parse($row->created_at)->diffForHumans() : '—',
                'duration_human' => AutomationState::formatDuration($row->duration_ms !== null ? (int) $row->duration_ms : null),
                'error' => $row->error ? Str::limit((string) $row->error, 220) : null,
            ];
        })->all();
    }

    private function nextRun(string $cron): ?Carbon
    {
        try {
            return Carbon::instance(CronExpression::factory($cron)->getNextRunDate(now()));
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @return array<string, mixed>|null
     */
    private function earliestNext(array $tasks): ?array
    {
        $candidates = array_filter($tasks, fn ($task) => !empty($task['next_run_at']));
        usort($candidates, fn ($a, $b) => strcmp($a['next_run_at'], $b['next_run_at']));

        return $candidates[0] ?? null;
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @return array<string, mixed>|null
     */
    private function latestFinished(array $tasks): ?array
    {
        $candidates = array_filter($tasks, fn ($task) => !empty($task['last_finished_at']));
        usort($candidates, fn ($a, $b) => strcmp($b['last_finished_at'], $a['last_finished_at']));

        return $candidates[0] ?? null;
    }

    /**
     * @return array{label: string, tone: string}
     */
    private function statusMeta(string $status): array
    {
        return match ($status) {
            AutomationState::STATUS_SUCCESS => ['label' => 'Success', 'tone' => 'emerald'],
            AutomationState::STATUS_FAILED => ['label' => 'Failed', 'tone' => 'rose'],
            AutomationState::STATUS_RUNNING => ['label' => 'Running', 'tone' => 'sky'],
            AutomationState::STATUS_QUEUED => ['label' => 'Queued', 'tone' => 'amber'],
            'interrupted' => ['label' => 'Interrupted', 'tone' => 'amber'],
            'stuck' => ['label' => 'Stuck in queue', 'tone' => 'amber'],
            default => ['label' => 'Never run', 'tone' => 'slate'],
        };
    }

    public function recordRun(string $taskKey, ?int $userId, string $trigger = 'manual'): AutomationRun
    {
        $run = AutomationRun::query()->create([
            'task_key' => $taskKey,
            'status' => AutomationState::STATUS_QUEUED,
            'trigger' => $trigger,
            'triggered_by' => $userId,
        ]);

        AutomationRun::prune();

        return $run;
    }
}
