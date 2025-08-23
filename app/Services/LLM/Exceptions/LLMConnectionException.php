<?php

declare(strict_types=1);

namespace App\Services\LLM\Exceptions;

/**
 * Exception thrown when LLM connection fails.
 */
final class LLMConnectionException extends LLMException
{
    public static function invalidApiKey(string $provider): self
    {
        return new self(
            message: "Invalid API key for {$provider} provider",
            code: 401,
            context: ['provider' => $provider, 'error_type' => 'invalid_api_key'],
        );
    }

    public static function connectionTimeout(string $provider, float $timeout): self
    {
        return new self(
            message: "Connection to {$provider} timed out after {$timeout} seconds",
            code: 408,
            context: ['provider' => $provider, 'timeout' => $timeout, 'error_type' => 'timeout'],
        );
    }

    public static function networkError(string $provider, string $errorMessage): self
    {
        return new self(
            message: "Network error connecting to {$provider}: {$errorMessage}",
            code: 500,
            context: ['provider' => $provider, 'original_error' => $errorMessage, 'error_type' => 'network'],
        );
    }
}
