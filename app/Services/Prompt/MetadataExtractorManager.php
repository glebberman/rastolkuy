<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use App\Services\Prompt\Extractors\AnalysisMetadataExtractor;
use App\Services\Prompt\Extractors\BaseMetadataExtractor;
use App\Services\Prompt\Extractors\TranslationMetadataExtractor;

final readonly class MetadataExtractorManager
{
    /**
     * @var array<string, BaseMetadataExtractor>
     */
    private array $extractors;

    public function __construct()
    {
        $this->extractors = [
            'translation' => new TranslationMetadataExtractor(),
            'contradiction' => new AnalysisMetadataExtractor(),
            'ambiguity' => new AnalysisMetadataExtractor(),
            'general' => new AnalysisMetadataExtractor(),
            'analysis' => new AnalysisMetadataExtractor(),
        ];
    }

    public function extractMetadata(array $data, ?string $schemaType = null): array
    {
        $schemaType ??= $this->detectSchemaType($data);

        $extractor = $this->getExtractor($schemaType);

        return $extractor->extract($data);
    }

    public function hasExtractor(string $schemaType): bool
    {
        return isset($this->extractors[$schemaType]);
    }

    public function getSupportedSchemaTypes(): array
    {
        return array_keys($this->extractors);
    }

    private function getExtractor(string $schemaType): BaseMetadataExtractor
    {
        return $this->extractors[$schemaType] ?? $this->extractors['general'];
    }

    private function detectSchemaType(array $data): string
    {
        // Определяем тип схемы на основе содержимого данных

        if (isset($data['section_translations'])) {
            return 'translation';
        }

        if (isset($data['contradictions_found'])) {
            return 'contradiction';
        }

        if (isset($data['ambiguities_found'])) {
            return 'ambiguity';
        }

        if (isset($data['analysis_type'])) {
            return $data['analysis_type'];
        }

        return 'general';
    }
}
