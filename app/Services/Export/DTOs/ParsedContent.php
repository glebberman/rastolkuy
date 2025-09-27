<?php

declare(strict_types=1);

namespace App\Services\Export\DTOs;

/**
 * DTO для распарсенного контента.
 */
final readonly class ParsedContent
{
    public function __construct(
        public string $originalContent,
        /** @var array<Section> */
        public array $sections,
        /** @var array<string> */
        public array $anchors,
    ) {
    }

    public function getSectionById(string $id): ?Section
    {
        foreach ($this->sections as $section) {
            if ($section->id === $id) {
                return $section;
            }
        }

        return null;
    }

    public function getSectionsCount(): int
    {
        return count($this->sections);
    }
}