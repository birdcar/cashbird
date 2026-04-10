<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Teller Application ID
    |--------------------------------------------------------------------------
    |
    | Your Teller application ID, used by Teller Connect in the browser.
    |
    */

    'app_id' => env('TELLER_APP_ID'),

    /*
    |--------------------------------------------------------------------------
    | Teller API Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL for Teller REST API. Use sandbox for development.
    |
    */

    'base_url' => env('TELLER_BASE_URL', 'https://api.teller.io'),

    /*
    |--------------------------------------------------------------------------
    | mTLS Certificate Paths
    |--------------------------------------------------------------------------
    |
    | Teller requires mutual TLS for API authentication. Provide absolute
    | paths to the certificate and private key files on the server.
    |
    */

    'cert_path' => env('TELLER_CERT_PATH'),
    'key_path' => env('TELLER_KEY_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Signing Secret
    |--------------------------------------------------------------------------
    |
    | Used to verify incoming webhook signatures from Teller.
    |
    */

    'signing_secret' => env('TELLER_SIGNING_SECRET'),
];
