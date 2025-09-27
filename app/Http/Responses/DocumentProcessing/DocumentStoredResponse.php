<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentStoredResource;
use App\Models\DocumentProcessing;

/**
 * Ответ на успешное сохранение документа (старый метод).
 */
final class DocumentStoredResponse extends BaseDocumentResponse
{
    public function __construct(DocumentProcessing $document)
    {
        $resource = new DocumentStoredResource($document);

        parent::__construct(
            resource: $resource,
            message: 'Документ успешно сохранен и поставлен в очередь на обработку',
            meta: [
                'task_type' => $document->task_type,
                'estimated_time' => 'от 1 до 5 минут',
                'method' => 'legacy_store',
            ]
        );
    }
}