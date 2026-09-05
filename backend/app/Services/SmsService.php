<?php

namespace App\Services;

use App\Models\GameSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS message to a phone number.
     * Uses admin-configured SMS provider from GameSetting.
     */
    public static function send(string $phone, string $message): bool
    {
        try {
            $enabled = (int) GameSetting::getValue('sms_enabled', 0);
            if (!$enabled) {
                Log::info('SMS disabled in settings, skipping send to: ' . $phone);
                return false;
            }

            $provider = GameSetting::getValue('sms_provider', 'http_api');
            $apiUrl = GameSetting::getValue('sms_api_url', '');
            $apiKey = GameSetting::getValue('sms_api_key', '');
            $from = GameSetting::getValue('sms_from', '');

            if (empty($apiUrl)) {
                Log::warning('SMS API URL not configured');
                return false;
            }

            // Normalize phone number (Nepal format)
            $phone = self::normalizePhone($phone);

            if ($provider === 'twilio') {
                return self::sendViaTwilio($phone, $message, $apiUrl, $apiKey, $from);
            }

            // Default: HTTP API Gateway
            return self::sendViaHttpApi($phone, $message, $apiUrl, $apiKey, $from);
        } catch (\Throwable $e) {
            Log::error('SMS send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via generic HTTP API gateway.
     * Expected POST with JSON body: { to, from, message }
     * or form data: to, from, text/message
     */
    private static function sendViaHttpApi(string $phone, string $message, string $apiUrl, string $apiKey, string $from): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(10)->post($apiUrl, [
            'to' => $phone,
            'from' => $from,
            'message' => $message,
            'text' => $message,
        ]);

        if ($response->successful()) {
            Log::info("SMS sent via HTTP API to {$phone}");
            return true;
        }

        Log::error("SMS HTTP API failed for {$phone}: " . $response->body());
        return false;
    }

    /**
     * Send via Twilio REST API.
     * API URL is the Twilio endpoint: https://api.twilio.com/2010-04-01/Accounts/{AccountSid}/Messages.json
     * API key format: "AccountSid:AuthToken" (base64 encoded or plain)
     */
    private static function sendViaTwilio(string $phone, string $message, string $apiUrl, string $apiKey, string $from): bool
    {
        // Parse AccountSid:AuthToken from api_key
        $parts = explode(':', $apiKey);
        $accountSid = $parts[0] ?? '';
        $authToken = $parts[1] ?? '';

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->timeout(10)
            ->asForm()
            ->post($apiUrl, [
                'To' => $phone,
                'From' => $from,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            Log::info("SMS sent via Twilio to {$phone}");
            return true;
        }

        Log::error("Twilio SMS failed for {$phone}: " . $response->body());
        return false;
    }

    /**
     * Normalize Nepal phone numbers to E.164 format.
     */
    private static function normalizePhone(string $phone): string
    {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[\s\-\(\)]/', '', trim($phone));

        // Already has country code
        if (str_starts_with($phone, '+977')) {
            return $phone;
        }

        // Starts with 977 without +
        if (str_starts_with($phone, '977')) {
            return '+' . $phone;
        }

        // Local format: 98XXXXXXXX or 97XXXXXXXX (10 digits)
        if (strlen($phone) === 10 && preg_match('/^9[78]/', $phone)) {
            return '+977' . $phone;
        }

        // Default: prepend +977
        return '+977' . $phone;
    }
}
