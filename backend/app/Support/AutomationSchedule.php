<?php

namespace App\Support;

use App\Jobs\RunAutomationTask;
use App\Support\AutomationRegistry;
use App\Support\AutomationState;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Registers every task from AutomationRegistry on Laravel's scheduler and
 * wires run tracking + the enable/disable switch.
 */
final class AutomationSchedule
{
    public static function register(Schedule $schedule): void
    {
        foreach (AutomationRegistry::all() as $key => $task) {
            $isJob = AutomationRegistry::isQueuedJob($task);

            if ($isJob) {
                $event = $schedule->job(
                    new RunAutomationTask($key, null, null, 'schedule'),
                    $task['queue'] ?? null,
                );
            } else {
                $event = $schedule->command($task['command'], $task['args'] ?? []);
            }

            $event->cron($task['cron'])
                ->description($task['name'].' — '.$task['schedule'])
                ->withoutOverlapping($task['overlap'] ?? 10)
                ->when(fn () => AutomationState::enabled($key));

            if ($isJob) {
                // The job itself marks the task running when a worker picks it
                // up. Do not mark it queued in the scheduled "before" callback:
                // that callback also runs when ShouldBeUnique skips a dispatch,
                // which would overwrite a live "running" status with "queued".
            } else {
                self::trackCommand($event, $key);
            }
        }

        // A heartbeat is the only reliable way to know the scheduler process is
        // actually alive: schedule:work/schedule:run persists nothing itself.
        $schedule->call(function () {
            AutomationState::heartbeat('scheduler', [
                'host' => gethostname() ?: 'unknown',
                'php' => PHP_VERSION,
                'at' => now()->toIso8601String(),
            ]);
        })
            ->everyMinute()
            ->description('Automation scheduler heartbeat')
            ->withoutOverlapping();
    }

    private static function trackCommand(Event $event, string $key): void
    {
        $clock = new \stdClass();
        $clock->startedAt = null;

        $event->before(function () use ($key, $clock) {
            $clock->startedAt = microtime(true);
            AutomationState::markStarted($key, 'schedule');
        });

        $event->onSuccessWithOutput(function ($output) use ($key, $clock) {
            $duration = $clock->startedAt ? (int) round((microtime(true) - $clock->startedAt) * 1000) : null;
            AutomationState::markFinished($key, true, (string) $output, null, $duration);
        });

        $event->onFailureWithOutput(function ($output) use ($key, $clock) {
            $duration = $clock->startedAt ? (int) round((microtime(true) - $clock->startedAt) * 1000) : null;
            AutomationState::markFinished($key, false, (string) $output, 'Command exited with a non-zero code.', $duration);
        });
    }
}
