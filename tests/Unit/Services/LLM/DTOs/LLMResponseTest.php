<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LLM\DTOs;

use App\Services\LLM\DTOs\LLMResponse;
use Tests\TestCase;

final class LLMResponseTest extends TestCase
{
    public function testCreatesResponse(): void
    {
        $response = new LLMResponse(
            content: 'Generated response',
            model: 'claude-3-5-sonnet',
            inputTokens: 100,
            outputTokens: 50,
            executionTimeMs: 1500.0,
            costUsd: 0.001,
            stopReason: 'end_turn',
            metadata: ['provider' => 'claude'],
            usage: ['input_tokens' => 100, 'output_tokens' => 50],
        );

        $this->assertEquals('Generated response', $response->content);
        $this->assertEquals('claude-3-5-sonnet', $response->model);
        $this->assertEquals(100, $response->inputTokens);
        $this->assertEquals(50, $response->outputTokens);
        $this->assertEquals(1500.0, $response->executionTimeMs);
        $this->assertEquals(0.001, $response->costUsd);
        $this->assertEquals('end_turn', $response->stopReason);
        $this->assertEquals(['provider' => 'claude'], $response->metadata);
        $this->assertEquals(['input_tokens' => 100, 'output_tokens' => 50], $response->usage);
    }

    public function testCalculatesTotalTokens(): void
    {
        $response = new LLMResponse(
            content: 'Response',
            model: 'claude-3-5-sonnet',
            inputTokens: 100,
            outputTokens: 75,
            executionTimeMs: 1000.0,
            costUsd: 0.001,
        );

        $this->assertEquals(175, $response->getTotalTokens());
    }

    public function testDeterminesSuccess(): void
    {
        $successfulResponse = new LLMResponse(
            content: 'Generated content',
            model: 'claude-3-5-sonnet',
            inputTokens: 50,
            outputTokens: 25,
            executionTimeMs: 1000.0,
            costUsd: 0.001,
            stopReason: 'end_turn',
        );

        $this->assertTrue($successfulResponse->isSuccess());

        $emptyResponse = new LLMResponse(
            content: '',
            model: 'claude-3-5-sonnet',
            inputTokens: 50,
            outputTokens: 0,
            executionTimeMs: 1000.0,
            costUsd: 0.001,
        );

        $this->assertFalse($emptyResponse->isSuccess());

        $errorResponse = new LLMResponse(
            content: 'Some content',
            model: 'claude-3-5-sonnet',
            inputTokens: 50,
            outputTokens: 25,
            executionTimeMs: 1000.0,
            costUsd: 0.001,
            stopReason: 'error',
        );

        $this->assertFalse($errorResponse->isSuccess());
    }

    public function testCalculatesCostPerToken(): void
    {
        $response = new LLMResponse(
            content: 'Response',
            model: 'claude-3-5-sonnet',
            inputTokens: 100,
            outputTokens: 100,
            executionTimeMs: 1000.0,
            costUsd: 0.002,
        );

        $this->assertEquals(0.00001, $response->getCostPerToken());

        $zeroTokenResponse = new LLMResponse(
            content: '',
            model: 'claude-3-5-sonnet',
            inputTokens: 0,
            outputTokens: 0,
            executionTimeMs: 1000.0,
            costUsd: 0.001,
        );

        $this->assertEquals(0.0, $zeroTokenResponse->getCostPerToken());
    }

    public function testCalculatesTokensPerSecond(): void
    {
        $response = new LLMResponse(
            content: 'Response',
            model: 'claude-3-5-sonnet',
            inputTokens: 50,
            outputTokens: 100,
            executionTimeMs: 2000.0, // 2 seconds
            costUsd: 0.001,
        );

        $this->assertEquals(50.0, $response->getTokensPerSecond()); // 100 output tokens / 2 seconds

        $zeroTimeResponse = new LLMResponse(
            content: 'Response',
            model: 'claude-3-5-sonnet',
            inputTokens: 50,
            outputTokens: 100,
            executionTimeMs: 0.0,
            costUsd: 0.001,
        );

        $this->assertEquals(0.0, $zeroTimeResponse->getTokensPerSecond());
    }

    public function testConvertsToArray(): void
    {
        $response = new LLMResponse(
            content: 'Generated response',
            model: 'claude-3-5-sonnet',
            inputTokens: 100,
            outputTokens: 50,
            executionTimeMs: 1500.0,
            costUsd: 0.001,
            stopReason: 'end_turn',
            metadata: ['provider' => 'claude'],
            usage: ['input_tokens' => 100, 'output_tokens' => 50],
        );

        $array = $response->toArray();

        $expectedKeys = [
            'content', 'model', 'input_tokens', 'output_tokens', 'total_tokens',
            'execution_time_ms', 'cost_usd', 'stop_reason', 'metadata', 'usage', 'performance',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }

        $this->assertEquals('Generated response', $array['content']);
        $this->assertEquals('claude-3-5-sonnet', $array['model']);
        $this->assertEquals(100, $array['input_tokens']);
        $this->assertEquals(50, $array['output_tokens']);
        $this->assertEquals(150, $array['total_tokens']);
        $this->assertEquals(1500.0, $array['execution_time_ms']);
        $this->assertEquals(0.001, $array['cost_usd']);

        $this->assertArrayHasKey('tokens_per_second', $array['performance']);
        $this->assertArrayHasKey('cost_per_token', $array['performance']);
    }

    public function testCreatesFromClaudeResponse(): void
    {
        $claudeResponseData = [
            'content' => [
                ['type' => 'text', 'text' => 'Hello world'],
                ['type' => 'text', 'text' => ' from Claude!'],
            ],
            'usage' => [
                'input_tokens' => 25,
                'output_tokens' => 10,
            ],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
        ];

        $response = LLMResponse::fromClaudeResponse(
            $claudeResponseData,
            'claude-3-5-sonnet-20241022',
            1234.5,
        );

        $this->assertEquals('Hello world from Claude!', $response->content);
        $this->assertEquals('claude-3-5-sonnet-20241022', $response->model);
        $this->assertEquals(25, $response->inputTokens);
        $this->assertEquals(10, $response->outputTokens);
        $this->assertEquals(1234.5, $response->executionTimeMs);
        $this->assertEquals('end_turn', $response->stopReason);
        $this->assertEquals('claude', $response->metadata['provider']);
        $this->assertGreaterThan(0, $response->costUsd);
    }

    public function testHandlesEmptyClaudeResponse(): void
    {
        $claudeResponseData = [
            'content' => [],
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 0,
            ],
        ];

        $response = LLMResponse::fromClaudeResponse(
            $claudeResponseData,
            'claude-3-5-sonnet-20241022',
            500.0,
        );

        $this->assertEquals('', $response->content);
        $this->assertEquals(10, $response->inputTokens);
        $this->assertEquals(0, $response->outputTokens);
        $this->assertFalse($response->isSuccess());
    }
}
