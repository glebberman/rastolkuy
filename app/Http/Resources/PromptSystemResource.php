<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PromptSystem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromptSystem
 */
class PromptSystemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'system_prompt' => $this->system_prompt,
            'default_parameters' => $this->default_parameters,
            'schema' => $this->schema,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'metadata' => $this->metadata,
            'templates_count' => $this->whenLoaded('templates', fn () => $this->templates->count()),
            'executions_count' => $this->whenLoaded('executions', fn () => $this->executions->count()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
