<?php

declare(strict_types=1);

namespace App\Services\Prompt\DTOs;

final readonly class ParsedLlmResponse
{
    public function __construct(
        public bool $isValid,
        public array $parsedData,
        public array $anchorValidation,
        public array $warnings,
        public array $errors,
        public ?string $schemaType = null,
        public ?string $rawResponse = null,
        public array $metadata = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->isValid && empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function hasPartialResults(): bool
    {
        return $this->isValid && !empty($this->warnings);
    }

    public function getValidAnchorCount(): int
    {
        return count(array_filter($this->anchorValidation, static fn (array $anchor): bool => $anchor['is_valid'] === true));
    }

    public function getInvalidAnchorCount(): int
    {
        return count(array_filter($this->anchorValidation, static fn (array $anchor): bool => $anchor['is_valid'] === false));
    }

    public function getAnchorValidationErrors(): array
    {
        return array_filter($this->anchorValidation, static fn (array $anchor): bool => $anchor['is_valid'] === false);
    }

    public function getDataByPath(string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $current = $this->parsedData;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Извлекает содержимое для конкретного якоря из ответа
     */
    public function getContentByAnchor(string $anchor): ?string
    {
        // Новый упрощенный формат
        if (isset($this->parsedData['sections']) && is_array($this->parsedData['sections'])) {
            foreach ($this->parsedData['sections'] as $section) {
                if (isset($section['anchor'], $section['content']) 
                    && $section['anchor'] === $anchor) {
                    return $section['content'];
                }
            }
        }

        // Fallback для старого формата
        if (isset($this->parsedData['section_translations']) && is_array($this->parsedData['section_translations'])) {
            foreach ($this->parsedData['section_translations'] as $section) {
                if (isset($section['anchor'], $section['translated_content']) 
                    && $section['anchor'] === $anchor) {
                    return $section['translated_content'];
                }
            }
        }

        return null;
    }

    /**
     * Возвращает все пары якорь => содержимое
     */
    public function getAnchorContentMap(): array
    {
        $map = [];

        // Новый упрощенный формат
        if (isset($this->parsedData['sections']) && is_array($this->parsedData['sections'])) {
            foreach ($this->parsedData['sections'] as $section) {
                if (isset($section['anchor'], $section['content'])) {
                    $map[$section['anchor']] = $section['content'];
                }
            }
            return $map;
        }

        // Fallback для старого формата
        if (isset($this->parsedData['section_translations']) && is_array($this->parsedData['section_translations'])) {
            foreach ($this->parsedData['section_translations'] as $section) {
                if (isset($section['anchor'], $section['translated_content'])) {
                    $map[$section['anchor']] = $section['translated_content'];
                }
            }
        }

        return $map;
    }
}
