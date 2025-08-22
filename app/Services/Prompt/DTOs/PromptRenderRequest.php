<?php

declare(strict_types=1);

namespace App\Services\Prompt\DTOs;

final readonly class PromptRenderRequest
{
    public function __construct(
        public string $systemName,
        public ?string $templateName,
        public array $variables,
        public array $options = [],
    ) {
    }

    public static function create(string $systemName, ?string $templateName = null, array $variables = [], array $options = []): self
    {
        return new self($systemName, $templateName, $variables, $options);
    }
}
