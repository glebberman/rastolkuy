<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\SectionDetector;
use App\Services\Structure\StructureAnalyzer;
use Tests\TestCase;

class StructureAnalyzerHierarchyTest extends TestCase
{
    private StructureAnalyzer $analyzer;

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
                'min_confidence_threshold' => 0.1,
                'min_section_length' => 5,
                'max_title_length' => 200,
                'max_analysis_time_seconds' => 30,
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
            'anchor_generation' => [
                'prefix' => '<!-- SECTION_ANCHOR_',
                'suffix' => ' -->',
                'max_title_length' => 50,
                'transliteration' => true,
                'normalize_case' => true,
            ],
        ]]);

        $this->analyzer = new StructureAnalyzer(
            new SectionDetector(new AnchorGenerator()),
            new AnchorGenerator()
        );
    }

    public function testBuildsHierarchyCorrectly(): void
    {
        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [
                new HeaderElement('1. Основные положения', 1),
                new ParagraphElement('Содержание раздела 1'),
                new HeaderElement('1.1 Подраздел А', 2),
                new ParagraphElement('Содержание подраздела 1.1'),
                new HeaderElement('1.2 Подраздел Б', 2),
                new ParagraphElement('Содержание подраздела 1.2'),
                new HeaderElement('2. Другой раздел', 1),
                new ParagraphElement('Содержание раздела 2'),
            ],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1
        );

        $result = $this->analyzer->analyze($document);
        $sections = $result->sections;


        // Должно быть 2 корневых секции
        $this->assertCount(2, $sections);

        // Первая секция должна иметь подсекции
        $firstSection = $sections[0];
        $this->assertTrue($firstSection->hasSubsections());
        $this->assertCount(2, $firstSection->subsections);

        // Вторая секция не должна иметь подсекции
        $secondSection = $sections[1];
        $this->assertFalse($secondSection->hasSubsections());
        $this->assertCount(0, $secondSection->subsections);

        // Проверяем уровни
        $this->assertEquals(1, $firstSection->level);
        $this->assertEquals(2, $firstSection->subsections[0]->level);
        $this->assertEquals(2, $firstSection->subsections[1]->level);
        $this->assertEquals(1, $secondSection->level);
    }

    public function testHandlesMultipleLevelsHierarchy(): void
    {
        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [
                new HeaderElement('1. Уровень 1', 1),
                new ParagraphElement('Содержание уровня 1'),
                new HeaderElement('1.1 Уровень 2', 2),
                new ParagraphElement('Содержание уровня 2'),
                new HeaderElement('1.2 Другой уровень 2', 2),
                new ParagraphElement('Содержание другого уровня 2'),
            ],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1
        );

        $result = $this->analyzer->analyze($document);
        $sections = $result->sections;

        // Должна быть 1 корневая секция
        $this->assertCount(1, $sections);

        $rootSection = $sections[0];
        $this->assertEquals(1, $rootSection->level);
        $this->assertCount(2, $rootSection->subsections);

        // Первая подсекция второго уровня
        $firstSubsection = $rootSection->subsections[0];
        $this->assertEquals(2, $firstSubsection->level);
        $this->assertCount(0, $firstSubsection->subsections);

        // Вторая подсекция второго уровня
        $secondSubsection = $rootSection->subsections[1];
        $this->assertEquals(2, $secondSubsection->level);
        $this->assertCount(0, $secondSubsection->subsections);
    }

    public function testHandlesEmptyInput(): void
    {
        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1
        );

        $result = $this->analyzer->analyze($document);

        $this->assertCount(0, $result->sections);
        $this->assertEquals(0.0, $result->averageConfidence);
    }
}