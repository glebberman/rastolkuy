<?php

declare(strict_types=1);

namespace App\Services\Validation\DTOs;

final readonly class ValidationResult
{
    /**
     * @param array<string> $errors
     * @param array<string> $warnings
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public bool $isValid,
        public array $errors = [],
        public array $warnings = [],
        public array $metadata = [],
    ) {
    }

    public static function valid(array $metadata = []): self
    {
        return new self(true, [], [], $metadata);
    }

    public static function invalid(array $errors, array $warnings = [], array $metadata = []): self
    {
        return new self(false, $errors, $warnings, $metadata);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Merge with another validation result.
     */
    public function merge(self $other): self
    {
        return new self(
            $this->isValid && $other->isValid,
            array_merge($this->errors, $other->errors),
            array_merge($this->warnings, $other->warnings),
            array_merge($this->metadata, $other->metadata),
        );
    }
}
