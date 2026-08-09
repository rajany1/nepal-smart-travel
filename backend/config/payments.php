<?php

return [
    'min_campaign_budget' => (float) env('MIN_CAMPAIGN_BUDGET', 100),

    'esewa' => [
        'sandbox' => env('ESEWA_SANDBOX', true),
        'merchant_code' => env('ESEWA_MERCHANT_CODE', 'EPAYTEST'),
        'secret_key' => env('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'),
        'base_url' => env('ESEWA_BASE_URL', 'https://rc-epay.esewa.com.np'),
        'form_path' => '/api/epay/main/v2/form',
        'status_path' => '/api/epay/transaction/status/',
    ],

    'khalti' => [
        'sandbox' => env('KHALTI_SANDBOX', true),
        'secret_key' => env('KHALTI_SECRET_KEY', ''),
        'public_key' => env('KHALTI_PUBLIC_KEY', ''),
        'initiate_url' => env('KHALTI_INITIATE_URL', 'https://dev.khalti.com/api/v2/epayment/initiate/'),
        'verify_url' => env('KHALTI_VERIFY_URL', 'https://dev.khalti.com/api/v2/epayment/verify/'),
    ],
];
