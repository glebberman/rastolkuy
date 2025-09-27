<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentCancelledResource;
use App\Models\DocumentProcessing;

/**
 * Ответ на успешную отмену обработки документа.
 */
final class DocumentCancelledResponse extends BaseDocumentResponse
{
    public function __construct(DocumentProcessing $document)
    {
        $resource = new DocumentCancelledResource($document);

        parent::__construct(
            resource: $resource,
            message: 'Обработка документа отменена',
            meta: [
                'previous_status' => $document->getOriginal('status'),
                'cancelled_at' => now()->toISOString(),
            ]
        );
    }
}