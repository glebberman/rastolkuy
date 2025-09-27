<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Models\DocumentExport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class DocumentExportResponse extends JsonResponse
{
    public function __construct(DocumentExport $export)
    {
        $documentProcessing = $export->documentProcessing;

        if ($documentProcessing === null) {
            throw new \InvalidArgumentException('DocumentExport must have a related DocumentProcessing');
        }

        $data = [
            'success' => true,
            'message' => 'Документ экспортирован',
            'data' => [
                'document_id' => $documentProcessing->uuid,
                'format' => $export->format,
                'filename' => $export->filename,
                'download_url' => route('api.documents.download', [
                    'uuid' => $documentProcessing->uuid,
                    'token' => $export->download_token,
                ]),
                'file_size' => $export->getFormattedFileSize(),
                'expires_at' => $export->expires_at->toISOString(),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid(),
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}