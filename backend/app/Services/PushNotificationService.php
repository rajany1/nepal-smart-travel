<?php

namespace App\Services;

use App\Models\PushToken;
use App\Models\TranslationGlossary;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send a push notification to a specific user.
     * Sends both English and Nepali versions based on user's language preference.
     */
    public static function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        string $settingsKey = 'push_notifications'
    ): void {
        $user = User::find($userId);
        if (!$user || self::isOptedOut($user, $settingsKey)) return;

        $fcmTokens = PushToken::where('user_id', $userId)
            ->where('subscribed', true)
            ->pluck('fcm_token')
            ->toArray();

        if (empty($fcmTokens)) return;

        $language = $user->settings['language'] ?? 'en';
        self::sendFcm($fcmTokens, $title, $body, $data, $language);
    }

    /**
     * Send push notification to users near a location.
     * Sends bilingual payload based on each user's language preference.
     */
    public static function notifyNearbyUsers(
        string $title,
        string $message,
        float $latitude,
        float $longitude,
        float $radiusKm = 20,
        array $data = [],
        string $settingsKey = 'push_notifications'
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

        // Respect per-user notification settings (opt-out only — users without the
        // settings key or with it unset keep receiving pushes).
        $candidateIds = $query->clone()->distinct()->pluck('user_id');
        $excludedIds = User::whereIn('id', $candidateIds)
            ->get()
            ->filter(fn (User $u) => self::isOptedOut($u, $settingsKey))
            ->pluck('id');
        if ($excludedIds->isNotEmpty()) {
            $query->whereNotIn('user_id', $excludedIds);
        }

        // Group tokens by user language preference
        $tokensByLanguage = [];
        $tokens = $query->with('user:id,settings')->get();
        foreach ($tokens as $token) {
            $lang = $token->user->settings['language'] ?? 'en';
            $tokensByLanguage[$lang][] = $token->fcm_token;
        }

        foreach ($tokensByLanguage as $language => $langTokens) {
            self::sendFcm($langTokens, $title, $message, $data, $language);
        }
    }

    protected static function isOptedOut(User $user, string $settingsKey): bool
    {
        $settings = $user->settings ?? [];
        if (($settings[$settingsKey] ?? true) === false) return true;
        // Master kill-switch for every push category
        if (($settings['push_notifications'] ?? true) === false) return true;
        return false;
    }

    /**
     * Send FCM payload with bilingual support.
     * If language is 'ne', includes Nepali translations in data payload.
     */
    protected static function sendFcm(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        string $language = 'en'
    ): void {
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
            'data' => array_merge($data, [
                'title_en' => $title,
                'body_en' => $body,
                'lang' => $language,
            ]),
            'priority' => 'high',
        ];

        // Add Nepali translations to data payload for 'ne' language
        if ($language === 'ne') {
            $payload['data']['title_ne'] = self::translate($title);
            $payload['data']['body_ne'] = self::translate($body);
            // Also translate data values if they exist
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $payload['data'][$key . '_ne'] = self::translate($value);
                }
            }
        }

        // Chunk tokens to avoid FCM limits (1000 per request)
        $chunks = array_chunk($tokens, 1000);
        foreach ($chunks as $chunk) {
            $payload['registration_ids'] = $chunk;
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'key=' . config('services.firebase.server_key'),
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

    /**
     * Translate a string using the glossary. Returns original if no translation found.
     */
    protected static function translate(string $text): string
    {
        // Try exact match first
        $translation = TranslationGlossary::where('is_active', true)
            ->where('term', $text)
            ->value('nepali');

        if ($translation) {
            return $translation;
        }

        // For multi-word strings, try translating each word
        $words = preg_split('/\s+/', $text);
        if (count($words) > 1) {
            $translatedWords = array_map(function ($word) {
                // Remove punctuation for lookup
                $cleanWord = preg_replace('/[^\p{L}\p{N}]+/u', '', $word);
                $translation = TranslationGlossary::where('is_active', true)
                    ->where('term', $cleanWord)
                    ->value('nepali');
                return $translation ?: $word;
            }, $words);
            return implode(' ', $translatedWords);
        }

        return $text;
    }
}