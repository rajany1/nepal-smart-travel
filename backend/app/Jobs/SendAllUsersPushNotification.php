<?php

namespace App\Jobs;

use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fan out a platform-wide push to ALL subscribed users (admin broadcast alerts).
 * Runs in the background so it never blocks the alert-create response.
 */
class SendAllUsersPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $title,
        public string $message,
        public array $data = [],
        public string $settingsKey = 'push_notifications',
    ) {
    }

    public function handle(): void
    {
        try {
            PushNotificationService::notifyAllUsers(
                title: $this->title,
                message: $this->message,
                data: $this->data,
                settingsKey: $this->settingsKey,
            );
        } catch (\Throwable $e) {
            Log::warning('Queued broadcast push failed: ' . $e->getMessage());
        }
    }
}
