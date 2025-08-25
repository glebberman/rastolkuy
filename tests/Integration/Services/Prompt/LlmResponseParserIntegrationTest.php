<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Prompt;

use App\Services\Prompt\DTOs\LlmParsingRequest;
use App\Services\Prompt\LlmResponseParser;
use App\Services\Prompt\MetadataExtractorManager;
use RuntimeException;
use Tests\TestCase;

class LlmResponseParserIntegrationTest extends TestCase
{
    private LlmResponseParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $metadataExtractorManager = new MetadataExtractorManager();
        $this->parser = new LlmResponseParser($metadataExtractorManager);
    }

    public function testCanParseRealTranslationResponse(): void
    {
        // Реальный пример ответа от Claude для перевода
        $realClaudeResponse = $this->safeJsonEncode([
            'simplified_text' => 'Этот договор устанавливает правила работы между компанией и сотрудником. Основные моменты: зарплата 50 000 рублей в месяц, рабочий день с 9 до 18, отпуск 28 дней в году.',
            'translation_quality' => [
                'clarity_score' => 0.92,
                'completeness_score' => 0.88,
                'readability_level' => 'beginner',
            ],
            'legal_terms_preserved' => [
                [
                    'term' => 'трудовой договор',
                    'explanation' => 'официальное соглашение между работником и работодателем',
                    'context' => 'определяет права и обязанности сторон',
                ],
                [
                    'term' => 'должностные обязанности',
                    'explanation' => 'что конкретно должен делать сотрудник на работе',
                ],
            ],
            'section_translations' => [
                [
                    'anchor' => 'SECTION_ANCHOR_predmet_dogovora_abc123',
                    'section_title' => 'Предмет договора',
                    'translated_content' => 'Компания принимает сотрудника на должность разработчика программного обеспечения.',
                    'summary' => 'Определяется должность и обязанности работника',
                ],
                [
                    'anchor' => 'SECTION_ANCHOR_oplata_truda_def456',
                    'section_title' => 'Оплата труда',
                    'translated_content' => 'Зарплата составляет 50 000 рублей в месяц, выплачивается до 10 числа каждого месяца.',
                    'summary' => 'Размер и порядок выплаты заработной платы',
                ],
            ],
            'key_concepts' => [
                [
                    'concept' => 'Испытательный срок',
                    'description' => 'Период, в течение которого работодатель может уволить сотрудника без объяснения причин',
                    'importance' => 'high',
                ],
                [
                    'concept' => 'Материальная ответственность',
                    'description' => 'Обязанность возместить ущерб, причиненный работодателю',
                    'importance' => 'medium',
                ],
            ],
            'warnings' => [
                'В договоре не указаны условия досрочного расторжения',
                'Отсутствует информация о социальных гарантиях',
            ],
            'metadata' => [
                'original_length' => 2500,
                'simplified_length' => 800,
                'complexity_reduction' => 0.68,
            ],
        ]);

        $originalAnchors = [
            'SECTION_ANCHOR_predmet_dogovora_abc123',
            'SECTION_ANCHOR_oplata_truda_def456',
        ];

        $request = LlmParsingRequest::forTranslation($realClaudeResponse, $originalAnchors);
        $result = $this->parser->parse($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('translation', $result->schemaType);
        $this->assertEquals(2, $result->getValidAnchorCount());
        $this->assertEquals(0, $result->getInvalidAnchorCount());

        // Проверяем извлеченные метаданные
        $this->assertArrayHasKey('translation_quality', $result->metadata);
        $this->assertEquals(0.9, $result->metadata['translation_quality']['overall_score']);

        $this->assertArrayHasKey('sections_count', $result->metadata);
        $this->assertEquals(2, $result->metadata['sections_count']['total_sections']);

        $this->assertArrayHasKey('terms_preserved', $result->metadata);
        $this->assertEquals(2, $result->metadata['terms_preserved']['total_terms']);

        // Проверяем данные
        $this->assertStringContainsString('50 000 рублей', $result->parsedData['simplified_text']);
        $this->assertCount(2, $result->parsedData['section_translations']);
        $this->assertCount(2, $result->parsedData['key_concepts']);
    }

    public function testCanHandleClaudeResponseWithMarkdownAndExtraText(): void
    {
        // Claude иногда добавляет объяснения до или после JSON
        $claudeResponseWithExtraText = "Вот анализ документа в формате JSON:\n\n```json\n" .
            $this->safeJsonEncode([
                'simplified_text' => 'Простой анализ документа',
                'translation_quality' => [
                    'clarity_score' => 0.8,
                    'completeness_score' => 0.9,
                ],
                'legal_terms_preserved' => [],
                'section_translations' => [
                    [
                        'anchor' => 'test_anchor',
                        'section_title' => 'Тест',
                        'translated_content' => 'Тестовый контент',
                    ],
                ],
            ]) .
            "\n```\n\nЭтот анализ основан на предоставленном документе.";

        $request = LlmParsingRequest::forTranslation($claudeResponseWithExtraText, ['test_anchor']);
        $result = $this->parser->parse($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('Простой анализ документа', $result->parsedData['simplified_text']);
    }

    public function testCanHandleIncompleteClaudeResponse(): void
    {
        // Симуляция прерванного ответа Claude
        $incompleteResponse = $this->safeJsonEncode([
            'simplified_text' => 'Начало анализа документа',
            'translation_quality' => [
                'clarity_score' => 0.7,
                // completeness_score отсутствует
            ],
            'legal_terms_preserved' => [
                [
                    'term' => 'договор',
                    // explanation отсутствует
                ],
            ],
            'section_translations' => [
                [
                    'anchor' => 'section_1',
                    'section_title' => 'Раздел 1',
                    // translated_content отсутствует - нарушение схемы
                ],
            ],
        ]);

        // Пытаемся парсить со строгой валидацией
        $schema = [
            'required' => ['simplified_text', 'translation_quality', 'section_translations'],
            'properties' => [
                'section_translations' => [
                    'type' => 'array',
                    'items' => [
                        'required' => ['anchor', 'section_title', 'translated_content'],
                    ],
                ],
            ],
        ];

        $request = new LlmParsingRequest(
            rawResponse: $incompleteResponse,
            expectedSchema: $schema,
            schemaType: 'translation',
            originalAnchors: ['section_1'],
            validationRules: ['anchors_required'],
            strictValidation: true,
        );

        // Основной парсинг должен упасть из-за отсутствующих полей
        $primaryResult = $this->parser->parse($request);
        $this->assertFalse($primaryResult->isSuccessful());

        // Fallback парсинг должен сработать
        $fallbackResult = $this->parser->parseWithFallback($request);
        $this->assertTrue($fallbackResult->isValid);
        $this->assertContains('Used fallback parsing due to primary parsing failure', $fallbackResult->warnings);
    }

    public function testCanParseMultipleResponseScenarios(): void
    {
        $scenarios = [
            'minimal_valid' => [
                'response' => $this->safeJsonEncode([
                    'simplified_text' => 'Минимальный ответ',
                    'translation_quality' => ['clarity_score' => 0.5, 'completeness_score' => 0.5],
                    'legal_terms_preserved' => [],
                    'section_translations' => [],
                ]),
                'should_succeed' => true,
            ],
            'with_extra_fields' => [
                'response' => $this->safeJsonEncode([
                    'simplified_text' => 'Ответ с дополнительными полями',
                    'translation_quality' => ['clarity_score' => 0.8, 'completeness_score' => 0.7],
                    'legal_terms_preserved' => [],
                    'section_translations' => [],
                    'extra_field' => 'Дополнительное поле',
                    'custom_metadata' => ['key' => 'value'],
                ]),
                'should_succeed' => true,
            ],
            'with_cyrillic_content' => [
                'response' => $this->safeJsonEncode([
                    'simplified_text' => 'Текст с кириллицей: договор, обязательства, ответственность',
                    'translation_quality' => ['clarity_score' => 0.9, 'completeness_score' => 0.8],
                    'legal_terms_preserved' => [
                        [
                            'term' => 'договор',
                            'explanation' => 'соглашение между сторонами',
                        ],
                    ],
                    'section_translations' => [
                        [
                            'anchor' => 'раздел_1',
                            'section_title' => 'Предмет договора',
                            'translated_content' => 'Описание предмета договора простыми словами',
                        ],
                    ],
                ]),
                'should_succeed' => true,
            ],
            'invalid_structure' => [
                'response' => $this->safeJsonEncode([
                    'invalid_field' => 'This does not match translation schema',
                ]),
                'should_succeed' => false,
            ],
        ];

        foreach ($scenarios as $scenarioName => $scenario) {
            $anchors = $scenarioName === 'with_cyrillic_content' ? ['раздел_1'] : [];
            $request = LlmParsingRequest::forTranslation($scenario['response'], $anchors);

            $result = $this->parser->parse($request);

            $shouldSucceed = $scenario['should_succeed'];

            if ($shouldSucceed) {
                $this->assertTrue($result->isSuccessful(), "Scenario '{$scenarioName}' should succeed");
            } else {
                $this->assertFalse($result->isSuccessful(), "Scenario '{$scenarioName}' should fail");
            }
        }
    }

    public function testPerformanceWithLargeResponse(): void
    {
        // Генерируем большой ответ для тестирования производительности
        $largeSections = [];
        $largeAnchors = [];

        for ($i = 1; $i <= 50; ++$i) {
            $anchor = "section_anchor_{$i}_" . md5((string) $i);
            $largeAnchors[] = $anchor;
            $largeSections[] = [
                'anchor' => $anchor,
                'section_title' => "Раздел номер {$i}",
                'translated_content' => str_repeat("Содержимое раздела {$i}. ", 20),
                'summary' => "Краткое содержание раздела {$i}",
            ];
        }

        $largeTerms = [];

        for ($i = 1; $i <= 20; ++$i) {
            $largeTerms[] = [
                'term' => "термин_{$i}",
                'explanation' => "Объяснение термина {$i}",
                'context' => "Контекст использования термина {$i}",
            ];
        }

        $largeResponse = $this->safeJsonEncode([
            'simplified_text' => str_repeat('Большой упрощенный текст. ', 100),
            'translation_quality' => [
                'clarity_score' => 0.85,
                'completeness_score' => 0.9,
                'readability_level' => 'intermediate',
            ],
            'legal_terms_preserved' => $largeTerms,
            'section_translations' => $largeSections,
            'key_concepts' => array_fill(0, 10, [
                'concept' => 'Важная концепция',
                'description' => 'Описание концепции',
                'importance' => 'high',
            ]),
        ]);

        $startTime = microtime(true);

        $request = LlmParsingRequest::forTranslation($largeResponse, $largeAnchors);
        $result = $this->parser->parse($request);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // в миллисекундах

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(50, $result->getValidAnchorCount());
        $this->assertLessThan(1000, $executionTime, 'Parsing should complete within 1 second');

        // Проверяем что метаданные корректно извлечены для большого ответа
        $this->assertEquals(50, $result->metadata['sections_count']['total_sections']);
        $this->assertEquals(20, $result->metadata['terms_preserved']['total_terms']);
    }

    private function safeJsonEncode(array $data): string
    {
        $result = json_encode($data);

        if ($result === false) {
            throw new RuntimeException('JSON encoding failed');
        }

        return $result;
    }
}
