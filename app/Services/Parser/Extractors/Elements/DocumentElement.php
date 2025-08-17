<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Elements;

abstract class DocumentElement
{
    /**
     * @param array<string, mixed> $position
     * @param array<string, mixed> $style
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly string $content,
        public readonly array $position,
        public readonly array $style,
        public readonly int $pageNumber,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function serialize(): array;

    abstract public function getPlainText(): string;

    public function getConfidenceScore(): float
    {
        $confidence = $this->metadata['confidence'] ?? 1.0;
        return is_numeric($confidence) ? (float) $confidence : 1.0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBaseData(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'position' => $this->position,
            'style' => $this->style,
            'page_number' => $this->pageNumber,
            'metadata' => $this->metadata,
            'confidence_score' => $this->getConfidenceScore(),
        ];
    }
}
