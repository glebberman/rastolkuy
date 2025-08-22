<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\SectionDetector;
use ReflectionClass;
use Tests\TestCase;

class SectionDetectorPatternTest extends TestCase
{
    public function testHandlesNestedPatternArrays(): void
    {
        // Конфигурация с вложенными массивами паттернов (как в некоторых тестах)
        config(['structure_analysis' => [
            'confidence_levels' => [
                'high' => 0.9,
                'medium' => 0.7,
                'low' => 0.5,
            ],
            'detection' => [
                'min_section_length' => 5,
                'max_title_length' => 200,
            ],
            'section_patterns' => [
                'numbered' => ['/^(\d+\.?\s*)(.*?)$/um'],
                'subsections' => [
                    '/^(\d+\.\d+\.?\s*)(.*?)$/um',
                    '/^(\d+\.\d+\.\d+\.?\s*)(.*?)$/um', // Вложенный массив
                ],
                'named' => ['/^(Введение\.?\s*)(.*?)$/ium'],
            ],
            'legal_keywords' => [
                'contract_terms' => ['договор', 'контракт'],
                'legal_entities' => ['заказчик', 'исполнитель'],
                'actions' => [['поставить', 'выполнить'], 'оказать'], // Вложенный массив для тестирования
            ],
            'anchor_generation' => [
                'prefix' => '<!-- SECTION_ANCHOR_',
                'suffix' => ' -->',
                'max_title_length' => 50,
                'transliteration' => true,
                'normalize_case' => true,
            ],
        ]]);

        $detector = new SectionDetector(new AnchorGenerator());

        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [
                new HeaderElement('1. Первый раздел', 1),
                new ParagraphElement('Содержание первого раздела'),
                new HeaderElement('1.1 Подраздел', 2),
                new ParagraphElement('Содержание подраздела'),
                new HeaderElement('1.1.1 Подподраздел', 3),
                new ParagraphElement('Содержание подподраздела'),
            ],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );

        // Это не должно выбрасывать исключение о типе параметра
        $sections = $detector->detectSections($document);

        // Проверяем, что все секции корректно обнаружены
        $this->assertCount(3, $sections);
        $this->assertEquals('1. Первый раздел', $sections[0]->title);
        $this->assertEquals('1.1 Подраздел', $sections[1]->title);
        $this->assertEquals('1.1.1 Подподраздел', $sections[2]->title);

        // Проверяем уровни
        $this->assertEquals(1, $sections[0]->level);
        $this->assertEquals(2, $sections[1]->level);
        $this->assertEquals(3, $sections[2]->level);
    }

    public function testFlattensPatternsCorrectly(): void
    {
        // Создаем простой детектор для тестирования
        $detector = new SectionDetector(new AnchorGenerator());

        // Используем рефлексию чтобы протестировать private метод
        $reflection = new ReflectionClass($detector);
        $method = $reflection->getMethod('flattenPatterns');
        $method->setAccessible(true);  // setAccessible returns void

        // Тестируем различные сценарии
        $result1 = $method->invoke($detector, ['pattern1', 'pattern2']);
        $this->assertEquals(['pattern1', 'pattern2'], $result1);

        $result2 = $method->invoke($detector, ['pattern1', ['pattern2', 'pattern3'], 'pattern4']);
        $this->assertEquals(['pattern1', 'pattern2', 'pattern3', 'pattern4'], $result2);

        $result3 = $method->invoke($detector, [['nested1', ['nested2']], 'top']);
        $this->assertEquals(['nested1', 'nested2', 'top'], $result3);

        // Проверяем что нестроковые элементы игнорируются
        $result4 = $method->invoke($detector, ['pattern1', 123, null, 'pattern2']);
        $this->assertEquals(['pattern1', 'pattern2'], $result4);
    }
}
