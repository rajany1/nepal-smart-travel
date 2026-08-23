<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;

/**
 * Live-feed backbone for the admin realtime layer.
 *
 * Every watched table exposes a cheap fingerprint (MAX(id) + MAX(updated_at)).
 * The browser polls /admin/live-feed/changes with its last fingerprint and
 * receives only the rows that actually changed (inserts, updates, deletes).
 * Hard deletes are tracked via a short-lived Redis queue per table.
 */
class LiveFeed
{
    /** Tables watched by the admin live feed. Keep in sync with migrations. */
    public const TABLES = [
        'places', 'place_reviews', 'reports', 'report_comments', 'alerts', 'users',
        'reward_offers', 'offer_redemptions', 'ad_campaigns', 'bookings', 'payouts',
        'audit_logs', 'place_corrections', 'travel_partners', 'user_subscriptions',
        'subscription_plans', 'roles', 'permissions', 'achievements', 'curated_routes',
        'ai_agents', 'ai_agent_tasks', 'translation_glossary',
    ];

    public const DELETE_TTL = 60;

    /** Record hard-deleted ids so the feed can tell clients to drop rows. */
    public static function bump(string $table, int|array $ids): void
    {
        if (!in_array($table, self::TABLES, true)) {
            return;
        }

        $ids = array_values(array_filter(array_map('intval', (array) $ids)));
        if ($ids === []) {
            return;
        }

        $key = 'live_feed_del:' . $table;
        Redis::pipeline(function ($pipe) use ($key, $ids) {
            foreach ($ids as $id) {
                $pipe->rpush($key, $id);
            }
            $pipe->expire($key, self::DELETE_TTL);
        });
    }

    /** Current fingerprint per table: [table => [max_id, max_ts]]. */
    public static function fingerprint(): array
    {
        return Cache::remember('live_feed_fp', 5, function () {
            $fp = [];
            foreach (self::TABLES as $t) {
                if (!Schema::hasTable($t)) {
                    continue;
                }
                $row = DB::table($t)
                    ->selectRaw('COALESCE(MAX(id),0) as max_id, COALESCE(MAX(updated_at),"1970-01-01 00:00:00") as max_ts')
                    ->first();
                $fp[$t] = [
                    'max_id' => (int) $row->max_id,
                    'max_ts' => (string) $row->max_ts,
                ];
            }
            return $fp;
        });
    }

    /**
     * Diff the client's last fingerprint against the server state.
     *
     * @param  array<string, array{max_id:int, max_ts:string}>  $since
     * @param  array<string, array<int>>  $delsSeen  deleted ids the client already applied
     * @return array<string, array{new:array,updated:array,deleted:array}>
     */
    public static function changes(array $since = [], array $delsSeen = []): array
    {
        $out = [];

        foreach (self::TABLES as $t) {
            if (!Schema::hasTable($t)) {
                continue;
            }

            $row = DB::table($t)
                ->selectRaw('COALESCE(MAX(id),0) as max_id, COALESCE(MAX(updated_at),"1970-01-01 00:00:00") as max_ts')
                ->first();
            $maxId = (int) $row->max_id;
            $maxTs = (string) $row->max_ts;

            $prev = $since[$t] ?? ['max_id' => 0, 'max_ts' => '1970-01-01 00:00:00'];
            $prevId = (int) $prev['max_id'];
            $prevTs = (string) ($prev['max_ts'] ?? '1970-01-01 00:00:00');

            $new = [];
            $updated = [];

            if ($maxId > $prevId) {
                $new = DB::table($t)
                    ->where('id', '>', $prevId)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($i) => (int) $i)
                    ->values()
                    ->all();
            }

            if ($maxTs !== $prevTs && $maxTs > $prevTs) {
                $updated = DB::table($t)
                    ->where('updated_at', '>', $prevTs)
                    ->where('id', '<=', $prevId)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($i) => (int) $i)
                    ->values()
                    ->all();
            }

            $seen = array_map('intval', $delsSeen[$t] ?? []);
            $deleted = collect(Redis::lrange('live_feed_del:' . $t, 0, -1))
                ->map(fn ($i) => (int) $i)
                ->reject(fn ($i) => in_array($i, $seen, true))
                ->values()
                ->all();

            if ($new !== [] || $updated !== [] || $deleted !== []) {
                $out[$t] = [
                    'new' => $new,
                    'updated' => $updated,
                    'deleted' => $deleted,
                ];
            }
        }

        return $out;
    }
}