<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentProcessing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DocumentProcessing $resource
 */
class DocumentResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => 'Результат обработки документа',
            'data' => [
                'id' => $this->resource->uuid,
                'filename' => $this->resource->original_filename,
                'task_type' => $this->resource->task_type,
                'result' => $this->resource->result,
                'processing_time_seconds' => $this->resource->processing_time_seconds,
                'cost_usd' => $this->resource->cost_usd,
                'metadata' => $this->resource->processing_metadata,
                'completed_at' => $this->resource->completed_at?->toJSON(),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => 'v1',
                'action' => 'document_result',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}