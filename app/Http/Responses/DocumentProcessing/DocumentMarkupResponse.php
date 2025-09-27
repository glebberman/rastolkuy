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
                'sections_count' => $this->getCountableSafeCount($markup, 'sections'),
                'anchors_count' => $this->getCountableSafeCount($markup, 'anchors'),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid(),
                'note' => 'Разметка создана без обращения к LLM',
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }

    /**
     * Безопасно получает количество элементов из массива.
     *
     * @param mixed $markup
     * @param string $key
     * @return int
     */
    private function getCountableSafeCount(mixed $markup, string $key): int
    {
        if (!is_array($markup) || !isset($markup[$key])) {
            return 0;
        }

        $value = $markup[$key];

        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }

        return 0;
    }
}