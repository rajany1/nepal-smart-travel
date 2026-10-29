<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverPlaceImagesJob;
use App\Models\Place;
use App\Models\PlaceImage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Throttled, resumable Wikimedia image backfill.
 *
 * Only queues work; all network calls happen in DiscoverPlaceImagesJob on the
 * existing queue worker. Tourism / historic / nature categories are picked
 * first, and a successful dry run always precedes a live batch.
 */
class BackfillPlaceImages extends Command
{
    protected $signature = 'places:images-backfill
        {--place= : Only this place id (for a focused test batch)}
        {--limit=50 : Maximum places to queue per run}
        {--dry-run : List what would be queued without dispatching}
        {--force : Process --place even if it is inside its retry window}
        {--sleep=0 : Milliseconds to wait between dispatches}';

    protected $description = 'Queue Wikimedia Commons image discovery for places without enough photos';

    private const PRIORITY_CATEGORIES = [
        'attractions', 'nature', 'activities', 'recreation', 'entertainment',
        'attraction', 'viewpoint', 'historic site', 'camping', 'picnic', 'museum',
    ];

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $sleep = max(0, (int) $this->option('sleep'));
        $placeId = $this->option('place');

        $query = $this->baseQuery();

        if ($placeId !== null) {
            $query->whereKey((int) $placeId);
        } else {
            $query->whereIn('places.image_discovery_status', [
                Place::IMAGE_STATUS_PENDING,
                Place::IMAGE_STATUS_NONE,
                Place::IMAGE_STATUS_PARTIAL,
                Place::IMAGE_STATUS_FAILED,
            ])->where(function (Builder $q) {
                $q->whereNull('places.image_next_retry_at')
                    ->orWhere('places.image_next_retry_at', '<=', now());
            });
        }

        $places = $query->orderBy('places.id')->limit($limit)->get();

        $queued = 0;
        $skipped = 0;

        foreach ($places as $place) {
            if (! $force && ! $this->isDue($place)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '#%d %s [%s] images=%d status=%s',
                    $place->id,
                    $place->name,
                    $place->category?->name ?? 'uncategorized',
                    (int) $place->approved_images_count,
                    $place->image_discovery_status
                ));

                continue;
            }

            DiscoverPlaceImagesJob::dispatch($place->id);

            // Debounce duplicate backfill runs while the job sits in the queue.
            Place::query()->whereKey($place->id)->update([
                'image_next_retry_at' => now()->addMinutes(15),
            ]);

            $queued++;

            if ($sleep > 0) {
                usleep($sleep * 1000);
            }
        }

        if ($dryRun) {
            $this->info("Dry run: {$places->count()} place(s) would be inspected (nothing dispatched).");

            return self::SUCCESS;
        }

        $this->info("Queued {$queued} image discovery job(s); skipped {$skipped}.");
        $this->line('Run: php artisan queue:work database --queue=default --tries=4 --timeout=90');

        return self::SUCCESS;
    }

    private function baseQuery(): Builder
    {
        $min = (int) config('images.min_per_place', 3);

        return Place::query()
            ->with('category')
            ->withCount(['galleryImages as approved_images_count'])
            ->leftJoin('place_categories', 'place_categories.id', '=', 'places.category_id')
            ->select('places.*')
            ->whereIn('places.source', (array) config('images.eligible_sources', ['seed', 'osm', 'admin', 'user_submitted']))
            ->where('places.is_active', true)
            ->where(function (Builder $q) use ($min) {
                $q->whereRaw(
                    '(SELECT COUNT(*) FROM place_images pi WHERE pi.place_id = places.id AND pi.status = ?) < ?',
                    [PlaceImage::STATUS_APPROVED, $min]
                );
            })
            ->orderByRaw(
                'CASE WHEN LOWER(place_categories.name) IN ('.
                implode(',', array_fill(0, count(self::PRIORITY_CATEGORIES), '?')).
                ') THEN 0 ELSE 1 END',
                self::PRIORITY_CATEGORIES
            );
    }

    private function isDue(Place $place): bool
    {
        $min = (int) config('images.min_per_place', 3);

        if ((int) $place->approved_images_count >= $min) {
            return false;
        }

        if ($place->image_discovery_status === Place::IMAGE_STATUS_DONE) {
            return false;
        }

        if ($place->image_discovery_status === Place::IMAGE_STATUS_RUNNING
            && $place->last_status_update !== null
            && $place->last_status_update->isAfter(now()->subMinutes(10))) {
            return false;
        }

        return $place->image_next_retry_at === null || $place->image_next_retry_at->isPast();
    }
}
