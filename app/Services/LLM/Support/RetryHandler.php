<?php

declare(strict_types=1);

namespace App\Services\LLM\Support;

use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\Exceptions\LLMRateLimitException;
use Closure;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles retry logic with exponential backoff for LLM operations.
 */
final readonly class RetryHandler
{
    /**
     * @param int $maxAttempts Maximum number of retry attempts
     * @param int $baseDelaySeconds Base delay in seconds for exponential backoff
     * @param float $backoffMultiplier Multiplier for exponential backoff
     * @param int $maxDelaySeconds Maximum delay between retries in seconds
     */
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelaySeconds = 1,
        private float $backoffMultiplier = 2.0,
        private int $maxDelaySeconds = 60,
    ) {
    }

    /**
     * Execute a closure with retry logic and exponential backoff.
     *
     * @template T
     *
     * @param Closure(): T $operation The operation to execute
     * @param array<class-string> $retryableExceptions Exceptions that should trigger a retry
     * @param string $operationName Name of the operation for logging
     *
     * @throws LLMException
     *
     * @return T
     */
    public function execute(
        Closure $operation,
        array $retryableExceptions = [LLMRateLimitException::class],
        string $operationName = 'LLM operation',
    ): mixed {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; ++$attempt) {
            try {
                Log::debug("Executing {$operationName}", [
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxAttempts,
                ]);

                return $operation();
            } catch (Throwable $e) {
                $lastException = $e;

                $shouldRetry = $this->shouldRetry($e, $attempt, $retryableExceptions);

                Log::warning("Attempt {$attempt} failed for {$operationName}", [
                    'attempt' => $attempt,
                    'max_attempts' => $this->maxAttempts,
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'will_retry' => $shouldRetry,
                ]);

                if (!$shouldRetry) {
                    break;
                }

                if ($attempt < $this->maxAttempts) {
                    $delay = $this->calculateDelay($attempt, $e);

                    Log::info("Retrying {$operationName} in {$delay} seconds", [
                        'attempt' => $attempt + 1,
                        'delay_seconds' => $delay,
                    ]);

                    sleep($delay);
                }
            }
        }

        // If we got here, all attempts failed
        Log::error("All {$this->maxAttempts} attempts failed for {$operationName}", [
            'final_error' => $lastException?->getMessage(),
            'exception_class' => $lastException ? get_class($lastException) : null,
        ]);

        if ($lastException instanceof LLMException) {
            throw $lastException;
        }

        throw new LLMException(
            "Operation failed after {$this->maxAttempts} attempts: " . ($lastException?->getMessage() ?? 'Unknown error'),
            0,
            $lastException instanceof Exception ? $lastException : null,
        );
    }

    /**
     * Create a retry handler with default configuration for LLM operations.
     */
    public static function forLLMOperations(): self
    {
        $maxAttempts = config('llm.providers.claude.max_retries', 3);
        $baseDelaySeconds = config('llm.providers.claude.retry_delay_seconds', 1);

        return new self(
            maxAttempts: is_int($maxAttempts) ? $maxAttempts : 3,
            baseDelaySeconds: is_int($baseDelaySeconds) ? $baseDelaySeconds : 1,
            backoffMultiplier: 2.0,
            maxDelaySeconds: 60,
        );
    }

    /**
     * Determine if we should retry based on the exception and attempt number.
     *
     * @param Throwable $exception The exception that occurred
     * @param int $attempt Current attempt number
     * @param array<class-string> $retryableExceptions Exceptions that should trigger retry
     */
    private function shouldRetry(Throwable $exception, int $attempt, array $retryableExceptions): bool
    {
        // Don't retry if we've reached max attempts
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        // Check if this exception type is retryable
        foreach ($retryableExceptions as $retryableException) {
            if ($exception instanceof $retryableException) {
                return true;
            }
        }

        // Don't retry non-retryable exceptions
        return false;
    }

    /**
     * Calculate the delay for the next retry attempt using exponential backoff.
     *
     * @param int $attempt Current attempt number (1-based)
     * @param Throwable $exception The exception that triggered the retry
     *
     * @return int Delay in seconds
     */
    private function calculateDelay(int $attempt, Throwable $exception): int
    {
        // For rate limit exceptions, check if we have a retry-after hint
        if ($exception instanceof LLMRateLimitException) {
            $context = $exception->getContext();
            $retryAfter = $context['retry_after'] ?? null;

            if ($retryAfter && is_numeric($retryAfter)) {
                return min((int) $retryAfter, $this->maxDelaySeconds);
            }
        }

        // Calculate exponential backoff delay
        $delay = $this->baseDelaySeconds * pow($this->backoffMultiplier, $attempt - 1);

        // Add some jitter to prevent thundering herd
        $jitter = random_int(0, (int) ($delay * 0.1));
        $delay += $jitter;

        return min((int) $delay, $this->maxDelaySeconds);
    }
}
