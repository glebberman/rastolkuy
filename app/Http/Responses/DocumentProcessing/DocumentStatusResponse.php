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
            DocumentProcessing::STATUS_UPLOADED => 10,
            DocumentProcessing::STATUS_ESTIMATED => 20,
            DocumentProcessing::STATUS_PENDING => 30,
            DocumentProcessing::STATUS_PROCESSING => 50,
            DocumentProcessing::STATUS_ANALYZING => 80,
            DocumentProcessing::STATUS_COMPLETED => 100,
            DocumentProcessing::STATUS_FAILED => 0,
        };
    }
}