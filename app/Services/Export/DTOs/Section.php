<?php

declare(strict_types=1);

namespace App\Services\Export\DTOs;

/**
 * DTO для секции документа.
 */
final readonly class Section
{
    public function __construct(
        public string $id,
        public string $title,
        public string $originalContent,
        /** @var array<string> */
        public array $translatedContent,
        /** @var array<Risk> */
        public array $risks,
        public ?string $anchor = null,
    ) {
    }

    public function hasTranslations(): bool
    {
        return !empty($this->translatedContent);
    }

    public function hasRisks(): bool
    {
        return !empty($this->risks);
    }

    public function getMainTranslation(): string
    {
        return $this->translatedContent[0] ?? '';
    }
}