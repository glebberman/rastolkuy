<?php

declare(strict_types=1);

namespace App\Services\Prompt\DTOs;

final readonly class LlmParsingRequest
{
    public function __construct(
        public string $rawResponse,
        public ?array $expectedSchema = null,
        public ?string $schemaType = null,
        public array $originalAnchors = [],
        public array $validationRules = [],
        public bool $strictValidation = true,
    ) {
    }

    public static function forTranslation(string $rawResponse, array $originalAnchors, ?array $schema = null): self
    {
        return new self(
            rawResponse: $rawResponse,
            expectedSchema: $schema,
            schemaType: 'translation',
            originalAnchors: $originalAnchors,
            validationRules: ['anchors_required'],
            strictValidation: true,
        );
    }

    public static function forAnalysis(string $rawResponse, string $analysisType, ?array $schema = null): self
    {
        return new self(
            rawResponse: $rawResponse,
            expectedSchema: $schema,
            schemaType: $analysisType,
            originalAnchors: [],
            validationRules: ['confidence_required'],
            strictValidation: true,
        );
    }

    public static function forGeneral(string $rawResponse, ?array $schema = null): self
    {
        return new self(
            rawResponse: $rawResponse,
            expectedSchema: $schema,
            schemaType: 'general',
            originalAnchors: [],
            validationRules: [],
            strictValidation: false,
        );
    }
}
