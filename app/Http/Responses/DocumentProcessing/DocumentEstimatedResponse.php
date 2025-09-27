<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentEstimatedResource;
use App\Models\DocumentProcessing;

/**
 * Ответ на успешную оценку документа.
 */
final class DocumentEstimatedResponse extends BaseDocumentResponse
{
    public function __construct(DocumentProcessing $document)
    {
        $resource = new DocumentEstimatedResource($document);

        parent::__construct(
            resource: $resource,
            message: 'Документ успешно оценен',
            meta: [
                'next_step' => 'process',
                'estimated_cost' => $document->processing_metadata['estimated_cost_usd'] ?? null,
                'estimated_tokens' => $document->processing_metadata['estimated_tokens'] ?? null,
            ]
        );
    }
}