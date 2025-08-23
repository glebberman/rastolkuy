<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default LLM Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default LLM provider that will be used by
    | the LLMService. You may specify which of the providers below you wish
    | to use as your default provider for all LLM operations.
    |
    */

    'default' => env('LLM_DEFAULT_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | LLM Providers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the LLM providers for your application. Each
    | provider should have the necessary configuration options to connect
    | to their respective APIs.
    |
    */

    'providers' => [
        'claude' => [
            'adapter' => 'claude',
            'api_key' => env('CLAUDE_API_KEY'),
            'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
            'default_model' => env('CLAUDE_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),
            'max_tokens' => env('CLAUDE_MAX_TOKENS', 4096),
            'temperature' => env('CLAUDE_TEMPERATURE', 0.1),
            'timeout' => env('CLAUDE_TIMEOUT', 60),
            'max_retries' => env('CLAUDE_MAX_RETRIES', 3),
            'retry_delay_seconds' => env('CLAUDE_RETRY_DELAY', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration to prevent exceeding API limits.
    | These settings control requests per minute/hour per provider.
    |
    */

    'rate_limiting' => [
        'claude' => [
            'requests_per_minute' => env('CLAUDE_REQUESTS_PER_MINUTE', 60),
            'requests_per_hour' => env('CLAUDE_REQUESTS_PER_HOUR', 1000),
            'tokens_per_minute' => env('CLAUDE_TOKENS_PER_MINUTE', 40000),
            'tokens_per_hour' => env('CLAUDE_TOKENS_PER_HOUR', 400000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Calculation
    |--------------------------------------------------------------------------
    |
    | Pricing information for different models to calculate usage costs.
    | Prices are per 1 million tokens.
    |
    */

    'pricing' => [
        'claude-3-5-sonnet-20241022' => [
            'input_per_million' => 3.00,
            'output_per_million' => 15.00,
        ],
        'claude-3-5-haiku-20241022' => [
            'input_per_million' => 0.25,
            'output_per_million' => 1.25,
        ],
        'claude-3-opus-20240229' => [
            'input_per_million' => 15.00,
            'output_per_million' => 75.00,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for queue-based batch processing of LLM requests.
    |
    */

    'queue' => [
        'connection' => env('LLM_QUEUE_CONNECTION', 'redis'),
        'queue_name' => env('LLM_QUEUE_NAME', 'llm-processing'),
        'batch_size' => env('LLM_BATCH_SIZE', 10),
        'max_concurrent_jobs' => env('LLM_MAX_CONCURRENT_JOBS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for LLM service operations.
    |
    */

    'logging' => [
        'enabled' => env('LLM_LOGGING_ENABLED', true),
        'level' => env('LLM_LOGGING_LEVEL', 'info'),
        'log_requests' => env('LLM_LOG_REQUESTS', true),
        'log_responses' => env('LLM_LOG_RESPONSES', true),
        'log_errors' => env('LLM_LOG_ERRORS', true),
    ],
];
