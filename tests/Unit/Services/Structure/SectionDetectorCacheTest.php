<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\SectionDetector;
use Tests\TestCase;

class SectionDetectorCacheTest extends TestCase
{
    private SectionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        // Мокаем конфигурацию
        config(['structure_analysis' => [
            'confidence_levels' => [
                'high' => 0.9,
                'medium' => 0.7,
                'low' => 0.5,
            ],
            'detection' => [
                'min_section_length' => 50,
                'max_title_length' => 200,
            ],
            'section_patterns' => [
                'numbered' => ['/^(\d+\.?\s*)(.*?)$/um'],
                'subsections' => ['/^(\d+\.\d+\.?\s*)(.*?)$/um'],
                'named' => ['/^(Введение\.?\s*)(.*?)$/ium'],
            ],
            'legal_keywords' => [
                'contract_terms' => ['договор', 'соглашение'],
                'legal_entities' => ['заказчик', 'исполнитель'],
                'actions' => ['поставить', 'выполнить'],
            ],
        ]]);

        $this->detector = new SectionDetector(new AnchorGenerator());
    }

    public function testCacheWorksForPatternMatching(): void
    {
        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [
                new ParagraphElement('1. Общие положения'),
                new ParagraphElement('1. Общие положения'), // Тот же контент - должен использовать кэш
                new ParagraphElement('2. Предмет договора'),
            ],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );

        // Очищаем кэш перед тестом
        $this->detector->clearPatternCache();

        // Первый анализ - должен заполнить кэш
        $sections1 = $this->detector->detectSections($document);
        $stats1 = $this->detector->getCacheStats();

        // Второй анализ того же документа - должен использовать кэш
        $sections2 = $this->detector->detectSections($document);
        $stats2 = $this->detector->getCacheStats();

        // Результаты должны быть одинаковыми
        $this->assertEquals($sections1, $sections2);

        // Кэш должен расти между вызовами
        $this->assertGreaterThanOrEqual($stats1['cache_size'], $stats2['cache_size']);
        $this->assertGreaterThan(0, $stats2['cache_size']);
    }

    public function testClearCacheWorks(): void
    {
        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [
                new ParagraphElement('1. Общие положения'),
            ],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );

        // Анализируем документ для заполнения кэша
        $this->detector->detectSections($document);
        $statsBeforeClear = $this->detector->getCacheStats();

        // Очищаем кэш
        $this->detector->clearPatternCache();
        $statsAfterClear = $this->detector->getCacheStats();

        // Кэш должен быть очищен
        $this->assertGreaterThan(0, $statsBeforeClear['cache_size']);
        $this->assertEquals(0, $statsAfterClear['cache_size']);
    }

    public function testCacheStatsReturnCorrectData(): void
    {
        $stats = $this->detector->getCacheStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cache_size', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
        $this->assertIsInt($stats['cache_size']);
        $this->assertIsInt($stats['memory_usage']);
    }
}
