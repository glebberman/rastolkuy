<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\DTOs;

use App\Services\Parser\Extractors\Elements\DocumentElement;

final readonly class ExtractedDocument
{
    /**
     * @param array<DocumentElement> $elements
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $metrics
     * @param array<string>|null $errors
     */
    public function __construct(
        public string $originalPath,
        public string $mimeType,
        public array $elements,
        public array $metadata,
        public int $totalPages,
        public float $extractionTime,
        public array $metrics = [],
        public ?array $errors = null,
    ) {
    }

    public function getPlainText(): string
    {
        return implode("\n", array_map(
            fn (DocumentElement $element) => $element->getPlainText(),
            $this->elements,
        ));
    }

    /**
     * @return array<DocumentElement>
     */
    public function getElementsByType(string $type): array
    {
        return array_filter(
            $this->elements,
            fn (DocumentElement $element) => $element->type === $type,
        );
    }

    /**
     * @return array<DocumentElement>
     */
    public function getHeaders(): array
    {
        return $this->getElementsByType('header');
    }

    /**
     * @return array<DocumentElement>
     */
    public function getTables(): array
    {
        return $this->getElementsByType('table');
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        return [
            'original_path' => $this->originalPath,
            'mime_type' => $this->mimeType,
            'elements' => array_map(
                fn (DocumentElement $element) => $element->serialize(),
                $this->elements,
            ),
            'metadata' => $this->metadata,
            'total_pages' => $this->totalPages,
            'extraction_time' => $this->extractionTime,
            'metrics' => $this->metrics,
            'errors' => $this->errors,
        ];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== null && count($this->errors) > 0;
    }

    public function getElementsCount(): int
    {
        return count($this->elements);
    }
}
