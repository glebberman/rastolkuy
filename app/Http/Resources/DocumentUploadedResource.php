<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DocumentProcessing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property DocumentProcessing $resource
 */
class DocumentUploadedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return (new DocumentProcessingResource($this->resource))->toArray($request);
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
                'action' => 'document_uploaded',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}
