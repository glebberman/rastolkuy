<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentResultResource;
use App\Models\DocumentProcessing;

/**
 * Ответ на запрос результата обработки документа.
 */
final class DocumentResultResponse extends BaseDocumentResponse
{
    public function __construct(DocumentProcessing $document)
    {
        $resource = new DocumentResultResource($document);

        parent::__construct(
            resource: $resource,
            message: 'Результат обработки документа получен',
            meta: [
                'processing_time' => $document->processing_time_seconds,
                'cost_usd' => $document->cost_usd,
                'completed_at' => $document->completed_at?->toISOString(),
                'available_exports' => ['html', 'docx', 'pdf'],
            ]
        );
    }
}