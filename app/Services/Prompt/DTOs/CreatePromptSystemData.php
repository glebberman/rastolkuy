<?php

declare(strict_types=1);

namespace App\Services\Prompt\DTOs;

use App\Http\Requests\Api\StorePromptSystemRequest;

final readonly class CreatePromptSystemData
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $description,
        public string $systemPrompt,
        public ?array $defaultParameters,
        public ?array $schema,
        public bool $isActive,
        public string $version,
        public ?array $metadata,
    ) {
    }

    public static function fromRequest(StorePromptSystemRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: is_string($validated['name'] ?? null) ? $validated['name'] : '',
            type: is_string($validated['type'] ?? null) ? $validated['type'] : '',
            description: isset($validated['description']) && is_string($validated['description']) ? $validated['description'] : null,
            systemPrompt: is_string($validated['system_prompt'] ?? null) ? $validated['system_prompt'] : '',
            defaultParameters: isset($validated['default_parameters']) && is_array($validated['default_parameters']) ? $validated['default_parameters'] : null,
            schema: isset($validated['schema']) && is_array($validated['schema']) ? $validated['schema'] : null,
            isActive: is_bool($validated['is_active'] ?? null) ? $validated['is_active'] : true,
            version: is_string($validated['version'] ?? null) ? $validated['version'] : '1.0.0',
            metadata: isset($validated['metadata']) && is_array($validated['metadata']) ? $validated['metadata'] : null,
        );
    }
}
