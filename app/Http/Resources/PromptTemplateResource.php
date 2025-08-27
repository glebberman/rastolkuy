<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PromptTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromptTemplate
 */
class PromptTemplateResource extends JsonResource
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
            'prompt_system_id' => $this->prompt_system_id,
            'name' => $this->name,
            'template' => $this->template,
            'required_variables' => $this->required_variables,
            'optional_variables' => $this->optional_variables,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'prompt_system' => $this->whenLoaded('promptSystem', fn () => new PromptSystemResource($this->promptSystem)),
            'executions_count' => $this->when(
                $this->relationLoaded('executions'),
                fn () => $this->executions->count(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
