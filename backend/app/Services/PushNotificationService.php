<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public static function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $fcmTokens = PushToken::where('user_id', $userId)
            ->where('subscribed', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($fcmTokens)) return;

        self::sendFcm($fcmTokens, $title, $body, $data);
    }

    public static function notifyNearbyUsers(
        string $title,
        string $message,
        float $latitude,
        float $longitude,
        float $radiusKm = 20,
        array $data = []
    ): void {
        $data['latitude'] = $latitude;
        $data['longitude'] = $longitude;
        $data['radius_km'] = $radiusKm;

        $query = PushToken::where('subscribed', true);

        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($latitude)));
        $query->where(function ($q) use ($latitude, $longitude, $latDelta, $lngDelta) {
            $q->where(function ($geo) use ($latitude, $longitude, $latDelta, $lngDelta) {
                $geo->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
                    ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);
            })->orWhereNull('latitude')
              ->orWhereNull('longitude');
        });

        $fcmTokens = $query->pluck('fcm_token')->toArray();

        if (empty($fcmTokens)) return;

        $chunks = array_chunk($fcmTokens, 500);
        foreach ($chunks as $chunk) {
            self::sendFcm($chunk, $title, $message, $data);
        }
    }

    protected static function sendFcm(array $tokens, string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.firebase.server_key');
        if (empty($serverKey)) {
            Log::warning('FCM server key not configured.');
            return;
        }

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => $data,
            'priority' => 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://fcm.googleapis.com/fcm/send', $payload);

            if (!$response->successful()) {
                Log::warning('FCM push failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('FCM push error: ' . $e->getMessage());
        }
    }
}
