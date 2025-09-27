<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ответ на успешное удаление документа.
 */
final class DocumentDeletedResponse extends JsonResponse
{
    public function __construct(string $documentUuid)
    {
        $data = [
            'success' => true,
            'message' => 'Документ успешно удален',
            'data' => [
                'uuid' => $documentUuid,
                'deleted_at' => now()->toISOString(),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid('', true),
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}