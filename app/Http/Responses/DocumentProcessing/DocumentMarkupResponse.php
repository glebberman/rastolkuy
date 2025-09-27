<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ответ на запрос разметки документа якорями.
 */
final class DocumentMarkupResponse extends JsonResponse
{
    /**
     * @param array<string, mixed> $markup
     */
    public function __construct(array $markup)
    {
        $data = [
            'success' => true,
            'message' => 'Разметка документа получена',
            'data' => [
                'markup' => $markup,
                'sections_count' => count($markup['sections'] ?? []),
                'anchors_count' => count($markup['anchors'] ?? []),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid(),
                'note' => 'Разметка создана без обращения к LLM',
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}