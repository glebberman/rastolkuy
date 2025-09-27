<?php

declare(strict_types=1);

namespace App\Services\Export\DTOs;

/**
 * DTO для риска/противоречия.
 */
final readonly class Risk
{
    public function __construct(
        public string $type,
        public string $text,
    ) {
    }

    public function isContradiction(): bool
    {
        return $this->type === 'contradiction';
    }

    public function isRisk(): bool
    {
        return $this->type === 'risk';
    }

    public function isWarning(): bool
    {
        return $this->type === 'warning';
    }
}