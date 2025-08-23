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
}
