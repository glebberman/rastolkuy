<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LLM\Support;

use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\Exceptions\LLMRateLimitException;
use App\Services\LLM\Support\RetryHandler;
use Exception;
use Tests\TestCase;

final class RetryHandlerTest extends TestCase
{
    public function testExecutesSuccessfulOperationOnFirstTry(): void
    {
        $retryHandler = new RetryHandler(maxAttempts: 3);
        $callCount = 0;

        $result = $retryHandler->execute(
            operation: function () use (&$callCount): string {
                ++$callCount;

                return 'success';
            },
            operationName: 'test operation',
        );

        $this->assertEquals('success', $result);
        $this->assertEquals(1, $callCount);
    }

    public function testRetriesOnRetryableException(): void
    {
        $retryHandler = new RetryHandler(
            maxAttempts: 3,
            baseDelaySeconds: 0, // No delay for testing
        );
        $callCount = 0;

        $result = $retryHandler->execute(
            operation: function () use (&$callCount): string {
                ++$callCount;

                if ($callCount < 3) {
                    throw new LLMRateLimitException('Rate limited');
                }

                return 'success after retries';
            },
            retryableExceptions: [LLMRateLimitException::class],
            operationName: 'test operation',
        );

        $this->assertEquals('success after retries', $result);
        $this->assertEquals(3, $callCount);
    }

    public function testFailsAfterMaxAttempts(): void
    {
        $retryHandler = new RetryHandler(
            maxAttempts: 2,
            baseDelaySeconds: 0,
        );
        $callCount = 0;

        $this->expectException(LLMRateLimitException::class);
        $this->expectExceptionMessage('Always fails');

        $retryHandler->execute(
            operation: function () use (&$callCount): string {
                ++$callCount;

                throw new LLMRateLimitException('Always fails');
            },
            retryableExceptions: [LLMRateLimitException::class],
            operationName: 'test operation',
        );

        $this->assertEquals(2, $callCount);
    }

    public function testDoesNotRetryNonRetryableException(): void
    {
        $retryHandler = new RetryHandler(maxAttempts: 3, baseDelaySeconds: 0);
        $callCount = 0;

        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Non-retryable error');

        $retryHandler->execute(
            operation: function () use (&$callCount): string {
                ++$callCount;

                throw new LLMException('Non-retryable error');
            },
            retryableExceptions: [LLMRateLimitException::class],
            operationName: 'test operation',
        );

        $this->assertEquals(1, $callCount);
    }

    public function testWrapsNonLlmExceptionInLlmException(): void
    {
        $retryHandler = new RetryHandler(maxAttempts: 1, baseDelaySeconds: 0);

        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Operation failed after 1 attempts: Generic error');

        $retryHandler->execute(
            operation: function (): string {
                throw new Exception('Generic error');
            },
            operationName: 'test operation',
        );
    }

    public function testRespectsRetryAfterFromRateLimitException(): void
    {
        $retryHandler = new RetryHandler(
            maxAttempts: 2,
            baseDelaySeconds: 1,
            maxDelaySeconds: 60,
        );

        $rateLimitException = new LLMRateLimitException('Rate limited');
        $rateLimitException->addContext('retry_after', 5);

        $callCount = 0;
        $startTime = microtime(true);

        try {
            $retryHandler->execute(
                operation: function () use (&$callCount, $rateLimitException): string {
                    ++$callCount;

                    throw $rateLimitException;
                },
                retryableExceptions: [LLMRateLimitException::class],
                operationName: 'test operation',
            );
        } catch (LLMRateLimitException) {
            // Expected to fail after retries
        }

        $executionTime = microtime(true) - $startTime;

        // Should have taken at least 5 seconds due to retry_after
        $this->assertGreaterThan(4, $executionTime);
        $this->assertEquals(2, $callCount);
    }

    public function testCreatesHandlerForLlmOperations(): void
    {
        $handler = RetryHandler::forLLMOperations();

        $this->assertInstanceOf(RetryHandler::class, $handler);

        // Should work with default configuration
        $result = $handler->execute(
            operation: fn () => 'success',
            operationName: 'test',
        );

        $this->assertEquals('success', $result);
    }

    public function testUsesExponentialBackoffWithJitter(): void
    {
        $retryHandler = new RetryHandler(
            maxAttempts: 3,
            baseDelaySeconds: 1,
            backoffMultiplier: 2.0,
            maxDelaySeconds: 10,
        );

        // We can't easily test the actual delay without making the test slow,
        // so we'll test the logic by checking that attempts are made
        $callCount = 0;
        $startTime = microtime(true);

        try {
            $retryHandler->execute(
                operation: function () use (&$callCount): string {
                    ++$callCount;

                    throw new LLMRateLimitException('Always fails');
                },
                retryableExceptions: [LLMRateLimitException::class],
                operationName: 'test operation',
            );
        } catch (LLMRateLimitException) {
            // Expected
        }

        $executionTime = microtime(true) - $startTime;

        $this->assertEquals(3, $callCount);
        // Should have taken at least the base delay times (1s + 2s = 3s minimum)
        $this->assertGreaterThan(2, $executionTime);
    }
}
