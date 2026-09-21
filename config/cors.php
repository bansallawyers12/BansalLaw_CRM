<?php

$configuredOrigins = env('CORS_ALLOWED_ORIGINS');

if (! empty($configuredOrigins)) {
    $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', (string) $configuredOrigins))));
} else {
    $allowedOrigins = array_values(array_filter([
        rtrim((string) env('APP_URL', ''), '/'),
        rtrim((string) env('APP_PUBLIC_WEBSITE_URL', 'https://www.bansallawyers.com.au'), '/'),
        'https://bansallawyers.com.au',
    ]));
}

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => env('APP_ENV') === 'production'
        ? []
        : ['#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
