<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Базовый ответ для операций с документами.
 */
abstract class BaseDocumentResponse extends JsonResponse
{
    public function __construct(
        JsonResource $resource,
        string $message,
        int $status = Response::HTTP_OK,
        ?array $meta = null
    ) {
        $data = [
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data' => $resource->resolve(),
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid('', true),
            ], $meta ?? []),
        ];

        parent::__construct($data, $status);
    }
}