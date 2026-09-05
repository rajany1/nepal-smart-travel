<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for cross-origin requests. Only the origins listed here will
    | be allowed to make requests to your application.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://nepalsmarttravel.com',
        'https://www.nepalsmarttravel.com',
        'https://app.nepalsmarttravel.com',
        'http://localhost:3000',   // Local dev
        'http://localhost:8080',   // Local dev
        'http://10.0.2.2:8000',   // Android emulator
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
