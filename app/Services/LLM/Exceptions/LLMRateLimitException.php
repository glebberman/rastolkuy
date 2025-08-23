<?php

declare(strict_types=1);

namespace App\Services\LLM\Exceptions;

/**
 * Exception thrown when rate limits are exceeded.
 */
final class LLMRateLimitException extends LLMException
{
    public static function requestLimitExceeded(string $provider, int $limit, int $timeWindowSeconds): self
    {
        return new self(
            message: "Request rate limit exceeded for {$provider}. Limit: {$limit} requests per {$timeWindowSeconds} seconds",
            code: 429,
            context: [
                'provider' => $provider,
                'limit' => $limit,
                'time_window_seconds' => $timeWindowSeconds,
                'error_type' => 'request_rate_limit',
            ],
        );
    }

    public static function tokenLimitExceeded(string $provider, int $tokenLimit, int $timeWindowSeconds): self
    {
        return new self(
            message: "Token rate limit exceeded for {$provider}. Limit: {$tokenLimit} tokens per {$timeWindowSeconds} seconds",
            code: 429,
            context: [
                'provider' => $provider,
                'token_limit' => $tokenLimit,
                'time_window_seconds' => $timeWindowSeconds,
                'error_type' => 'token_rate_limit',
            ],
        );
    }

    public static function fromApiResponse(string $provider, array $responseHeaders = []): self
    {
        $retryAfterRaw = $responseHeaders['retry-after'] ?? $responseHeaders['Retry-After'] ?? null;

        // Handle case where header might be an array
        $retryAfter = null;

        if ($retryAfterRaw !== null) {
            if (is_array($retryAfterRaw)) {
                $retryAfter = reset($retryAfterRaw); // Get first value
            } else {
                $retryAfter = $retryAfterRaw;
            }

            // Ensure it's a string or number
            if (!is_scalar($retryAfter)) {
                $retryAfter = null;
            }
        }

        return new self(
            message: "Rate limit exceeded for {$provider}" . ($retryAfter ? ". Retry after {$retryAfter} seconds" : ''),
            code: 429,
            context: [
                'provider' => $provider,
                'retry_after' => $retryAfter,
                'response_headers' => $responseHeaders,
                'error_type' => 'api_rate_limit',
            ],
        );
    }
}
