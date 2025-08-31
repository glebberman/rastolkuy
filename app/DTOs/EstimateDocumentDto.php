<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\EstimateDocumentRequest;

final readonly class EstimateDocumentDto
{
    public function __construct(
        public ?string $model,
    ) {
    }

    public static function fromRequest(EstimateDocumentRequest $request): self
    {
        /** @var array{model?: string|null} $validated */
        $validated = $request->validated();

        return new self(
            model: $validated['model'] ?? null,
        );
    }
}
