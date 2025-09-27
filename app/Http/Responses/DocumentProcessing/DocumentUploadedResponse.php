<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentUploadedResource;
use App\Models\DocumentProcessing;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ответ на успешную загрузку документа.
 */
final class DocumentUploadedResponse extends BaseDocumentResponse
{
    public function __construct(DocumentProcessing $document)
    {
        $resource = new DocumentUploadedResource($document);

        parent::__construct(
            resource: $resource,
            message: 'Документ успешно загружен',
            status: Response::HTTP_CREATED,
            meta: [
                'file_size' => $document->file_size,
                'file_type' => $document->file_type,
                'next_step' => 'estimate',
            ]
        );
    }
}