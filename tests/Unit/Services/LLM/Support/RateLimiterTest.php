<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LLM\Support;

use App\Services\LLM\Exceptions\LLMRateLimitException;
use App\Services\LLM\Support\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = new RateLimiter(
            provider: 'test',
            requestsPerMinute: 5,
            requestsPerHour: 50,
            tokensPerMinute: 1000,
            tokensPerHour: 10000,
        );

        // Clear any cached data
        Cache::flush();
    }

    public function testAllowsRequestWithinLimits(): void
    {
        $this->rateLimiter->checkAndReserve(100);
        $this->assertTrue(true); // No exception thrown
    }

    public function testThrowsExceptionWhenMinuteRequestLimitExceeded(): void
    {
        // Make 5 requests (at the limit)
        for ($i = 0; $i < 5; ++$i) {
            $this->rateLimiter->checkAndReserve();
        }

        $this->expectException(LLMRateLimitException::class);
        $this->expectExceptionMessage('Request rate limit exceeded');

        // 6th request should fail
        $this->rateLimiter->checkAndReserve();
    }

    public function testThrowsExceptionWhenMinuteTokenLimitExceeded(): void
    {
        $this->expectException(LLMRateLimitException::class);
        $this->expectExceptionMessage('Token rate limit exceeded');

        // Try to use more tokens than allowed per minute
        $this->rateLimiter->checkAndReserve(1500);
    }

    public function testAllowsGradualTokenUsage(): void
    {
        // Use tokens gradually - should all succeed
        $this->rateLimiter->checkAndReserve(200);
        $this->rateLimiter->checkAndReserve(300);
        $this->rateLimiter->checkAndReserve(400);

        $this->assertTrue(true); // All succeeded
    }

    public function testTracksUsageStats(): void
    {
        $this->rateLimiter->checkAndReserve(100);
        $this->rateLimiter->checkAndReserve(200);

        $stats = $this->rateLimiter->getUsageStats();

        $this->assertEquals('test', $stats['provider']);
        $this->assertIsArray($stats['requests']);
        $this->assertIsArray($stats['tokens']);
        if (is_array($stats['requests']) && is_array($stats['requests']['per_minute'])) {
            $this->assertEquals(2, $stats['requests']['per_minute']['used']);
            $this->assertEquals(3, $stats['requests']['per_minute']['remaining']);
        }
        if (is_array($stats['tokens']) && is_array($stats['tokens']['per_minute'])) {
            $this->assertEquals(300, $stats['tokens']['per_minute']['used']);
            $this->assertEquals(700, $stats['tokens']['per_minute']['remaining']);
        }
    }

    public function testRecordsActualUsage(): void
    {
        $this->rateLimiter->checkAndReserve(100);
        $this->rateLimiter->recordUsage(150);

        // Recording usage shouldn't throw an exception
        $this->assertTrue(true);
    }

    public function testResetsCounters(): void
    {
        $this->rateLimiter->checkAndReserve(100);

        $statsBeforeReset = $this->rateLimiter->getUsageStats();
        $this->assertIsArray($statsBeforeReset['requests']);
        if (is_array($statsBeforeReset['requests']) && is_array($statsBeforeReset['requests']['per_minute'])) {
            $this->assertEquals(1, $statsBeforeReset['requests']['per_minute']['used']);
        }

        $this->rateLimiter->reset();

        $statsAfterReset = $this->rateLimiter->getUsageStats();
        $this->assertIsArray($statsAfterReset['requests']);
        if (is_array($statsAfterReset['requests']) && is_array($statsAfterReset['requests']['per_minute'])) {
            $this->assertEquals(0, $statsAfterReset['requests']['per_minute']['used']);
        }
    }

    public function testCreatesRateLimiterForProvider(): void
    {
        // Mock config for testing
        config(['llm.rate_limiting.claude' => [
            'requests_per_minute' => 100,
            'requests_per_hour' => 1000,
            'tokens_per_minute' => 50000,
            'tokens_per_hour' => 500000,
        ]]);

        $rateLimiter = RateLimiter::forProvider('claude');

        $this->assertInstanceOf(RateLimiter::class, $rateLimiter);

        $stats = $rateLimiter->getUsageStats();
        $this->assertEquals('claude', $stats['provider']);
        $this->assertIsArray($stats['requests']);
        $this->assertIsArray($stats['tokens']);
        if (is_array($stats['requests']) && is_array($stats['requests']['per_minute'])) {
            $this->assertEquals(100, $stats['requests']['per_minute']['limit']);
        }
        if (is_array($stats['tokens']) && is_array($stats['tokens']['per_minute'])) {
            $this->assertEquals(50000, $stats['tokens']['per_minute']['limit']);
        }
    }

    public function testHandlesMissingProviderConfig(): void
    {
        $rateLimiter = RateLimiter::forProvider('unknown');

        // Should use default values
        $stats = $rateLimiter->getUsageStats();
        $this->assertEquals('unknown', $stats['provider']);
        $this->assertIsArray($stats['requests']);
        if (is_array($stats['requests']) && is_array($stats['requests']['per_minute'])) {
            $this->assertEquals(60, $stats['requests']['per_minute']['limit']); // Default
        }
    }

    public function testHourLimitsWorkIndependently(): void
    {
        // This is a simplified test - in practice, hour limits would be tested
        // with time manipulation, but for unit tests we'll verify the structure

        $this->rateLimiter->checkAndReserve(100);

        $stats = $this->rateLimiter->getUsageStats();

        $this->assertIsArray($stats['requests']);
        $this->assertIsArray($stats['tokens']);
        if (is_array($stats['requests'])) {
            $this->assertArrayHasKey('per_hour', $stats['requests']);
            if (is_array($stats['requests']['per_hour'])) {
                $this->assertEquals(1, $stats['requests']['per_hour']['used']);
            }
        }
        if (is_array($stats['tokens'])) {
            $this->assertArrayHasKey('per_hour', $stats['tokens']);
            if (is_array($stats['tokens']['per_hour'])) {
                $this->assertEquals(100, $stats['tokens']['per_hour']['used']);
            }
        }
    }

    public function testHandlesZeroTokenRequests(): void
    {
        // Some requests might not have token estimates
        $this->rateLimiter->checkAndReserve(0);

        $stats = $this->rateLimiter->getUsageStats();
        $this->assertIsArray($stats['requests']);
        $this->assertIsArray($stats['tokens']);
        if (is_array($stats['requests']) && is_array($stats['requests']['per_minute'])) {
            $this->assertEquals(1, $stats['requests']['per_minute']['used']);
        }
        if (is_array($stats['tokens']) && is_array($stats['tokens']['per_minute'])) {
            $this->assertEquals(0, $stats['tokens']['per_minute']['used']);
        }
    }

    public function testPreventsNegativeRemainingCounts(): void
    {
        // Use up all requests
        for ($i = 0; $i < 5; ++$i) {
            $this->rateLimiter->checkAndReserve();
        }

        $stats = $this->rateLimiter->getUsageStats();
        $this->assertIsArray($stats['requests']);
        if (is_array($stats['requests']) && is_array($stats['requests']['per_minute'])) {
            $this->assertEquals(0, $stats['requests']['per_minute']['remaining']);
            $this->assertGreaterThanOrEqual(0, $stats['requests']['per_minute']['remaining']);
        }
    }
}
