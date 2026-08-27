<?php

namespace App\Services;

use App\Models\PushToken;
use App\Models\TranslationGlossary;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Exception\Messaging\NotFound as TokenNotFound;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class PushNotificationService
{
    protected static ?Messaging $messaging = null;

    /**
     * Lazy-shared FCM v1 messaging client (service-account based).
     * The legacy server-key API (fcm.googleapis.com/fcm/send) was shut
     * down by Google, so all sends must go through HTTP v1.
     */
    protected static function messaging(): ?Messaging
    {
        if (self::$messaging !== null) {
            return self::$messaging;
        }

        $credentials = (string) config('services.firebase.credentials');
        if ($credentials === '') {
            Log::warning('FCM credentials not configured (FIREBASE_CREDENTIALS).');
            return null;
        }

        $path = file_exists($credentials) ? $credentials : base_path($credentials);
        if (!file_exists($path)) {
            Log::warning('Firebase credentials file missing.', ['path' => $credentials]);
            return null;
        }

        try {
            self::$messaging = (new Factory)->withServiceAccount($path)->createMessaging();
            return self::$messaging;
        } catch (\Throwable $e) {
            Log::error('Firebase init failed: ' . $e->getMessage());
            return null;
        }
    }
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
     * Send FCM payload via HTTP v1 with bilingual support.
     * If language is 'ne', includes Nepali translations in data payload.
     */
    protected static function sendFcm(
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        string $language = 'en'
    ): void {
        $messaging = self::messaging();
        if ($messaging === null) {
            return;
        }

        $payloadData = array_merge($data, [
            'title_en' => $title,
            'body_en' => $body,
            'lang' => $language,
        ]);

        // Add Nepali translations to data payload for 'ne' language
        if ($language === 'ne') {
            $payloadData['title_ne'] = self::translate($title);
            $payloadData['body_ne'] = self::translate($body);
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    $payloadData[$key . '_ne'] = self::translate($value);
                }
            }
        }

        $sent = 0;
        $pruned = 0;

        foreach ($tokens as $token) {
            try {
                $message = CloudMessage::new()
                    ->withHighestPossiblePriority()
                    ->withNotification(['title' => $title, 'body' => $body])
                    ->withData($payloadData)
                    ->withChangedTarget('token', (string) $token);

                $messaging->send($message);
                $sent++;
            } catch (TokenNotFound $e) {
                // Stale token (app uninstalled/token rotated): stop targeting it.
                PushToken::where('fcm_token', $token)->update(['subscribed' => false]);
                $pruned++;
            } catch (MessagingException $e) {
                Log::warning('FCM v1 send failed for token: ' . substr($e->getMessage(), 0, 200));
            } catch (\Throwable $e) {
                Log::error('FCM send error: ' . $e->getMessage());
            }
        }

        if ($sent > 0 || $pruned > 0) {
            Log::info("FCM push result: sent={$sent}, pruned={$pruned}, lang={$language}");
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