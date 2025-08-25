<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentProcessing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read DocumentProcessing $resource
 */
class DocumentProcessingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $processing = $this->resource;

        return [
            'id' => $processing->uuid,
            'filename' => $processing->original_filename,
            'file_type' => $processing->file_type,
            'file_size' => $processing->file_size,
            'task_type' => $processing->task_type,
            'task_description' => $processing->getTaskTypeDescription(),
            'anchor_at_start' => $processing->anchor_at_start,
            'status' => $processing->status,
            'status_description' => $processing->getStatusDescription(),
            'progress_percentage' => $processing->getProgressPercentage(),
            'result' => $this->when($processing->isCompleted() && $processing->result !== null, $processing->result),
            'processing_time_seconds' => $this->when($processing->processing_time_seconds !== null, $processing->processing_time_seconds),
            'cost_usd' => $this->when($processing->cost_usd !== null, $processing->cost_usd),
            'error' => $this->when($processing->isFailed() && $processing->error_details, [
                'message' => ($processing->error_details)['message'] ?? 'Unknown error',
                'details' => $this->whenLoaded('error_details', $processing->error_details),
            ]),
            'metadata' => $this->when($processing->processing_metadata !== null, $processing->processing_metadata),
            'timestamps' => [
                'created_at' => $processing->created_at?->toISOString(),
                'started_at' => $processing->started_at?->toISOString(),
                'completed_at' => $processing->completed_at?->toISOString(),
                'updated_at' => $processing->updated_at?->toISOString(),
            ],
            // Дополнительная информация для отладки в dev окружении
            'debug_info' => $this->when(app()->environment('local'), [
                'database_id' => $processing->id,
                'file_path_hint' => basename($processing->file_path ?? ''),
            ]),
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
                'processed_at' => now()->toISOString(),
            ],
        ];
    }
}