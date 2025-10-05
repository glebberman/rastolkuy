<?php

declare(strict_types=1);

namespace App\Services\LLM\Adapters;

use App\Services\LLM\Contracts\LLMAdapterInterface;
use App\Services\LLM\DTOs\LLMRequest;
use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Exceptions\LLMException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fake LLM adapter for development and testing.
 *
 * Returns realistic fake responses based on predefined templates
 * instead of making real API calls to Claude.
 */
final class FakeAdapter implements LLMAdapterInterface
{
    private const FAKE_RESPONSES = [
        'contract' => [
            'content' => '## 1. ПРЕДМЕТ ДОГОВОРА

Заказчик поручает, а Исполнитель принимает на себя обязательства по разработке корпоративного веб-сайта.

**[Переведено]:** Простыми словами: Программист будет делать сайт по вашему описанию, а вы обязуетесь его принять и заплатить за работу.

**[Найден риск]:** Нет четкого описания технического задания. Если ТЗ будет неполным или изменится в процессе работы, это может привести к конфликтам и доплатам.

<!-- SECTION_ANCHOR_section_1_predmet_dogovora -->

## 2. СТОИМОСТЬ И УСЛОВИЯ ОПЛАТЫ

Общая стоимость выполнения работ составляет 150 000 (сто пятьдесят тысяч) рублей.

Оплата производится в следующем порядке:
- 50% (75 000 рублей) - предоплата в течение 5 банковских дней
- 50% (75 000 рублей) - после подписания акта сдачи-приемки работ

**[Переведено]:** Простыми словами: Весь проект стоит 150 000 рублей. Половину нужно заплатить вперед в течение 5 дней, вторую половину - после завершения работы.

<!-- SECTION_ANCHOR_section_2_stoimost_oplata -->',
            'anchors' => [
                [
                    'id' => 'section_1_predmet_dogovora',
                    'title' => '1. ПРЕДМЕТ ДОГОВОРА',
                    'translation' => 'Простыми словами: Программист будет делать сайт по вашему описанию, а вы обязуетесь его принять и заплатить за работу.',
                ],
                [
                    'id' => 'section_2_stoimost_oplata',
                    'title' => '2. СТОИМОСТЬ И УСЛОВИЯ ОПЛАТЫ',
                    'translation' => 'Простыми словами: Весь проект стоит 150 000 рублей. Половину нужно заплатить вперед в течение 5 дней, вторую половину - после завершения работы.',
                ],
            ],
            'risks' => [
                [
                    'type' => 'risk',
                    'text' => 'Нет четкого описания технического задания. Если ТЗ будет неполным или изменится в процессе работы, это может привести к конфликтам и доплатам.',
                ],
                [
                    'type' => 'warning',
                    'text' => 'Отсутствует указание на ответственность за просрочку платежей и выполнения работ.',
                ],
            ],
        ],
        'employment' => [
            'content' => '## 1. РАБОТОДАТЕЛЬ И РАБОТНИК

Работодатель: ООО "ТехноСтар", в лице генерального директора Иванова П.С.
Работник: Сидоров Алексей Владимирович

**[Переведено]:** Простыми словами: Компания ТехноСтар нанимает Сидорова Алексея на работу.

<!-- SECTION_ANCHOR_section_1_storony -->

## 2. ДОЛЖНОСТЬ И ОБЯЗАННОСТИ

Работник принимается на должность "Ведущий разработчик ПО" с окладом 250 000 рублей в месяц.

**[Переведено]:** Простыми словами: Будете работать главным программистом за 250 тысяч рублей в месяц.

**[Найден риск]:** Нет конкретного описания рабочих обязанностей, что может привести к переработкам или конфликтам с руководством.

<!-- SECTION_ANCHOR_section_2_dolzhnost -->',
            'anchors' => [
                [
                    'id' => 'section_1_storony',
                    'title' => '1. РАБОТОДАТЕЛЬ И РАБОТНИК',
                    'translation' => 'Простыми словами: Компания ТехноСтар нанимает Сидорова Алексея на работу.',
                ],
                [
                    'id' => 'section_2_dolzhnost',
                    'title' => '2. ДОЛЖНОСТЬ И ОБЯЗАННОСТИ',
                    'translation' => 'Простыми словами: Будете работать главным программистом за 250 тысяч рублей в месяц.',
                ],
            ],
            'risks' => [
                [
                    'type' => 'risk',
                    'text' => 'Нет конкретного описания рабочих обязанностей, что может привести к переработкам или конфликтам с руководством.',
                ],
                [
                    'type' => 'warning',
                    'text' => 'Отсутствует информация о социальных гарантиях и компенсациях.',
                ],
            ],
        ],
        'lease' => [
            'content' => '## 1. СТОРОНЫ ДОГОВОРА

Арендодатель: Петров Иван Михайлович
Арендатор: Козлова Мария Александровна

**[Переведено]:** Простыми словами: Петров сдает квартиру Козловой в аренду.

<!-- SECTION_ANCHOR_section_1_storony -->

## 2. ПРЕДМЕТ АРЕНДЫ

Сдается однокомнатная квартира площадью 42 кв.м за 85 000 рублей в месяц с депозитом 170 000 рублей.

**[Переведено]:** Простыми словами: Снимаете квартиру за 85 тысяч в месяц, плюс залог 170 тысяч при заселении.

**[Найден риск]:** Высокий размер депозита (2 месячных платежа) создает большую финансовую нагрузку.

<!-- SECTION_ANCHOR_section_2_predmet -->',
            'anchors' => [
                [
                    'id' => 'section_1_storony',
                    'title' => '1. СТОРОНЫ ДОГОВОРА',
                    'translation' => 'Простыми словами: Петров сдает квартиру Козловой в аренду.',
                ],
                [
                    'id' => 'section_2_predmet',
                    'title' => '2. ПРЕДМЕТ АРЕНДЫ',
                    'translation' => 'Простыми словами: Снимаете квартиру за 85 тысяч в месяц, плюс залог 170 тысяч при заселении.',
                ],
            ],
            'risks' => [
                [
                    'type' => 'risk',
                    'text' => 'Высокий размер депозита (2 месячных платежа) создает большую финансовую нагрузку.',
                ],
                [
                    'type' => 'warning',
                    'text' => 'Нет четких условий возврата депозита и критериев нормального износа.',
                ],
            ],
        ],
    ];

    private float $baseDelay;

    private bool $shouldSimulateErrors;

    public function __construct(
        float $baseDelay = 0.5,
        bool $shouldSimulateErrors = false,
    ) {
        $this->baseDelay = $baseDelay;
        $this->shouldSimulateErrors = $shouldSimulateErrors;
    }

    public function execute(LLMRequest $request): LLMResponse
    {
        Log::info('FakeAdapter: Executing fake LLM request', [
            'content_length' => mb_strlen($request->content),
            'model' => $request->model ?? 'fake-claude',
            'delay' => $this->baseDelay,
        ]);

        // Simulate processing delay
        if ($this->baseDelay > 0) {
            usleep((int) ($this->baseDelay * 1000000));
        }

        // Simulate random errors for testing
        if ($this->shouldSimulateErrors && random_int(1, 10) === 1) {
            throw new LLMException(
                message: 'Fake LLM error for testing',
                code: 500,
                previous: null,
                context: [
                    'fake_error' => true,
                    'request_content_length' => mb_strlen($request->content),
                ],
            );
        }

        $startTime = microtime(true);

        // Determine document type from content and extract anchors
        $documentType = $this->detectDocumentType($request->content);
        $anchors = $this->extractAnchorsFromContent($request->content);

        // Generate fake response in Claude's JSON format
        $responseData = $this->generateFakeJsonResponse($documentType, $anchors);
        $jsonResponse = json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($jsonResponse === false) {
            throw new RuntimeException('Failed to encode fake response to JSON');
        }

        $executionTime = microtime(true) - $startTime;

        // Create realistic response - returning JSON like Claude does
        return new LLMResponse(
            content: $jsonResponse,
            model: $request->model ?? 'fake-claude-3-5-sonnet',
            inputTokens: $this->countTokens($request->content, ''),
            outputTokens: $this->countTokens($jsonResponse, ''),
            costUsd: $this->calculateCost(
                $this->countTokens($request->content, ''),
                $this->countTokens($jsonResponse, ''),
                '',
            ),
            executionTimeMs: $executionTime * 1000,
            metadata: [
                'fake_adapter' => true,
                'document_type' => $documentType,
                'anchors_count' => count($anchors),
                'base_delay' => $this->baseDelay,
                'timestamp' => now()->toISOString(),
            ],
        );
    }

    public function executeBatch(array $requests): array
    {
        Log::info('FakeAdapter: Executing batch fake LLM requests', [
            'count' => count($requests),
            'total_delay' => $this->baseDelay * count($requests),
        ]);

        $responses = [];

        foreach ($requests as $request) {
            $responses[] = $this->execute($request);
        }

        return $responses;
    }

    public function validateConnection(): bool
    {
        Log::info('FakeAdapter: Validating fake connection');

        // Simulate connection validation delay
        usleep(100000); // 0.1 second

        return true;
    }

    public function getProviderName(): string
    {
        return 'fake';
    }

    public function getSupportedModels(): array
    {
        return [
            'fake-claude-3-5-sonnet',
            'fake-claude-3-5-haiku',
            'fake-claude-sonnet-4',
        ];
    }

    public function calculateCost(int $inputTokens, int $outputTokens, string $model): float
    {
        // Fake pricing similar to Claude's actual pricing but lower
        $inputCostPer1M = 0.10; // $0.10 per 1M tokens (vs Claude's $3.00)
        $outputCostPer1M = 0.50; // $0.50 per 1M tokens (vs Claude's $15.00)

        $inputCost = ($inputTokens / 1000000) * $inputCostPer1M;
        $outputCost = ($outputTokens / 1000000) * $outputCostPer1M;

        return round($inputCost + $outputCost, 6);
    }

    public function countTokens(string $text, string $model): int
    {
        // Rough approximation: ~4 characters per token for Russian text
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Detect document type from content.
     */
    private function detectDocumentType(string $content): string
    {
        $content = mb_strtolower($content);

        if (str_contains($content, 'работник') || str_contains($content, 'работодатель') || str_contains($content, 'трудов')) {
            return 'employment';
        }

        if (str_contains($content, 'аренд') || str_contains($content, 'наем') || str_contains($content, 'квартир')) {
            return 'lease';
        }

        if (str_contains($content, 'договор') || str_contains($content, 'соглашение') || str_contains($content, 'контракт')) {
            return 'contract';
        }

        return 'contract'; // default
    }

    /**
     * Extract anchors from document content.
     *
     * @return array<string>
     */
    private function extractAnchorsFromContent(string $content): array
    {
        $anchors = [];
        $anchorPattern = '/<!-- SECTION_ANCHOR_([^>]+) -->/';

        $result = preg_match_all($anchorPattern, $content, $matches);

        if ($result === false || $result === 0) {
            return [];
        }

        return array_values($matches[1]);
    }

    /**
     * Generate fake JSON response in Claude's format.
     *
     * @param array<string> $anchors
     *
     * @return array<string, mixed>
     */
    private function generateFakeJsonResponse(string $documentType, array $anchors): array
    {
        $template = self::FAKE_RESPONSES[$documentType] ?? self::FAKE_RESPONSES['contract'];

        // If no anchors found in document, return simple response
        if (empty($anchors)) {
            return [
                'sections' => [
                    [
                        'anchor' => 'main',
                        'content' => $template['content'],
                        'type' => 'translation',
                    ],
                ],
            ];
        }

        // Generate sections for each anchor
        $sections = [];

        foreach ($anchors as $index => $anchorId) {
            // Use template data if available, otherwise generate generic content
            $anchorContent = $template['anchors'][$index] ?? null;

            if ($anchorContent !== null) {
                $translationText = $anchorContent['translation'];
            } else {
                $translationText = 'Простыми словами: это стандартный пункт договора, который определяет условия взаимодействия сторон.';
            }

            // Добавляем риск к переводу если он есть для этой секции
            $risk = $template['risks'][$index] ?? null;
            if ($risk !== null && is_array($risk)) {
                $riskLabel = match ($risk['type']) {
                    'risk' => '**[Найден риск]:**',
                    'warning' => '**[Внимание]:**',
                    default => '**[Примечание]:**',
                };
                $translationText .= "\n\n{$riskLabel} {$risk['text']}";
            }

            $sections[] = [
                'anchor' => $anchorId,
                'content' => $translationText,
                'type' => 'translation',
            ];
        }

        return [
            'sections' => $sections,
        ];
    }
}
