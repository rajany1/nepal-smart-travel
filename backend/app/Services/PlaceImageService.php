<?php

namespace App\Services;

use App\Models\Place;
use App\Models\PlaceImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Writes discovered images to MySQL — the single source of truth for image
 * metadata — and keeps `places.image_discovery_*` in step.
 *
 * Redis is deliberately not involved in storing anything durable here; it is
 * only used for the (throttled) cache-invalidation flag.
 */
class PlaceImageService
{
    /** Minimum interval between PlacesCache::bump() during bulk discovery. */
    private const BUMP_THROTTLE_SECONDS = 60;

    private const BUMP_THROTTLE_KEY = 'places:images:bump_lock';

    public function approvedCount(int $placeId): int
    {
        return PlaceImage::query()
            ->where('place_id', $placeId)
            ->where('status', PlaceImage::STATUS_APPROVED)
            ->count();
    }

    /**
     * Persist one accepted candidate. Returns true when a new row was
     * written, false when it was already known (duplicate).
     */
    public function insert(Place $place, array $candidate, float $score): bool
    {
        $displayUrl = (string) ($candidate['url'] ?? '');
        if ($displayUrl === '') {
            return false;
        }

        $sha1 = sha1($displayUrl);

        if (PlaceImage::query()
            ->where('place_id', $place->id)
            ->where('sha1', $sha1)
            ->exists()) {
            return false;
        }

        $sourceId = $candidate['source_id'] ?? null;
        if ($sourceId !== null && $sourceId !== ''
            && PlaceImage::query()
                ->where('place_id', $place->id)
                ->where('source', PlaceImage::SOURCE_WIKIMEDIA)
                ->where('source_id', $sourceId)
                ->exists()) {
            return false;
        }

        try {
            PlaceImage::create([
                'place_id' => $place->id,
                'image_url' => $displayUrl,
                'thumbnail_url' => $candidate['url'] ?? null,
                'original_url' => $candidate['original_url'] ?? null,
                'source' => PlaceImage::SOURCE_WIKIMEDIA,
                'source_id' => $sourceId,
                'source_page_url' => $candidate['source_page_url'] ?? null,
                'title' => $this->truncate($candidate['title'] ?? null, 255),
                'author' => $this->truncate($candidate['author'] ?? null, 255),
                'license' => $this->truncate($candidate['license'] ?? null, 50),
                'license_url' => $this->truncate($candidate['license_url'] ?? null, 255),
                'attribution' => $candidate['attribution'] ?? null,
                'width' => $candidate['width'] ?? null,
                'height' => $candidate['height'] ?? null,
                'sha1' => $sha1,
                'photo_latitude' => $candidate['photo_lat'] ?? null,
                'photo_longitude' => $candidate['photo_lng'] ?? null,
                'match_score' => round($score, 3),
                'matched_via' => $this->truncate($candidate['via'] ?? null, 30),
                'is_primary' => false,
                'sort_order' => 0,
                'status' => PlaceImage::STATUS_APPROVED,
                'discovered_at' => now(),
                'last_checked_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Lost a race against a concurrent job — the row exists, which is
            // exactly the outcome we wanted.
            return false;
        }
    }

    /**
     * Keep exactly one primary image. Local uploads win (user/admin content
     * outranks scraped photos), otherwise the best-scoring discovered image.
     */
    public function ensurePrimary(int $placeId): void
    {
        $primary = PlaceImage::query()
            ->where('place_id', $placeId)
            ->where('status', PlaceImage::STATUS_APPROVED)
            ->orderByRaw("CASE WHEN source = 'upload' THEN 0 ELSE 1 END")
            ->orderByRaw('COALESCE(match_score, 0) DESC')
            ->orderBy('id')
            ->first();

        if ($primary === null) {
            return;
        }

        PlaceImage::query()
            ->where('place_id', $placeId)
            ->where('is_primary', true)
            ->where('id', '!=', $primary->id)
            ->update(['is_primary' => false]);

        if (!$primary->is_primary) {
            $primary->update(['is_primary' => true]);
        }
    }

    /**
     * Record the outcome of one discovery attempt on the place row.
     *
     * @param int   $stored images actually written this run
     * @param null|string $error exception message when the run blew up
     */
    public function finalize(Place $place, int $stored, ?string $error = null): void
    {
        $total = $this->approvedCount($place->id);
        $min = (int) config('images.min_per_place', 3);
        $max = (int) config('images.max_per_place', 10);

        $attributes = [
            'image_attempt_count' => $place->image_attempt_count + 1,
            'last_status_update' => now(),
        ];

        if ($error !== null) {
            $attributes['image_discovery_status'] = Place::IMAGE_STATUS_FAILED;
            $attributes['image_discovered_at'] = $place->image_discovered_at ?? now();
            $attributes['image_next_retry_at'] = now()->addMinutes($this->retryDelayMinutes($place->image_attempt_count));
            Log::warning('Place image discovery failed', [
                'place_id' => $place->id,
                'stored' => $stored,
                'error' => $error,
            ]);
        } elseif ($total >= $max || $total >= $min) {
            $attributes['image_discovery_status'] = Place::IMAGE_STATUS_DONE;
            $attributes['image_discovered_at'] = $place->image_discovered_at ?? now();
            $attributes['image_next_retry_at'] = null;
        } elseif ($total > 0) {
            // 1–2 images: keep them, but look again later — Commons coverage
            // for a place can improve, and we may have been too strict once.
            $attributes['image_discovery_status'] = Place::IMAGE_STATUS_PARTIAL;
            $attributes['image_discovered_at'] = $place->image_discovered_at ?? now();
            $attributes['image_next_retry_at'] = now()->addDays((int) config('images.job.negative_ttl_days', 7));
        } else {
            $attributes['image_discovery_status'] = Place::IMAGE_STATUS_NONE;
            $attributes['image_discovered_at'] = $place->image_discovered_at ?? now();
            $attributes['image_next_retry_at'] = now()->addDays((int) config('images.job.negative_ttl_days', 7));
        }

        Place::query()->whereKey($place->id)->update($attributes);
    }

    public function markRunning(Place $place): void
    {
        Place::query()->whereKey($place->id)->update([
            'image_discovery_status' => Place::IMAGE_STATUS_RUNNING,
        ]);
    }

    /** Exponential-ish backoff between whole-place attempts, in minutes. */
    public function retryDelayMinutes(int $attemptCount): int
    {
        $backoff = (array) config('images.job.backoff', [60, 300, 1800]);
        $index = min(max($attemptCount, 0), count($backoff) - 1);

        return max(1, (int) $backoff[$index]);
    }

    /**
     * Invalidate the versioned places caches so list endpoints pick up new
     * cover images — at most once per minute so a 12k-place backfill does not
     * blow the cache away on every single insert.
     */
    public function bumpPlacesCacheThrottled(): void
    {
        try {
            $acquired = Redis::set(self::BUMP_THROTTLE_KEY, '1', ['NX', 'EX' => self::BUMP_THROTTLE_SECONDS]);
            if ($acquired) {
                PlacesCache::bump();
            }
        } catch (\Throwable $e) {
            // Redis hiccup must never fail image persistence; the next insert
            // (or the import itself) will bump again.
            Log::debug('PlacesCache throttled bump skipped: ' . $e->getMessage());
        }
    }

    /**
     * Bulk helper: write a whole scored batch, then settle bookkeeping.
     *
     * @return array{stored: int, duplicates: int}
     */
    public function storeBatch(Place $place, array $accepted): array
    {
        $stored = 0;
        $duplicates = 0;

        foreach ($accepted as $row) {
            $written = $this->insert($place, $row['candidate'], (float) $row['score']);
            $written ? $stored++ : $duplicates++;
            if ($stored >= (int) config('images.max_per_place', 10)) {
                break;
            }
        }

        if ($stored > 0) {
            $this->ensurePrimary($place->id);
            $this->bumpPlacesCacheThrottled();
        }

        return compact('stored', 'duplicates');
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $length);
    }

    /** Raw diagnostic helper used by the stats command. */
    public function countsByStatus(): array
    {
        return PlaceImage::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    /** @return array<string, int> */
    public function countsBySource(): array
    {
        return PlaceImage::query()
            ->selectRaw('source, COUNT(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source')
            ->all();
    }

    /** Places broken down by image-discovery state. */
    public function placeStatusCounts(): array
    {
        return DB::table('places')
            ->selectRaw('image_discovery_status, COUNT(*) as total')
            ->groupBy('image_discovery_status')
            ->pluck('total', 'image_discovery_status')
            ->all();
    }
}
