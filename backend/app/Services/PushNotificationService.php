<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    protected static string $appId = '';
    protected static string $restApiKey = '';

    public static function init(): void
    {
        self::$appId = config('onesignal.app_id', env('ONESIGNAL_APP_ID', ''));
        self::$restApiKey = config('onesignal.rest_api_key', env('ONESIGNAL_REST_API_KEY', ''));
    }

    public static function sendToUser(int $userId, string $title, string $message, array $data = []): void
    {
        self::init();
        if (empty(self::$appId) || empty(self::$restApiKey)) return;

        $playerIds = PushToken::where('user_id', $userId)
            ->where('subscribed', true)
            ->pluck('player_id')
            ->toArray();

        if (empty($playerIds)) return;

        self::sendNotification($playerIds, $title, $message, $data);
    }

    public static function notifyNearbyUsers(
        string $title,
        string $message,
        float $latitude,
        float $longitude,
        float $radiusKm = 20,
        array $data = []
    ): void {
        self::init();
        if (empty(self::$appId) || empty(self::$restApiKey)) return;

        // Include location in the data payload so the app can filter client-side
        $data['latitude'] = $latitude;
        $data['longitude'] = $longitude;
        $data['radius_km'] = $radiusKm;

        $playerIds = PushToken::where('subscribed', true)
            ->pluck('player_id')
            ->toArray();

        if (empty($playerIds)) return;

        $chunks = array_chunk($playerIds, 2000);
        foreach ($chunks as $chunk) {
            self::sendNotification($chunk, $title, $message, $data);
        }
    }

    protected static function sendNotification(array $playerIds, string $title, string $message, array $data = []): void
    {
        $payload = [
            'app_id' => self::$appId,
            'include_player_ids' => $playerIds,
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
            'small_icon' => 'ic_stat_onesignal_default',
            'data' => $data,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . self::$restApiKey,
                'Content-Type' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', $payload);

            if (!$response->successful()) {
                Log::warning('OneSignal push failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('OneSignal push error: ' . $e->getMessage());
        }
    }
}
