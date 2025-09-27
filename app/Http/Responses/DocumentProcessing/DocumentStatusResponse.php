<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentStatusResource;
use App\Models\DocumentProcessing;

/**
 * Ответ на запрос статуса документа.
 */
final class DocumentStatusResponse extends BaseDocumentResponse
{
    public function __construct(DocumentProcessing $document)
    {
        $resource = new DocumentStatusResource($document);

        parent::__construct(
            resource: $resource,
            message: 'Статус документа получен',
            meta: [
                'status' => $document->status,
                'is_completed' => $document->isCompleted(),
                'progress_percentage' => $this->calculateProgress($document),
            ]
        );
    }

    /**
     * Вычисляет прогресс обработки документа.
     */
    private function calculateProgress(DocumentProcessing $document): int
    {
        return match ($document->status) {
            'uploaded' => 10,
            'estimated' => 20,
            'pending' => 30,
            'processing' => 50,
            'analyzing' => 80,
            'completed' => 100,
            'failed' => 0,
            default => 0
        };
    }
}