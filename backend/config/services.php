<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'model' => env('AI_MODEL', 'gemini-2.0-flash'),
        'gemini_text_model' => env('AI_GEMINI_TEXT_MODEL', 'gemini-2.0-flash'),
        'vision_provider' => env('AI_VISION_PROVIDER', 'gemini'),
        'vision_model' => env('AI_VISION_MODEL', 'gemini-flash-latest'),
        'vision_groq_model' => env('AI_VISION_GROQ_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct'),
        'text_chain' => env('AI_TEXT_CHAIN', 'groq,gemini,cloudflare'),
        'vision_chain' => env('AI_VISION_CHAIN', 'gemini,cloudflare'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'api_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('GEMINI_API_KEYS', ''))))),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
    ],

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'vision_model' => env('CLOUDFLARE_VISION_MODEL', '@cf/meta/llama-3.2-11b-vision-instruct'),
        'text_model' => env('CLOUDFLARE_TEXT_MODEL', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'server_key' => env('FIREBASE_SERVER_KEY'),
    ],

];
