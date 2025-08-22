<?php

declare(strict_types=1);

namespace App\Services\Structure\DTOs;

use App\Services\Parser\Extractors\Elements\DocumentElement;
use App\Services\Structure\Validation\InputValidator;
use InvalidArgumentException;

final readonly class DocumentSection
{
    /**
     * @param array<DocumentElement> $elements
     * @param array<DocumentSection> $subsections
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $content,
        public int $level,
        public int $startPosition,
        public int $endPosition,
        public string $anchor,
        public array $elements,
        public array $subsections = [],
        public float $confidence = 1.0,
        public array $metadata = [],
    ) {
        // Валидация данных при создании
        if (empty(trim($id))) {
            throw new InvalidArgumentException('Section ID cannot be empty');
        }

        if (empty(trim($title))) {
            throw new InvalidArgumentException('Section title cannot be empty');
        }

        if ($level < 1 || $level > 10) {
            throw new InvalidArgumentException('Section level must be between 1 and 10');
        }

        if ($startPosition < 0 || $endPosition < 0 || $startPosition > $endPosition) {
            throw new InvalidArgumentException('Invalid section positions');
        }

        InputValidator::validateConfidence($confidence);
    }

    public function hasSubsections(): bool
    {
        return !empty($this->subsections);
    }

    public function getSubsectionCount(): int
    {
        return count($this->subsections);
    }

    public function getElementsCount(): int
    {
        return count($this->elements);
    }

    public function getTotalLength(): int
    {
        return mb_strlen($this->content);
    }

    /**
     * @return array<DocumentSection>
     */
    public function getAllSubsections(): array
    {
        $result = $this->subsections;

        foreach ($this->subsections as $subsection) {
            array_push($result, ...$subsection->getAllSubsections());
        }

        return $result;
    }

    public function getPlainText(): string
    {
        $text = $this->content;

        foreach ($this->subsections as $subsection) {
            $text .= "\n\n" . $subsection->getPlainText();
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'level' => $this->level,
            'start_position' => $this->startPosition,
            'end_position' => $this->endPosition,
            'anchor' => $this->anchor,
            'elements_count' => $this->getElementsCount(),
            'subsections_count' => $this->getSubsectionCount(),
            'total_length' => $this->getTotalLength(),
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
            'subsections' => array_map(
                static fn (DocumentSection $section) => $section->serialize(),
                $this->subsections,
            ),
        ];
    }
}
