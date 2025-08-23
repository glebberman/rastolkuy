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
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Available models with their versions and capabilities.
    | Updated according to Anthropic's latest offerings.
    |
    */

    'models' => [
        'claude' => [
            // Claude 4 Opus - Most capable model
            'opus-4.1' => [
                'id' => 'claude-4-opus-20251205',
                'name' => 'Claude Opus 4.1',
                'context_window' => 200000,
                'description' => 'Most capable model for complex tasks',
            ],
            // Claude 4 Sonnet - Balanced performance
            'sonnet-4' => [
                'id' => 'claude-4-sonnet-20251205',
                'name' => 'Claude Sonnet 4',
                'context_window' => 200000,
                'description' => 'Balanced performance and cost',
            ],
            // Claude 3.5 Sonnet - Current production model
            'sonnet-3.5' => [
                'id' => 'claude-3-5-sonnet-20241022',
                'name' => 'Claude 3.5 Sonnet',
                'context_window' => 200000,
                'description' => 'Current production model with excellent performance',
            ],
            // Claude 3.5 Haiku - Fast and economical
            'haiku-3.5' => [
                'id' => 'claude-3-5-haiku-20241022',
                'name' => 'Claude 3.5 Haiku',
                'context_window' => 200000,
                'description' => 'Fast and economical model for simple tasks',
            ],
            // Legacy models for compatibility
            'opus-3' => [
                'id' => 'claude-3-opus-20240229',
                'name' => 'Claude 3 Opus',
                'context_window' => 200000,
                'description' => 'Legacy high-capability model',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Model Selection
    |--------------------------------------------------------------------------
    |
    | Default model to use when none is specified.
    |
    */

    'default_model' => env('LLM_DEFAULT_MODEL', 'claude-3-5-sonnet-20241022'),

    /*
    |--------------------------------------------------------------------------
    | Cost Calculation
    |--------------------------------------------------------------------------
    |
    | Pricing information for different models to calculate usage costs.
    | Prices are per 1 million tokens. Updated December 2024.
    |
    */

    'pricing' => [
        // Claude 4.1 Opus - Latest flagship model
        'claude-4-opus-20251205' => [
            'input_per_million' => 15.00,
            'output_per_million' => 75.00,
        ],
        // Claude 4 Sonnet - Variable pricing based on context
        'claude-4-sonnet-20251205' => [
            'input_per_million' => 3.00,  // Up to 200K tokens
            'output_per_million' => 15.00, // Up to 200K tokens
            // Note: Higher pricing for >200K tokens (6/22.50)
        ],
        // Claude 3.5 Sonnet - Current production model
        'claude-3-5-sonnet-20241022' => [
            'input_per_million' => 3.00,
            'output_per_million' => 15.00,
        ],
        // Claude 3.5 Haiku - Fast and economical
        'claude-3-5-haiku-20241022' => [
            'input_per_million' => 0.80,
            'output_per_million' => 4.00,
        ],
        // Legacy Claude 3 Opus
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
