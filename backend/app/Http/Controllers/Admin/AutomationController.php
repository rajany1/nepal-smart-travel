<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PingQueueWorker;
use App\Jobs\RunAutomationTask;
use App\Models\AuditLog;
use App\Models\AutomationTask;
use App\Services\AutomationService;
use App\Support\AutomationRegistry;
use App\Support\AutomationState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AutomationController extends Controller
{
    public function __construct(private AutomationService $automation)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeManage($request);

        return view('admin.automation', [
            'data' => $this->automation->build(),
        ]);
    }

    /** JSON refresh for the live status cards and task list. */
    public function data(Request $request)
    {
        $this->authorizeManage($request);

        return response()
            ->json(['success' => true, 'data' => $this->automation->build()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function toggle(Request $request, string $key)
    {
        $this->authorizeManage($request);
        $this->assertTaskExists($key);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $task = AutomationTask::query()->firstOrCreate(['task_key' => $key]);
        $task->enabled = (bool) $validated['enabled'];
        $task->save();
        AutomationState::forget($key);

        $definition = AutomationRegistry::find($key);
        $label = $definition['name'] ?? $key;

        $this->audit(
            $task->enabled ? 'automation.task.enabled' : 'automation.task.disabled',
            ($task->enabled ? 'Enabled' : 'Disabled')." scheduled task '{$label}'",
            ['task' => $key],
            $request,
        );

        return response()->json([
            'success' => true,
            'message' => $task->enabled
                ? "{$label} will run on its next schedule."
                : "{$label} is paused and will be skipped by the scheduler.",
            'task' => $this->automation->task($key),
        ]);
    }

    public function run(Request $request, string $key)
    {
        $this->authorizeManage($request);
        $definition = $this->assertTaskExists($key);

        $current = $this->automation->task($key);
        if ($current && !$current['can_run']) {
            return response()->json([
                'success' => false,
                'message' => 'This task is already running or queued. Wait for it to finish before starting another run.',
            ], 409);
        }

        $user = Auth::user();
        $run = $this->automation->recordRun($key, $user?->id, 'manual');
        AutomationState::markQueued(
            $key,
            'manual',
            $user?->id,
            'Queued by '.($user?->name ?: 'an admin').' for a manual run.',
        );

        try {
            RunAutomationTask::dispatch($key, $user?->id, $run->id, 'manual');
        } catch (Throwable $e) {
            $message = 'Could not queue the task: '.$e->getMessage();
            $run->forceFill([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $message,
            ])->save();
            AutomationState::markFinished($key, false, null, $message, 0);

            return response()->json(['success' => false, 'message' => $message], 500);
        }

        $this->audit(
            'automation.task.run',
            "Queued scheduled task '{$definition['name']}' for a manual run",
            ['task' => $key, 'run_id' => $run->id],
            $request,
        );

        return response()->json([
            'success' => true,
            'message' => "{$definition['name']} was queued. The status will update when a worker picks it up.",
            'task' => $this->automation->task($key),
            'run_id' => $run->id,
        ]);
    }

    public function retryFailed(Request $request)
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
        ]);

        if (!empty($validated['uuid'])) {
            $exists = DB::table('failed_jobs')->where('uuid', $validated['uuid'])->exists();
            if (!$exists) {
                return response()->json(['success' => false, 'message' => 'That failed job no longer exists.'], 404);
            }
            Artisan::call('queue:retry', ['id' => [$validated['uuid']]]);
            $message = 'Failed job pushed back onto its queue.';
            $action = 'automation.queue.retry-one';
        } else {
            Artisan::call('queue:retry', ['id' => ['all']]);
            $message = 'All failed jobs were pushed back onto their queues.';
            $action = 'automation.queue.retry-all';
        }

        $this->audit($action, $message, ['uuid' => $validated['uuid'] ?? null], $request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'output' => trim(Artisan::output()),
            'data' => $this->automation->build(),
        ]);
    }

    public function forgetFailed(Request $request)
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
        ]);

        if (!empty($validated['uuid'])) {
            $exists = DB::table('failed_jobs')->where('uuid', $validated['uuid'])->exists();
            if (!$exists) {
                return response()->json(['success' => false, 'message' => 'That failed job no longer exists.'], 404);
            }
            Artisan::call('queue:forget', ['id' => $validated['uuid']]);
            $message = 'Failed job removed from the failed list.';
            $action = 'automation.queue.forget-one';
        } else {
            Artisan::call('queue:flush');
            $message = 'All failed jobs were cleared. They cannot be retried after this.';
            $action = 'automation.queue.flush';
        }

        $this->audit($action, $message, ['uuid' => $validated['uuid'] ?? null], $request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $this->automation->build(),
        ]);
    }

    public function restartWorkers(Request $request)
    {
        $this->authorizeManage($request);

        Artisan::call('queue:restart');
        $this->audit(
            'automation.queue.restart',
            'Requested a graceful queue worker restart',
            [],
            $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Restart signal sent. Workers finish their current job, then exit; the watchdog should start them again.',
            'data' => $this->automation->build(),
        ]);
    }

    public function pingWorker(Request $request)
    {
        $this->authorizeManage($request);

        $user = Auth::user();
        PingQueueWorker::dispatch($user?->id);

        $this->audit(
            'automation.queue.ping',
            'Queued a test job to verify the queue worker is running',
            [],
            $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Test job queued. If the worker is alive, its heartbeat will refresh within a few seconds.',
            'data' => $this->automation->build(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function assertTaskExists(string $key): array
    {
        $definition = AutomationRegistry::find($key);
        if (!$definition) {
            abort(404, 'Unknown automation task.');
        }

        return $definition;
    }

    private function authorizeManage(Request $request): void
    {
        $user = Auth::user();

        if (!$user || !$user->hasPermission('manage_automation')) {
            if ($request->expectsJson()) {
                abort(403, 'You do not have permission to manage automation.');
            }
            abort(403, 'You do not have permission to manage automation.');
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function audit(string $action, string $description, array $metadata, Request $request): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => Auth::id(),
                'action' => $action,
                'resource_type' => 'automation',
                'resource_id' => null,
                'description' => Str::limit($description, 500),
                'metadata' => $metadata,
                'ip_address' => $request->ip(),
            ]);
        } catch (Throwable $e) {
            // Automation controls should still work if audit logging fails.
        }
    }
}
