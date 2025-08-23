<?php

declare(strict_types=1);

namespace Tests\Integration\Services\LLM;

use App\Services\LLM\Adapters\ClaudeAdapter;
use App\Services\LLM\LLMService;
use App\Services\LLM\Support\RateLimiter;
use App\Services\LLM\Support\RetryHandler;
use App\Services\LLM\UsageMetrics;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Integration tests for LLMService with real Claude API.
 *
 * These tests require a valid Claude API key in the environment.
 * They are marked to skip if the API key is not available.
 *
 * To run these tests:
 * 1. Set CLAUDE_API_KEY environment variable
 * 2. Run: php artisan test --testsuite=Integration
 */
final class LLMServiceIntegrationTest extends TestCase
{
    private LLMService $llmService;

    protected function setUp(): void
    {
        parent::setUp();

        $apiKey = env('CLAUDE_API_KEY');

        if (empty($apiKey)) {
            $this->markTestSkipped('Claude API key not configured. Set CLAUDE_API_KEY environment variable to run integration tests.');
        }

        // Create real LLM service with test configuration
        $adapter = new ClaudeAdapter(
            apiKey: $apiKey,
            timeoutSeconds: 30,
        );

        $rateLimiter = new RateLimiter(
            provider: 'claude',
            requestsPerMinute: 5, // Conservative for testing
            requestsPerHour: 100,
            tokensPerMinute: 10000,
            tokensPerHour: 100000,
        );

        $retryHandler = new RetryHandler(
            maxAttempts: 2, // Fewer retries for testing
            baseDelaySeconds: 1,
        );

        $usageMetrics = new UsageMetrics('claude');

        $this->llmService = new LLMService(
            adapter: $adapter,
            rateLimiter: $rateLimiter,
            retryHandler: $retryHandler,
            usageMetrics: $usageMetrics,
        );

        Cache::flush();
    }

    /**
     * @group integration
     * @group slow
     */
    public function testTranslatesSimpleLegalSection(): void
    {
        $legalText = 'The party of the first part hereby agrees to indemnify and hold harmless the party of the second part against any and all claims, damages, losses, and expenses.';

        $response = $this->llmService->translateSection(
            sectionContent: $legalText,
            documentType: 'contract',
            context: ['jurisdiction' => 'US'],
            options: ['style' => 'simple'],
        );

        $this->assertTrue($response->isSuccess());
        $this->assertNotEmpty($response->content);
        $this->assertGreaterThan(0, $response->inputTokens);
        $this->assertGreaterThan(0, $response->outputTokens);
        $this->assertGreaterThan(0, $response->costUsd);
        $this->assertGreaterThan(0, $response->executionTimeMs);

        // The response should be simpler than the original legal text
        $this->assertStringNotContainsString('indemnify', $response->content);
        $this->assertStringNotContainsString('party of the first part', $response->content);

        // Should contain more understandable language
        $this->assertStringContainsStringIgnoringCase('protect', $response->content, 'Response should contain more understandable terms');
    }

    /**
     * @group integration
     * @group slow
     */
    public function testTranslatesBatchOfSections(): void
    {
        $sections = [
            'Whereas the parties hereto desire to enter into this agreement',
            'The term of this agreement shall commence on the effective date',
            'Either party may terminate this agreement upon thirty days written notice',
        ];

        $responses = $this->llmService->translateBatch(
            sections: $sections,
            documentType: 'agreement',
            context: ['type' => 'business'],
            options: ['style' => 'conversational'],
        );

        $this->assertCount(3, $responses);

        foreach ($responses as $index => $response) {
            $this->assertTrue($response->isSuccess(), "Response {$index} should be successful");
            $this->assertNotEmpty($response->content, "Response {$index} should have content");
            $this->assertGreaterThan(0, $response->costUsd, "Response {$index} should have cost");
        }

        // Total cost should be reasonable (less than $0.10 for this small test)
        $totalCost = $responses->sum('costUsd');
        $this->assertLessThan(0.1, $totalCost, 'Total cost should be reasonable for test');
    }

    /**
     * @group integration
     * @group slow
     */
    public function testValidatesConnection(): void
    {
        $isValid = $this->llmService->validateConnection();
        $this->assertTrue($isValid);
    }

    /**
     * @group integration
     */
    public function testEstimatesCostAccurately(): void
    {
        $testContent = 'This is a test legal document section for cost estimation.';

        $estimation = $this->llmService->estimateCost($testContent);

        $this->assertArrayHasKey('estimated_cost_usd', $estimation);
        $this->assertArrayHasKey('estimated_input_tokens', $estimation);
        $this->assertArrayHasKey('estimated_output_tokens', $estimation);
        $this->assertArrayHasKey('model', $estimation);

        $this->assertGreaterThan(0, $estimation['estimated_cost_usd']);
        $this->assertGreaterThan(0, $estimation['estimated_input_tokens']);
        $this->assertLessThan(0.01, $estimation['estimated_cost_usd']); // Should be very cheap for short text
    }

    /**
     * @group integration
     */
    public function testCollectsUsageStatistics(): void
    {
        // Make a small request to generate some stats
        $this->llmService->translateSection('Test legal clause', 'contract');

        $stats = $this->llmService->getUsageStats(1); // Last 1 day

        $this->assertArrayHasKey('provider', $stats);
        $this->assertArrayHasKey('rate_limiting', $stats);
        $this->assertArrayHasKey('metrics', $stats);

        $this->assertEquals('claude', $stats['provider']);
        $this->assertArrayHasKey('totals', $stats['metrics']);
        $this->assertGreaterThan(0, $stats['metrics']['totals']['requests']);
    }

    /**
     * @group integration
     */
    public function testReturnsProviderInfo(): void
    {
        $info = $this->llmService->getProviderInfo();

        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('supported_models', $info);
        $this->assertArrayHasKey('connection_valid', $info);

        $this->assertEquals('claude', $info['name']);
        $this->assertIsArray($info['supported_models']);
        $this->assertContains('claude-3-5-sonnet-20241022', $info['supported_models']);
        $this->assertTrue($info['connection_valid']);
    }

    /**
     * @group integration
     * @group slow
     */
    public function testHandlesRateLimitingGracefully(): void
    {
        // Create a very restrictive rate limiter for testing
        $restrictiveRateLimiter = new RateLimiter(
            provider: 'claude',
            requestsPerMinute: 1,
            requestsPerHour: 10,
            tokensPerMinute: 500,
            tokensPerHour: 5000,
        );

        $restrictiveService = new LLMService(
            adapter: $this->llmService->adapter ?? new ClaudeAdapter(env('CLAUDE_API_KEY')),
            rateLimiter: $restrictiveRateLimiter,
            retryHandler: new RetryHandler(maxAttempts: 1),
            usageMetrics: new UsageMetrics('claude'),
        );

        // First request should succeed
        $response1 = $restrictiveService->translateSection('First test', 'contract');
        $this->assertTrue($response1->isSuccess());

        // Second request should hit rate limit
        $this->expectException(\App\Services\LLM\Exceptions\LLMRateLimitException::class);
        $restrictiveService->translateSection('Second test', 'contract');
    }

    /**
     * @group integration
     * @group slow
     */
    public function testRealisticLegalDocumentTranslation(): void
    {
        $legalClause = '
        LIMITATION OF LIABILITY. EXCEPT FOR BREACHES OF CONFIDENTIALITY OBLIGATIONS, 
        GROSS NEGLIGENCE, OR WILLFUL MISCONDUCT, IN NO EVENT SHALL EITHER PARTY BE LIABLE 
        FOR ANY INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL DAMAGES, 
        INCLUDING BUT NOT LIMITED TO LOST PROFITS, LOST REVENUE, OR LOST DATA, WHETHER IN AN 
        ACTION IN CONTRACT, TORT (INCLUDING NEGLIGENCE), OR OTHERWISE, EVEN IF SUCH PARTY HAS 
        BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.
        ';

        $response = $this->llmService->translateSection(
            sectionContent: trim($legalClause),
            documentType: 'service_agreement',
            context: [
                'audience' => 'general_public',
                'complexity' => 'simple',
                'focus' => 'risks_and_protections',
            ],
            options: [
                'include_examples' => true,
                'explain_implications' => true,
            ],
        );

        $this->assertTrue($response->isSuccess());
        $this->assertNotEmpty($response->content);

        // Response should be significantly longer due to explanations
        $this->assertGreaterThan(strlen(trim($legalClause)), strlen($response->content));

        // Should not contain complex legal jargon
        $this->assertStringNotContainsString('LIMITATION OF LIABILITY', $response->content);
        $this->assertStringNotContainsString('CONSEQUENTIAL DAMAGES', $response->content);

        // Should contain simpler explanations
        $this->assertStringContainsStringIgnoringCase('protect', $response->content);
        $this->assertStringContainsStringIgnoringCase('limit', $response->content);

        // Should be cost-effective (realistic legal clause translation)
        $this->assertLessThan(0.05, $response->costUsd, 'Single clause translation should be under $0.05');
    }
}

/**
 * Helper trait to add case-insensitive string assertion.
 */
trait CaseInsensitiveStringAssertions
{
    protected function assertStringContainsStringIgnoringCase(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContainsString(
            strtolower($needle),
            strtolower($haystack),
            $message,
        );
    }
}
