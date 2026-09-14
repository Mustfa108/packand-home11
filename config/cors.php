<?php

/**
 * CORS configuration for the SPA.
 *
 * Production checklist:
 * - Set FRONTEND_URL to the exact SPA origin (scheme + host + optional port).
 * - Optionally set FRONTEND_URL_ALT for a second origin (staging / www).
 * - Paths include api/* and broadcasting/* for Echo auth.
 * - After changing .env run: php artisan config:clear
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        env('FRONTEND_URL_ALT'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
