<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LLM\Adapters;

use App\Services\LLM\Adapters\ClaudeAdapter;
use App\Services\LLM\DTOs\LLMRequest;
use App\Services\LLM\Exceptions\LLMConnectionException;
use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\Exceptions\LLMRateLimitException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Tests\TestCase;

final class ClaudeAdapterTest extends TestCase
{
    private ClaudeAdapter $adapter;

    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);

        $this->adapter = new ClaudeAdapter(
            apiKey: 'test-api-key',
            baseUrl: 'https://api.anthropic.com/v1/messages',
            timeoutSeconds: 30,
        );

        // Replace the HTTP client with our mocked one
        $reflection = new ReflectionClass($this->adapter);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->adapter, new Client(['handler' => $handlerStack]));

        Cache::flush();
    }

    public function testExecutesSuccessfulRequest(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'content' => [
                ['type' => 'text', 'text' => 'This is a translation of the legal text.'],
            ],
            'usage' => [
                'input_tokens' => 50,
                'output_tokens' => 25,
            ],
            'stop_reason' => 'end_turn',
        ])));

        $request = new LLMRequest(
            content: 'Legal text to translate',
            systemPrompt: 'You are a legal translator',
        );

        $response = $this->adapter->execute($request);

        $this->assertEquals('This is a translation of the legal text.', $response->content);
        $this->assertEquals(50, $response->inputTokens);
        $this->assertEquals(25, $response->outputTokens);
        $this->assertEquals(75, $response->getTotalTokens());
        $this->assertTrue($response->isSuccess());
        $this->assertGreaterThan(0, $response->costUsd);
        $this->assertGreaterThan(0, $response->executionTimeMs);
    }

    public function testHandlesMultipleContentBlocks(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'content' => [
                ['type' => 'text', 'text' => 'First part '],
                ['type' => 'text', 'text' => 'second part.'],
            ],
            'usage' => [
                'input_tokens' => 30,
                'output_tokens' => 15,
            ],
            'stop_reason' => 'end_turn',
        ])));

        $request = new LLMRequest('Test content');
        $response = $this->adapter->execute($request);

        $this->assertEquals('First part second part.', $response->content);
    }

    public function testValidatesRequestContent(): void
    {
        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Request content cannot be empty');

        $request = new LLMRequest('');
        $this->adapter->execute($request);
    }

    public function testValidatesUnsupportedModel(): void
    {
        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Unsupported model: invalid-model');

        $request = new LLMRequest('Test', model: 'invalid-model');
        $this->adapter->execute($request);
    }

    public function testValidatesTemperatureRange(): void
    {
        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Temperature must be between 0 and 1');

        $request = new LLMRequest('Test', temperature: 1.5);
        $this->adapter->execute($request);
    }

    public function testValidatesPositiveMaxTokens(): void
    {
        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Max tokens must be positive');

        $request = new LLMRequest('Test', maxTokens: -1);
        $this->adapter->execute($request);
    }

    public function testHandles401Unauthorized(): void
    {
        $this->mockHandler->append(new ClientException(
            'Unauthorized',
            new Request('POST', 'https://api.anthropic.com/v1/messages'),
            new Response(401, [], '{"error": {"message": "Invalid API key"}}'),
        ));

        $this->expectException(LLMConnectionException::class);
        $this->expectExceptionMessage('Invalid API key');

        $request = new LLMRequest('Test content');
        $this->adapter->execute($request);
    }

    public function testHandles429RateLimit(): void
    {
        $this->mockHandler->append(new ClientException(
            'Rate limited',
            new Request('POST', 'https://api.anthropic.com/v1/messages'),
            new Response(429, ['Retry-After' => ['60']], '{"error": {"message": "Rate limit exceeded"}}'),
        ));

        $this->expectException(LLMRateLimitException::class);

        $request = new LLMRequest('Test content');
        $this->adapter->execute($request);
    }

    public function testHandlesConnectionTimeout(): void
    {
        $this->mockHandler->append(new ConnectException(
            'Connection timeout',
            new Request('POST', 'https://api.anthropic.com/v1/messages'),
        ));

        $this->expectException(LLMConnectionException::class);
        $this->expectExceptionMessage('Connection to claude timed out');

        $request = new LLMRequest('Test content');
        $this->adapter->execute($request);
    }

    public function testHandlesJsonParsingError(): void
    {
        $this->mockHandler->append(new Response(200, [], 'invalid json'));

        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Failed to parse Claude API response');

        $request = new LLMRequest('Test content');
        $this->adapter->execute($request);
    }

    public function testExecutesBatchRequests(): void
    {
        // Mock responses for each request in the batch
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'content' => [['type' => 'text', 'text' => 'Response 1']],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
                'stop_reason' => 'end_turn',
            ])),
            new Response(200, [], json_encode([
                'content' => [['type' => 'text', 'text' => 'Response 2']],
                'usage' => ['input_tokens' => 15, 'output_tokens' => 8],
                'stop_reason' => 'end_turn',
            ])),
        );

        $requests = [
            new LLMRequest('Content 1'),
            new LLMRequest('Content 2'),
        ];

        $responses = $this->adapter->executeBatch($requests);

        $this->assertCount(2, $responses);
        $this->assertEquals('Response 1', $responses[0]->content);
        $this->assertEquals('Response 2', $responses[1]->content);
    }

    public function testBatchFailsOnInvalidRequestType(): void
    {
        $this->expectException(LLMException::class);
        $this->expectExceptionMessage('Invalid request at index 0');

        $this->adapter->executeBatch(['not a request']);
    }

    public function testValidatesConnectionSuccess(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'content' => [['type' => 'text', 'text' => 'test response']],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            'stop_reason' => 'end_turn',
        ])));

        $isValid = $this->adapter->validateConnection();
        $this->assertTrue($isValid);
    }

    public function testValidatesConnectionFailure(): void
    {
        $this->mockHandler->append(new ClientException(
            'Unauthorized',
            new Request('POST', 'https://api.anthropic.com/v1/messages'),
            new Response(401),
        ));

        $isValid = $this->adapter->validateConnection();
        $this->assertFalse($isValid);
    }

    public function testReturnsProviderName(): void
    {
        $this->assertEquals('claude', $this->adapter->getProviderName());
    }

    public function testReturnsSupportedModels(): void
    {
        $models = $this->adapter->getSupportedModels();

        $this->assertIsArray($models);
        $this->assertContains('claude-3-5-sonnet-20241022', $models);
        $this->assertContains('claude-3-5-haiku-20241022', $models);
    }

    public function testCalculatesCost(): void
    {
        $cost = $this->adapter->calculateCost(
            inputTokens: 1000000,
            outputTokens: 1000000,
            model: 'claude-3-5-sonnet-20241022',
        );

        // Based on pricing: $3 input + $15 output per 1M tokens = $18
        $this->assertEquals(18.0, $cost);
    }

    public function testCalculatesCostWithFallbackPricing(): void
    {
        $cost = $this->adapter->calculateCost(
            inputTokens: 1000000,
            outputTokens: 1000000,
            model: 'unknown-model',
        );

        // Should use default pricing
        $this->assertGreaterThan(0, $cost);
    }

    public function testCountsTokensApproximation(): void
    {
        $tokenCount = $this->adapter->countTokens('This is a test', 'claude-3-5-sonnet');

        // Simple approximation: 4 chars per token, so ~14 chars / 4 = ~4 tokens
        $this->assertGreaterThan(2, $tokenCount);
        $this->assertLessThan(6, $tokenCount);
    }

    public function testCachesConnectionValidation(): void
    {
        // First call should make HTTP request
        $this->mockHandler->append(new Response(200, [], json_encode([
            'content' => [['type' => 'text', 'text' => 'test']],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])));

        $result1 = $this->adapter->validateConnection();

        // Second call should use cache (no HTTP request)
        $result2 = $this->adapter->validateConnection();

        $this->assertTrue($result1);
        $this->assertTrue($result2);

        // MockHandler should have only been called once
        $this->assertEquals(0, $this->mockHandler->count());
    }
}
