<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PromptFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromptFeedback
 */
class PromptFeedbackResource extends JsonResource
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
            'prompt_execution_id' => $this->prompt_execution_id,
            'feedback_type' => $this->feedback_type,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'details' => $this->details,
            'user_type' => $this->user_type,
            'user_id' => $this->user_id,
            'metadata' => $this->metadata,
            'prompt_execution' => $this->whenLoaded('promptExecution', function (): array {
                return [
                    'id' => $this->promptExecution->id,
                    'execution_id' => $this->promptExecution->execution_id,
                    'status' => $this->promptExecution->status,
                    'created_at' => $this->promptExecution->created_at,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
