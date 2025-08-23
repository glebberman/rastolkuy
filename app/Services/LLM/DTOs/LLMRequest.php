<?php

declare(strict_types=1);

namespace App\Services\LLM\DTOs;

/**
 * Data Transfer Object for LLM requests.
 */
final readonly class LLMRequest
{
    /**
     * @param string $content The main content/prompt to send to the LLM
     * @param string|null $systemPrompt Optional system prompt for context
     * @param string|null $model Specific model to use (overrides default)
     * @param int|null $maxTokens Maximum tokens in response
     * @param float|null $temperature Sampling temperature (0.0 to 1.0)
     * @param array<string, mixed> $options Additional provider-specific options
     * @param array<string, mixed> $metadata Additional metadata for tracking
     */
    public function __construct(
        public string $content,
        public ?string $systemPrompt = null,
        public ?string $model = null,
        public ?int $maxTokens = null,
        public ?float $temperature = null,
        public array $options = [],
        public array $metadata = [],
    ) {
    }

    /**
     * Create a new LLM request for document section translation.
     *
     * @param string $sectionContent The document section to translate
     * @param string $documentType Type of document (contract, agreement, etc.)
     * @param array<string, mixed> $context Additional context for translation
     * @param array<string, mixed> $options Additional options
     */
    public static function forSectionTranslation(
        string $sectionContent,
        string $documentType = 'legal_document',
        array $context = [],
        array $options = [],
    ): self {
        return new self(
            content: $sectionContent,
            systemPrompt: 'You are a legal document translator. Translate complex legal text into simple, understandable language while preserving all important legal meanings and implications.',
            options: array_merge([
                'type' => 'section_translation',
                'document_type' => $documentType,
                'context' => $context,
            ], $options),
            metadata: [
                'request_type' => 'section_translation',
                'document_type' => $documentType,
                'created_at' => now()->toISOString(),
            ],
        );
    }

    /**
     * Create a new LLM request for batch document translation.
     *
     * @param array<string> $sections Array of document sections
     * @param string $documentType Type of document
     * @param array<string, mixed> $context Additional context
     * @param array<string, mixed> $options Additional options
     *
     * @return array<self>
     */
    public static function forBatchTranslation(
        array $sections,
        string $documentType = 'legal_document',
        array $context = [],
        array $options = [],
    ): array {
        $requests = [];

        foreach ($sections as $index => $section) {
            $requests[] = new self(
                content: $section,
                systemPrompt: 'You are a legal document translator. Translate complex legal text into simple, understandable language while preserving all important legal meanings and implications.',
                options: array_merge([
                    'type' => 'batch_translation',
                    'document_type' => $documentType,
                    'context' => $context,
                    'batch_index' => $index,
                    'batch_total' => count($sections),
                ], $options),
                metadata: [
                    'request_type' => 'batch_translation',
                    'document_type' => $documentType,
                    'batch_index' => $index,
                    'batch_total' => count($sections),
                    'created_at' => now()->toISOString(),
                ],
            );
        }

        return $requests;
    }

    /**
     * Get the estimated input token count for this request.
     *
     * @return int Estimated token count
     */
    public function getEstimatedInputTokens(): int
    {
        // Simple estimation: roughly 4 characters per token
        $contentLength = mb_strlen($this->content);
        $systemLength = $this->systemPrompt ? mb_strlen($this->systemPrompt) : 0;

        return (int) ceil(($contentLength + $systemLength) / 4);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'system_prompt' => $this->systemPrompt,
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'options' => $this->options,
            'metadata' => $this->metadata,
        ];
    }
}
