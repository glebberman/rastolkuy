<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LLM\DTOs;

use App\Services\LLM\DTOs\LLMRequest;
use Tests\TestCase;

final class LLMRequestTest extends TestCase
{
    public function testCreatesBasicRequest(): void
    {
        $request = new LLMRequest(
            content: 'Test content',
            systemPrompt: 'System prompt',
            model: 'claude-3-5-sonnet',
            maxTokens: 1000,
            temperature: 0.5,
            options: ['test' => 'value'],
            metadata: ['key' => 'value'],
        );

        $this->assertEquals('Test content', $request->content);
        $this->assertEquals('System prompt', $request->systemPrompt);
        $this->assertEquals('claude-3-5-sonnet', $request->model);
        $this->assertEquals(1000, $request->maxTokens);
        $this->assertEquals(0.5, $request->temperature);
        $this->assertEquals(['test' => 'value'], $request->options);
        $this->assertEquals(['key' => 'value'], $request->metadata);
    }

    public function testCreatesRequestWithMinimalParams(): void
    {
        $request = new LLMRequest('Test content');

        $this->assertEquals('Test content', $request->content);
        $this->assertNull($request->systemPrompt);
        $this->assertNull($request->model);
        $this->assertNull($request->maxTokens);
        $this->assertNull($request->temperature);
        $this->assertEquals([], $request->options);
        $this->assertEquals([], $request->metadata);
    }

    public function testCreatesSectionTranslationRequest(): void
    {
        $request = LLMRequest::forSectionTranslation(
            sectionContent: 'Legal contract section',
            documentType: 'contract',
            context: ['jurisdiction' => 'US'],
            options: ['style' => 'simple'],
        );

        $this->assertEquals('Legal contract section', $request->content);
        $this->assertStringContainsString('legal document translator', $request->systemPrompt ?? '');
        $this->assertEquals('section_translation', $request->options['type']);
        $this->assertEquals('contract', $request->options['document_type']);
        $this->assertEquals(['jurisdiction' => 'US'], $request->options['context']);
        $this->assertEquals('simple', $request->options['style']);
        $this->assertEquals('section_translation', $request->metadata['request_type']);
        $this->assertEquals('contract', $request->metadata['document_type']);
    }

    public function testCreatesBatchTranslationRequests(): void
    {
        $sections = ['Section 1', 'Section 2', 'Section 3'];

        $requests = LLMRequest::forBatchTranslation(
            sections: $sections,
            documentType: 'agreement',
            context: ['type' => 'employment'],
            options: ['format' => 'bullet_points'],
        );

        $this->assertCount(3, $requests);

        foreach ($requests as $index => $request) {
            $this->assertInstanceOf(LLMRequest::class, $request);
            $this->assertEquals($sections[$index], $request->content);
            $this->assertStringContainsString('legal document translator', $request->systemPrompt ?? '');
            $this->assertEquals('batch_translation', $request->options['type']);
            $this->assertEquals('agreement', $request->options['document_type']);
            $this->assertEquals(['type' => 'employment'], $request->options['context']);
            $this->assertEquals('bullet_points', $request->options['format']);
            $this->assertEquals($index, $request->options['batch_index']);
            $this->assertEquals(3, $request->options['batch_total']);
            $this->assertEquals('batch_translation', $request->metadata['request_type']);
            $this->assertEquals($index, $request->metadata['batch_index']);
        }
    }

    public function testEstimatesInputTokens(): void
    {
        $shortRequest = new LLMRequest('Hi');
        $this->assertEquals(1, $shortRequest->getEstimatedInputTokens());

        $mediumRequest = new LLMRequest('This is a medium length text that should have more tokens');
        $this->assertGreaterThan(5, $mediumRequest->getEstimatedInputTokens());

        $requestWithSystemPrompt = new LLMRequest('Content', 'System prompt');
        $this->assertGreaterThan(2, $requestWithSystemPrompt->getEstimatedInputTokens());
    }

    public function testConvertsToArray(): void
    {
        $request = new LLMRequest(
            content: 'Test content',
            systemPrompt: 'System prompt',
            model: 'claude-3-5-sonnet',
            maxTokens: 1000,
            temperature: 0.5,
            options: ['test' => 'value'],
            metadata: ['key' => 'value'],
        );

        $array = $request->toArray();

        $expectedKeys = [
            'content', 'system_prompt', 'model', 'max_tokens',
            'temperature', 'options', 'metadata',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $array);
        }

        $this->assertEquals('Test content', $array['content']);
        $this->assertEquals('System prompt', $array['system_prompt']);
        $this->assertEquals('claude-3-5-sonnet', $array['model']);
        $this->assertEquals(1000, $array['max_tokens']);
        $this->assertEquals(0.5, $array['temperature']);
        $this->assertEquals(['test' => 'value'], $array['options']);
        $this->assertEquals(['key' => 'value'], $array['metadata']);
    }
}
