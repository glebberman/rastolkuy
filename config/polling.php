<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Polling Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for status polling functionality including intervals,
    | timeouts, and development mode settings.
    |
    */

    // Enable or disable polling globally
    'enabled' => env('POLLING_ENABLED', true),

    // Polling interval in seconds
    'interval' => env('POLLING_INTERVAL', 10),

    // Maximum number of polling attempts before timeout
    'max_attempts' => env('POLLING_MAX_ATTEMPTS', 30),

    // Timeout for long running operations (in seconds)
    'timeout' => env('POLLING_TIMEOUT', 300),

    // Development mode settings
    'dev_mode' => [
        'enabled' => env('APP_ENV') === 'local',
        'interval' => env('POLLING_DEV_INTERVAL', 5), // 5 seconds for dev as per RAS-17
        'debug_logging' => env('POLLING_DEBUG_LOG', false),
    ],

    // Document processing specific settings
    'document_processing' => [
        'interval' => env('POLLING_DOCUMENT_INTERVAL', 5), // 5 seconds as per RAS-17
        'estimation_interval' => env('POLLING_ESTIMATION_INTERVAL', 3), // 3 seconds for estimation polling
        'max_wait_time' => env('POLLING_DOCUMENT_MAX_WAIT', 600), // 10 minutes
        'statuses_to_poll' => ['pending', 'processing', 'estimated', 'uploaded'],
    ],

    // Dashboard credits refresh interval
    'dashboard' => [
        'credits_refresh_interval' => env('DASHBOARD_CREDITS_REFRESH_INTERVAL', 30), // 30 seconds
    ],

    // Cache settings for polling data
    'cache' => [
        'ttl' => env('POLLING_CACHE_TTL', 30), // seconds
        'key_prefix' => 'polling_',
    ],

    // Rate limiting for polling endpoints
    'rate_limit' => [
        'max_requests' => env('POLLING_RATE_LIMIT', 120),
        'per_minutes' => env('POLLING_RATE_WINDOW', 1),
    ],
];
