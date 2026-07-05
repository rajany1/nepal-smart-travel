<?php

namespace App\Console\Commands;

use App\Models\PushToken;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class TestPushNotification extends Command
{
    protected $signature = 'push:test {title?} {message?}';
    protected $description = 'Send a test push notification to all registered devices';

    public function handle()
    {
        $title = $this->argument('title') ?? 'Test Notification';
        $message = $this->argument('message') ?? 'This is a test push from Laravel!';

        $count = PushToken::where('subscribed', true)->count();

        if ($count === 0) {
            $this->warn("No registered push tokens found.");
            $this->info("Make sure the Flutter app is running on a device and has initialized OneSignal.");
            return 1;
        }

        $this->info("Sending push to {$count} device(s)...");

        PushNotificationService::notifyNearbyUsers(
            title: $title,
            message: $message,
            latitude: 27.7172,
            longitude: 85.3240,
            radiusKm: 200,
            data: ['type' => 'test', 'id' => 0],
        );

        $this->info("Done! Check your device.");
        return 0;
    }
}
