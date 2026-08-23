<?php

namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Send a batch "nearby users" FCM push in the background so it never blocks
 * the report/alert submission response (each user = an HTTP round-trip, so
 * this used to stall submit requests by seconds).
 */
class SendNearbyPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $title,
        public string $message,
        public float $latitude,
        public float $longitude,
        public float $radiusKm = 20,
        public array $data = [],
        public string $settingsKey = 'push_notifications',
    ) {
    }

    public function handle(): void
    {
        try {
            PushNotificationService::notifyNearbyUsers(
                title: $this->title,
                message: $this->message,
                latitude: $this->latitude,
                longitude: $this->longitude,
                radiusKm: $this->radiusKm,
                data: $this->data,
                settingsKey: $this->settingsKey,
            );
        } catch (\Throwable $e) {
            Log::warning('Queued push notification failed: ' . $e->getMessage());
        }
    }
}