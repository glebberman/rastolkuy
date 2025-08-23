<?php

declare(strict_types=1);

namespace App\Services\LLM\Support;

use App\Services\LLM\Exceptions\LLMRateLimitException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rate limiter for LLM API requests to prevent exceeding provider limits.
 */
final readonly class RateLimiter
{
    /**
     * @param string $provider Provider name (e.g., 'claude')
     * @param int $requestsPerMinute Maximum requests per minute
     * @param int $requestsPerHour Maximum requests per hour
     * @param int $tokensPerMinute Maximum tokens per minute
     * @param int $tokensPerHour Maximum tokens per hour
     */
    public function __construct(
        private string $provider,
        private int $requestsPerMinute = 60,
        private int $requestsPerHour = 1000,
        private int $tokensPerMinute = 40000,
        private int $tokensPerHour = 400000,
    ) {
    }

    /**
     * Check if a request can be made and reserve the capacity.
     *
     * @param int $estimatedTokens Estimated tokens for the request
     *
     * @throws LLMRateLimitException If rate limit would be exceeded
     */
    public function checkAndReserve(int $estimatedTokens = 0): void
    {
        $currentTime = now();

        // Check request limits
        $this->checkRequestLimits($currentTime);

        // Check token limits if tokens are provided
        if ($estimatedTokens > 0) {
            $this->checkTokenLimits($currentTime, $estimatedTokens);
        }

        // Reserve capacity
        $this->reserveCapacity($currentTime, $estimatedTokens);

        Log::debug('Rate limit check passed', [
            'provider' => $this->provider,
            'estimated_tokens' => $estimatedTokens,
            'timestamp' => $currentTime->toISOString(),
        ]);
    }

    /**
     * Record actual usage after a request completes.
     *
     * @param int $actualTokens Actual tokens used
     */
    public function recordUsage(int $actualTokens): void
    {
        $currentTime = now();

        // We already reserved estimated capacity, so we need to adjust for actual usage
        // This is a simplified approach - in production you might want more sophisticated tracking

        Log::debug('Recording actual LLM usage', [
            'provider' => $this->provider,
            'actual_tokens' => $actualTokens,
            'timestamp' => $currentTime->toISOString(),
        ]);
    }

    /**
     * Get current usage statistics.
     *
     * @return array<string, mixed>
     */
    public function getUsageStats(): array
    {
        $currentTime = now();

        $minuteKey = $this->getCacheKey('requests', 'minute', $currentTime->format('Y-m-d-H-i'));
        $hourKey = $this->getCacheKey('requests', 'hour', $currentTime->format('Y-m-d-H'));
        $tokenMinuteKey = $this->getCacheKey('tokens', 'minute', $currentTime->format('Y-m-d-H-i'));
        $tokenHourKey = $this->getCacheKey('tokens', 'hour', $currentTime->format('Y-m-d-H'));

        return [
            'provider' => $this->provider,
            'requests' => [
                'per_minute' => [
                    'used' => Cache::get($minuteKey, 0),
                    'limit' => $this->requestsPerMinute,
                    'remaining' => $this->requestsPerMinute - Cache::get($minuteKey, 0),
                ],
                'per_hour' => [
                    'used' => Cache::get($hourKey, 0),
                    'limit' => $this->requestsPerHour,
                    'remaining' => $this->requestsPerHour - Cache::get($hourKey, 0),
                ],
            ],
            'tokens' => [
                'per_minute' => [
                    'used' => Cache::get($tokenMinuteKey, 0),
                    'limit' => $this->tokensPerMinute,
                    'remaining' => $this->tokensPerMinute - Cache::get($tokenMinuteKey, 0),
                ],
                'per_hour' => [
                    'used' => Cache::get($tokenHourKey, 0),
                    'limit' => $this->tokensPerHour,
                    'remaining' => $this->tokensPerHour - Cache::get($tokenHourKey, 0),
                ],
            ],
        ];
    }

    /**
     * Reset rate limiting counters (useful for testing).
     */
    public function reset(): void
    {
        $currentTime = now();

        $keys = [
            $this->getCacheKey('requests', 'minute', $currentTime->format('Y-m-d-H-i')),
            $this->getCacheKey('requests', 'hour', $currentTime->format('Y-m-d-H')),
            $this->getCacheKey('tokens', 'minute', $currentTime->format('Y-m-d-H-i')),
            $this->getCacheKey('tokens', 'hour', $currentTime->format('Y-m-d-H')),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        Log::debug('Rate limiter reset', ['provider' => $this->provider]);
    }

    /**
     * Create a rate limiter for the specified provider.
     *
     * @param string $provider Provider name
     */
    public static function forProvider(string $provider): self
    {
        $config = config("llm.rate_limiting.{$provider}", []);
        
        if (!is_array($config)) {
            $config = [];
        }

        return new self(
            provider: $provider,
            requestsPerMinute: is_int($config['requests_per_minute'] ?? null) ? $config['requests_per_minute'] : 60,
            requestsPerHour: is_int($config['requests_per_hour'] ?? null) ? $config['requests_per_hour'] : 1000,
            tokensPerMinute: is_int($config['tokens_per_minute'] ?? null) ? $config['tokens_per_minute'] : 40000,
            tokensPerHour: is_int($config['tokens_per_hour'] ?? null) ? $config['tokens_per_hour'] : 400000,
        );
    }

    /**
     * Check if request limits would be exceeded.
     *
     * @param \Illuminate\Support\Carbon $currentTime Current timestamp
     *
     * @throws LLMRateLimitException
     */
    private function checkRequestLimits(\Illuminate\Support\Carbon $currentTime): void
    {
        // Check per-minute limit
        $minuteKey = $this->getCacheKey('requests', 'minute', $currentTime->format('Y-m-d-H-i'));
        $currentMinuteRequests = Cache::get($minuteKey, 0);

        if ($currentMinuteRequests >= $this->requestsPerMinute) {
            throw LLMRateLimitException::requestLimitExceeded($this->provider, $this->requestsPerMinute, 60);
        }

        // Check per-hour limit
        $hourKey = $this->getCacheKey('requests', 'hour', $currentTime->format('Y-m-d-H'));
        $currentHourRequests = Cache::get($hourKey, 0);

        if ($currentHourRequests >= $this->requestsPerHour) {
            throw LLMRateLimitException::requestLimitExceeded($this->provider, $this->requestsPerHour, 3600);
        }
    }

    /**
     * Check if token limits would be exceeded.
     *
     * @param \Illuminate\Support\Carbon $currentTime Current timestamp
     * @param int $estimatedTokens Estimated tokens for the request
     *
     * @throws LLMRateLimitException
     */
    private function checkTokenLimits(\Illuminate\Support\Carbon $currentTime, int $estimatedTokens): void
    {
        // Check per-minute token limit
        $tokenMinuteKey = $this->getCacheKey('tokens', 'minute', $currentTime->format('Y-m-d-H-i'));
        $currentMinuteTokens = Cache::get($tokenMinuteKey, 0);

        if ($currentMinuteTokens + $estimatedTokens > $this->tokensPerMinute) {
            throw LLMRateLimitException::tokenLimitExceeded($this->provider, $this->tokensPerMinute, 60);
        }

        // Check per-hour token limit
        $tokenHourKey = $this->getCacheKey('tokens', 'hour', $currentTime->format('Y-m-d-H'));
        $currentHourTokens = Cache::get($tokenHourKey, 0);

        if ($currentHourTokens + $estimatedTokens > $this->tokensPerHour) {
            throw LLMRateLimitException::tokenLimitExceeded($this->provider, $this->tokensPerHour, 3600);
        }
    }

    /**
     * Reserve capacity for the request.
     *
     * @param \Illuminate\Support\Carbon $currentTime Current timestamp
     * @param int $estimatedTokens Estimated tokens
     */
    private function reserveCapacity(\Illuminate\Support\Carbon $currentTime, int $estimatedTokens): void
    {
        // Increment request counters
        $minuteKey = $this->getCacheKey('requests', 'minute', $currentTime->format('Y-m-d-H-i'));
        $hourKey = $this->getCacheKey('requests', 'hour', $currentTime->format('Y-m-d-H'));

        Cache::increment($minuteKey, 1);
        Cache::increment($hourKey, 1);

        // Set expiry for minute counter (65 seconds to account for timing)
        Cache::put($minuteKey, Cache::get($minuteKey), 65);
        // Set expiry for hour counter (3665 seconds to account for timing)
        Cache::put($hourKey, Cache::get($hourKey), 3665);

        // Increment token counters if tokens provided
        if ($estimatedTokens > 0) {
            $tokenMinuteKey = $this->getCacheKey('tokens', 'minute', $currentTime->format('Y-m-d-H-i'));
            $tokenHourKey = $this->getCacheKey('tokens', 'hour', $currentTime->format('Y-m-d-H'));

            Cache::increment($tokenMinuteKey, $estimatedTokens);
            Cache::increment($tokenHourKey, $estimatedTokens);

            Cache::put($tokenMinuteKey, Cache::get($tokenMinuteKey), 65);
            Cache::put($tokenHourKey, Cache::get($tokenHourKey), 3665);
        }
    }

    /**
     * Generate a cache key for rate limiting.
     *
     * @param string $type Type of limit ('requests' or 'tokens')
     * @param string $window Time window ('minute' or 'hour')
     * @param string $timeKey Time-based key component
     */
    private function getCacheKey(string $type, string $window, string $timeKey): string
    {
        return "llm_rate_limit:{$this->provider}:{$type}:{$window}:{$timeKey}";
    }
}
