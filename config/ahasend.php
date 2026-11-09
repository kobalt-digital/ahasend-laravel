<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AhaSend Credentials
    |--------------------------------------------------------------------------
    |
    | The API key and account ID give access to the AhaSend API. You create
    | an API key in the AhaSend dashboard, and you find the account ID in
    | the account settings. Both are required to send mail.
    |
    */

    'api_key' => env('AHASEND_API_KEY'),

    'account_id' => env('AHASEND_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | The timeout is in seconds. Requests that fail because of a connection
    | error, a rate limit or a server error are retried this many times.
    | Retrying is safe: every send carries an idempotency key.
    |
    */

    'base_url' => env('AHASEND_BASE_URL', 'https://api.ahasend.com'),

    'timeout' => (int) env('AHASEND_TIMEOUT', 30),

    'retries' => (int) env('AHASEND_RETRIES', 2),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | The secret verifies that an incoming webhook really comes from AhaSend.
    | Every webhook and route has its own secret: separate several secrets
    | with a comma. The tolerance is the allowed clock drift in seconds.
    |
    */

    'webhook' => [
        'secret' => env('AHASEND_WEBHOOK_SECRET'),
        'tolerance' => (int) env('AHASEND_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | This package registers a route that receives webhooks from AhaSend, by
    | default at "/ahasend/webhook". You may change its domain, path and
    | middleware here, or turn the route off and register your own.
    |
    */

    'routes' => (bool) env('AHASEND_ROUTES', true),

    'domain' => env('AHASEND_DOMAIN'),

    'path' => env('AHASEND_PATH', 'ahasend'),

    'middleware' => [],

];
