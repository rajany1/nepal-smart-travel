<?php

namespace App\Support;

use App\Jobs\SyncBipadData;

/**
 * Single source of truth for every scheduled automation task.
 *
 * The same list drives:
 *  - the schedule registrations in bootstrap/app.php
 *  - the enable/disable toggles
 *  - the admin catalogue (name, description, impact, command preview)
 *
 * Add a task here and it immediately appears on the Automation page and is
 * registered with the scheduler. No second place to update.
 */
final class AutomationRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'ai.process-tasks' => [
                'name' => 'AI task processor',
                'category' => 'AI pipeline',
                'icon' => 'robot',
                'color' => 'violet',
                'command' => 'ai:process-tasks',
                'args' => [],
                'cron' => '* * * * *',
                'schedule' => 'Every minute',
                'description' => 'Picks up pending AI agent tasks and runs them through the agent assigned to them.',
                'impact' => 'AI tasks stay queued, so analysis and translations wait for a person.',
                'overlap' => 60,
            ],
            'ai.process-reports' => [
                'name' => 'Report Manager AI',
                'category' => 'AI pipeline',
                'icon' => 'file-circle-check',
                'color' => 'violet',
                'command' => 'ai:process-reports',
                'args' => [],
                'cron' => '*/15 * * * *',
                'schedule' => 'Every 15 minutes',
                'description' => 'Reviews new reports, classifies them, sets priority and routes them to the right moderation queue.',
                'impact' => 'New reports wait for manual triage instead of being pre-sorted.',
                'overlap' => 120,
            ],
            'ai.auto-work' => [
                'name' => 'AI background work',
                'category' => 'AI pipeline',
                'icon' => 'wand-magic-sparkles',
                'color' => 'violet',
                'command' => 'ai:auto-work',
                'args' => [],
                'cron' => '*/30 * * * *',
                'schedule' => 'Every 30 minutes',
                'description' => 'Lets AI agents perform background work such as translation drafts and moderation suggestions.',
                'impact' => 'Background AI improvements pause until the next run.',
                'overlap' => 300,
            ],
            'ai.safety-sweep' => [
                'name' => 'Content safety sweep',
                'category' => 'Safety',
                'icon' => 'shield-halved',
                'color' => 'rose',
                'command' => 'ai:safety-sweep',
                'args' => [],
                'cron' => '* * * * *',
                'schedule' => 'Every minute',
                'description' => 'Scans content for banned words and escalates repeat offenders through warnings and strikes.',
                'impact' => 'Unsafe content can stay visible until a moderator finds it manually.',
                'overlap' => 120,
            ],
            'weather.fetch' => [
                'name' => 'Weather grid refresh',
                'category' => 'Data sync',
                'icon' => 'cloud-sun',
                'color' => 'sky',
                'command' => 'weather:fetch',
                'args' => [],
                'cron' => '*/15 * * * *',
                'schedule' => 'Every 15 minutes',
                'description' => 'Fetches current weather from Open-Meteo for the Nepal grid used by place and alert screens.',
                'impact' => 'Weather chips keep showing the previous snapshot.',
                'overlap' => 10,
            ],
            'bipad.sync' => [
                'name' => 'BIPAD disaster sync',
                'category' => 'Data sync',
                'icon' => 'satellite-dish',
                'color' => 'orange',
                'command' => null,
                'args' => [],
                'job' => SyncBipadData::class,
                'job_args' => ['both'],
                'queue' => 'bipad-sync',
                'cron' => '* * * * *',
                'schedule' => 'Every minute',
                'description' => 'Queues the BIPAD API sync that pulls the latest incidents and official alerts into the platform.',
                'impact' => 'Official disaster alerts and incidents stop refreshing.',
                'overlap' => 5,
            ],
            'osm.refresh' => [
                'name' => 'OSM district refresh',
                'category' => 'Data sync',
                'icon' => 'map-location-dot',
                'color' => 'emerald',
                'command' => 'osm:refresh',
                'args' => [],
                'cron' => '0 3 * * 0',
                'schedule' => 'Weekly, Sunday at 3:00 AM',
                'description' => 'Refreshes previously imported OpenStreetMap districts through the queue (upsert only, no duplicates).',
                'impact' => 'OSM place data slowly goes stale, but existing places still work.',
                'overlap' => 720,
            ],
            'map.refresh-cache' => [
                'name' => 'Nepal map cache refresh',
                'category' => 'Data sync',
                'icon' => 'layer-group',
                'color' => 'emerald',
                'command' => 'map:refresh-cache',
                'args' => [],
                'cron' => '0 3 * * *',
                'schedule' => 'Daily at 3:00 AM',
                'description' => 'Rebuilds the Nepal ADM0/ADM1/ADM2 boundary GeoJSON cache in Redis.',
                'impact' => 'Maps keep using the old boundary cache until the next successful run.',
                'overlap' => 60,
            ],
            'offers.expire' => [
                'name' => 'Expire reward offers',
                'category' => 'Marketplace',
                'icon' => 'tag',
                'color' => 'amber',
                'command' => 'offers:expire',
                'args' => [],
                'cron' => '* * * * *',
                'schedule' => 'Every minute',
                'description' => 'Pauses reward offers whose end time has passed. The offer list also checks this lazily.',
                'impact' => 'Expired offers can still look active until someone opens the offer list.',
                'overlap' => 5,
            ],
            'ads.expire' => [
                'name' => 'Expire ad campaigns',
                'category' => 'Marketplace',
                'icon' => 'rectangle-ad',
                'color' => 'amber',
                'command' => 'ads:expire',
                'args' => [],
                'cron' => '* * * * *',
                'schedule' => 'Every minute',
                'description' => 'Pauses ad campaigns whose end time has passed and decays fraud scores for stale campaigns.',
                'impact' => 'Finished campaigns may keep serving until their next lazy check.',
                'overlap' => 5,
            ],
            'places.images-backfill' => [
                'name' => 'Place image backfill',
                'category' => 'Media',
                'icon' => 'images',
                'color' => 'indigo',
                'command' => 'places:images-backfill',
                'args' => ['--limit' => 50],
                'cron' => '*/30 * * * *',
                'schedule' => 'Every 30 minutes',
                'description' => 'Queues Wikimedia Commons image discovery for places that still have too few photos.',
                'impact' => 'Places with missing galleries stay incomplete until a detail page queues them.',
                'overlap' => 120,
            ],
            'data.purge' => [
                'name' => 'Retention purge',
                'category' => 'Maintenance',
                'icon' => 'broom',
                'color' => 'slate',
                'command' => 'data:purge',
                'args' => [],
                'cron' => '0 3 * * *',
                'schedule' => 'Daily at 3:00 AM',
                'description' => 'Deletes expired records according to the retention policies (logs, temporary data and similar).',
                'impact' => 'Old rows accumulate and slow down some reports over time.',
                'overlap' => 120,
            ],
            'coins.reconcile-self-rewards' => [
                'name' => 'Self-reward coin audit',
                'category' => 'Maintenance',
                'icon' => 'coins',
                'color' => 'teal',
                'command' => 'coins:reconcile-self-rewards',
                'args' => [],
                'cron' => '30 3 * * *',
                'schedule' => 'Daily at 3:30 AM',
                'description' => 'Finds coin credits where a report owner rewarded their own report and reverses them with ledger entries.',
                'impact' => 'Invalid self-reward coins are not automatically reversed.',
                'overlap' => 120,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Human-readable command line shown in the admin catalogue.
     *
     * @param array<string, mixed> $task
     */
    public static function commandPreview(array $task): string
    {
        if (!empty($task['job'])) {
            $args = implode(', ', array_map(fn ($arg) => is_string($arg) ? "'{$arg}'" : (string) $arg, $task['job_args'] ?? []));
            $queue = !empty($task['queue']) ? ' → '.$task['queue'].' queue' : '';

            return class_basename($task['job']).'('.trim($args).')'.$queue;
        }

        $args = '';
        foreach ($task['args'] ?? [] as $name => $value) {
            $args .= ' '.(is_string($name) ? $name.'='.$value : $value);
        }

        return 'php artisan '.$task['command'].$args;
    }

    /**
     * @param array<string, mixed> $task
     */
    public static function isQueuedJob(array $task): bool
    {
        return !empty($task['job']);
    }
}
