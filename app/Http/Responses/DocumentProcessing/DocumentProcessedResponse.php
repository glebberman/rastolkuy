<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentProcessedResource;
use App\Models\DocumentProcessing;

/**
 * Ответ на успешную обработку документа.
 */
final class DocumentProcessedResponse extends BaseDocumentResponse
{
    public function __construct(DocumentProcessing $document)
    {
        $resource = new DocumentProcessedResource($document);

        parent::__construct(
            resource: $resource,
            message: 'Документ поставлен в очередь на обработку',
            meta: [
                'task_type' => $document->task_type,
                'estimated_time' => 'от 1 до 5 минут',
                'next_step' => 'check_status',
            ]
        );
    }
}