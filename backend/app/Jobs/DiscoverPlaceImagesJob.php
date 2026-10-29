<?php

namespace App\Jobs;

use App\Models\Place;
use App\Services\Wikimedia\PlaceImageDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronous Wikimedia discovery for a single place.
 *
 * Unique per place so repeated detail views / backfill runs cannot queue the
 * same work twice. Never dispatched from the Nearby endpoint.
 */
class DiscoverPlaceImagesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 80;

    public int $uniqueFor = 3600;

    /** @var int[] */
    public array $backoff = [60, 300, 1800];

    public function __construct(public int $placeId) {}

    public function uniqueId(): string
    {
        return 'place-images:'.$this->placeId;
    }

    public function handle(PlaceImageDiscoveryService $service): void
    {
        $place = Place::find($this->placeId);
        if ($place === null) {
            return;
        }

        $service->discover($place);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Place image discovery job failed', [
            'place_id' => $this->placeId,
            'error' => $e->getMessage(),
        ]);
    }
}
