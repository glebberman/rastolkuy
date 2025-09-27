<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ответ на запрос предварительного просмотра промпта.
 */
final class PromptPreviewResponse extends JsonResponse
{
    /**
     * @param array<string, mixed> $promptData
     */
    public function __construct(array $promptData)
    {
        $data = [
            'success' => true,
            'message' => 'Предварительный просмотр промпта сгенерирован',
            'data' => [
                'prompt' => $promptData,
                'characters_count' => strlen($promptData['rendered_prompt'] ?? ''),
                'estimated_tokens' => $this->estimateTokens($promptData['rendered_prompt'] ?? ''),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid(),
                'note' => 'Промпт сгенерирован для предварительного просмотра, не отправлен в LLM',
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }

    /**
     * Примерная оценка количества токенов.
     */
    private function estimateTokens(string $text): int
    {
        // Приблизительная оценка: 1 токен = ~4 символа для русского текста
        return (int) ceil(strlen($text) / 4);
    }
}