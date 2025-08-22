<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\StructureAnalyzer;
use Tests\TestCase;

class StructureAnalysisIntegrationTest extends TestCase
{
    public function testCompleteStructureAnalysisWorkflow(): void
    {
        // Создаем реалистичный документ
        $document = new ExtractedDocument(
            originalPath: '/test/legal-document.txt',
            mimeType: 'text/plain',
            elements: [
                new HeaderElement('ДОГОВОР АРЕНДЫ', 1),
                new ParagraphElement('Настоящий договор заключен между сторонами с целью аренды недвижимого имущества.'),

                new HeaderElement('1. Общие положения', 1),
                new ParagraphElement('В данном разделе устанавливаются основные принципы сотрудничества между сторонами.'),

                new HeaderElement('1.1 Предмет договора', 2),
                new ParagraphElement('Арендодатель предоставляет арендатору во временное пользование помещение площадью 100 кв.м.'),

                new HeaderElement('1.2 Права и обязанности сторон', 2),
                new ParagraphElement('Арендатор обязуется своевременно вносить арендную плату и поддерживать помещение в надлежащем состоянии.'),

                new HeaderElement('2. Финансовые условия', 1),
                new ParagraphElement('Размер арендной платы составляет 50000 рублей в месяц.'),

                new HeaderElement('3. Ответственность сторон', 1),
                new ParagraphElement('За нарушение условий договора стороны несут ответственность в соответствии с законодательством РФ.'),

                new HeaderElement('4. Заключительные положения', 1),
                new ParagraphElement('Договор вступает в силу с момента подписания и действует в течение одного года.'),
            ],
            metadata: [
                'document_type' => 'contract',
                'language' => 'ru',
            ],
            totalPages: 2,
            extractionTime: 0.5,
        );

        // Получаем анализатор из контейнера
        $analyzer = $this->app->make(StructureAnalyzer::class);

        // Анализируем документ
        $result = $analyzer->analyze($document);

        // Проверяем основные метрики
        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result->getSectionsCount());
        $this->assertGreaterThan(0, $result->averageConfidence);
        $this->assertGreaterThan(0, $result->analysisTime);

        // Проверяем структуру
        $sections = $result->sections;
        $this->assertNotEmpty($sections);

        // Проверяем что есть корневые секции
        $rootSectionsCount = 0;

        foreach ($sections as $section) {
            if ($section->level === 1) {
                ++$rootSectionsCount;
            }
        }
        $this->assertGreaterThanOrEqual(3, $rootSectionsCount); // Ожидаем минимум 3 основных раздела

        // Проверяем наличие иерархии
        $hasSubsections = false;

        foreach ($sections as $section) {
            if ($section->hasSubsections()) {
                $hasSubsections = true;
                break;
            }
        }
        $this->assertTrue($hasSubsections, 'Document should have hierarchical structure');

        // Проверяем якоря
        foreach ($sections as $section) {
            $this->assertNotEmpty($section->anchor);
            $this->assertStringStartsWith('<!-- SECTION_ANCHOR_', $section->anchor);
            $this->assertStringEndsWith(' -->', $section->anchor);
        }

        // Проверяем статистику
        $statistics = $result->statistics;
        $this->assertArrayHasKey('total_sections', $statistics);
        $this->assertArrayHasKey('sections_by_level', $statistics);
        $this->assertArrayHasKey('average_section_length', $statistics);
        $this->assertArrayHasKey('coverage_percentage', $statistics);

        // Проверяем метаданные
        $metadata = $result->metadata;
        $this->assertArrayHasKey('document_mime_type', $metadata);
        $this->assertArrayHasKey('total_elements', $metadata);
        $this->assertArrayHasKey('analyzer_version', $metadata);

        // Проверяем что нет критических предупреждений
        $warnings = $result->warnings;
        $criticalWarnings = array_filter($warnings, function ($warning) {
            return str_contains($warning, 'failed') || str_contains($warning, 'error');
        });
        $this->assertEmpty($criticalWarnings, 'Should not have critical warnings');
    }

    public function testBatchAnalysisWorkflow(): void
    {
        $documents = [
            'contract1' => new ExtractedDocument(
                originalPath: '/test/contract1.txt',
                mimeType: 'text/plain',
                elements: [
                    new HeaderElement('Договор поставки', 1),
                    new ParagraphElement('Содержание договора поставки товаров.'),
                ],
                metadata: [],
                totalPages: 1,
                extractionTime: 0.1,
            ),
            'contract2' => new ExtractedDocument(
                originalPath: '/test/contract2.txt',
                mimeType: 'text/plain',
                elements: [
                    new HeaderElement('Договор услуг', 1),
                    new ParagraphElement('Содержание договора оказания услуг.'),
                ],
                metadata: [],
                totalPages: 1,
                extractionTime: 0.1,
            ),
        ];

        $analyzer = $this->app->make(StructureAnalyzer::class);
        $results = $analyzer->analyzeBatch($documents);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('contract1', $results);
        $this->assertArrayHasKey('contract2', $results);

        foreach ($results as $result) {
            $this->assertNotNull($result);
            $this->assertGreaterThanOrEqual(0, $result->getSectionsCount());
            $this->assertGreaterThanOrEqual(0, $result->averageConfidence);
        }
    }

    public function testServiceProviderIntegration(): void
    {
        // Проверяем что все сервисы правильно зарегистрированы в контейнере
        $this->assertTrue($this->app->bound(StructureAnalyzer::class));
        $this->assertTrue($this->app->bound('structure.analyzer'));

        // Проверяем что они возвращают один и тот же instance (singleton)
        $analyzer1 = $this->app->make(StructureAnalyzer::class);
        $analyzer2 = $this->app->make('structure.analyzer');

        $this->assertSame($analyzer1, $analyzer2);
    }

    public function testPerformanceWithLargeDocument(): void
    {
        // Создаем большой документ
        $elements = [];

        for ($i = 1; $i <= 100; ++$i) {
            $elements[] = new HeaderElement("Раздел $i", 1);
            $elements[] = new ParagraphElement("Содержание раздела $i. " . str_repeat('Текст секции. ', 50));

            if ($i % 10 === 0) {
                $elements[] = new HeaderElement("$i.1 Подраздел", 2);
                $elements[] = new ParagraphElement('Содержание подраздела. ' . str_repeat('Дополнительный текст. ', 30));
            }
        }

        $document = new ExtractedDocument(
            originalPath: '/test/large-document.txt',
            mimeType: 'text/plain',
            elements: $elements,
            metadata: [],
            totalPages: 50,
            extractionTime: 2.0,
        );

        $analyzer = $this->app->make(StructureAnalyzer::class);

        $startTime = microtime(true);
        $result = $analyzer->analyze($document);
        $endTime = microtime(true);

        $analysisTime = $endTime - $startTime;

        // Проверяем что анализ завершился в разумное время (менее 5 секунд)
        $this->assertLessThan(5.0, $analysisTime, 'Analysis should complete within 5 seconds');

        // Проверяем что результат корректный
        $this->assertNotNull($result);
        $this->assertGreaterThan(50, $result->getSectionsCount());
        $this->assertGreaterThan(0, $result->averageConfidence);

        // Проверяем что нет предупреждений о превышении времени
        $timeWarnings = array_filter($result->warnings, function ($warning) {
            return str_contains($warning, 'time');
        });
        $this->assertEmpty($timeWarnings, 'Should not have time-related warnings');
    }
}
