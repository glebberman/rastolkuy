<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Currency Configuration
    |--------------------------------------------------------------------------
    |
    | The default currency for the credits system. Credits will be priced
    | in this currency and all conversions will use it as the base.
    |
    */

    'default_currency' => env('CREDITS_DEFAULT_CURRENCY', 'RUB'),

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    |
    | List of currencies supported by the credits system. Each currency
    | should have corresponding exchange rates and credit costs configured.
    |
    */

    'supported_currencies' => ['RUB', 'USD', 'EUR'],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Exchange rates for converting between currencies. The base currency is
    | RUB (Russian Ruble). These rates should be updated regularly.
    |
    */

    'exchange_rates' => [
        'RUB' => 1.0,      // Base currency
        'USD' => 85.0,     // 1 USD = 95 RUB
        'EUR' => 100.0,    // 1 EUR = 105 RUB
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Costs
    |--------------------------------------------------------------------------
    |
    | The cost of 1 credit in different currencies. This determines how much
    | users need to pay to purchase credits for document processing.
    |
    */

    'credit_cost' => [
        'RUB' => 50.0,
        'USD' => 0.6,
        'EUR' => 0.5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Credit Balance
    |--------------------------------------------------------------------------
    |
    | The number of credits new users receive upon registration.
    | Set to 0 to disable free credits for new users.
    |
    */

    'initial_balance' => env('CREDITS_INITIAL_BALANCE', 100),

    /*
    |--------------------------------------------------------------------------
    | Minimum Credit Balance
    |--------------------------------------------------------------------------
    |
    | The minimum credit balance a user can have. This prevents negative
    | balances and ensures users always have some credits available.
    |
    */

    'minimum_balance' => env('CREDITS_MINIMUM_BALANCE', 0),

    /*
    |--------------------------------------------------------------------------
    | Maximum Credit Balance
    |--------------------------------------------------------------------------
    |
    | The maximum credit balance a user can have. This prevents excessive
    | accumulation of credits and potential abuse of the system.
    |
    */

    'maximum_balance' => env('CREDITS_MAXIMUM_BALANCE', 100000),

    /*
    |--------------------------------------------------------------------------
    | Transaction Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for logging credit transactions. Enable detailed logging
    | for better audit trails and debugging capabilities.
    |
    */

    'transaction_logging' => [
        'enabled' => env('CREDITS_LOGGING_ENABLED', true),
        'log_topups' => env('CREDITS_LOG_TOPUPS', true),
        'log_debits' => env('CREDITS_LOG_DEBITS', true),
        'log_refunds' => env('CREDITS_LOG_REFUNDS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Policies
    |--------------------------------------------------------------------------
    |
    | Business rules and policies for credit operations. These settings
    | control how credits can be used and managed within the system.
    |
    */

    'policies' => [
        'allow_negative_balance' => env('CREDITS_ALLOW_NEGATIVE', false),
        'refund_processing_enabled' => env('CREDITS_REFUND_ENABLED', true),
        'auto_refund_failed_processing' => env('CREDITS_AUTO_REFUND_FAILED', true),
        'credit_expiration_days' => env('CREDITS_EXPIRATION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Low Balance Threshold
    |--------------------------------------------------------------------------
    |
    | When user's balance falls below this threshold, notifications will be
    | sent to alert them to top up their credits.
    |
    */

    'low_balance_threshold' => env('CREDITS_LOW_BALANCE_THRESHOLD', 10),

    /*
    |--------------------------------------------------------------------------
    | Conversion Rate to USD
    |--------------------------------------------------------------------------
    |
    | The rate for converting USD amounts (from LLM pricing) to credits.
    | This is calculated as: credits = usd_amount * usd_to_credits_rate
    |
    */

    'usd_to_credits_rate' => env('CREDITS_USD_RATE', 100), // 1 USD = 100 credits
];
