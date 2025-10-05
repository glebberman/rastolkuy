<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @property LengthAwarePaginator $resource
 */
class DocumentListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'documents' => DocumentProcessingResource::collection($this->resource->items()),
            'meta' => [
                'api_version' => 'v1',
                'action' => 'documents_list',
                'timestamp' => now()->toISOString(),
                'pagination' => [
                    'current_page' => $this->resource->currentPage(),
                    'last_page' => $this->resource->lastPage(),
                    'per_page' => $this->resource->perPage(),
                    'total' => $this->resource->total(),
                    'from' => $this->resource->firstItem(),
                    'to' => $this->resource->lastItem(),
                ],
            ],
        ];
    }
}
