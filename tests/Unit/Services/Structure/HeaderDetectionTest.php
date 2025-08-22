<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\SectionDetector;
use Tests\TestCase;

class HeaderDetectionTest extends TestCase
{
    private SectionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        // Простая конфигурация
        config(['structure_analysis' => [
            'confidence_levels' => [
                'high' => 0.9,
                'medium' => 0.7,
                'low' => 0.5,
            ],
            'detection' => [
                'min_section_length' => 1,
                'max_title_length' => 200,
            ],
            'section_patterns' => [
                'numbered' => ['/^(\d+\.?\s*)(.*?)$/um'],
                'subsections' => ['/^(\d+\.\d+\.?\s*)(.*?)$/um'],
                'named' => ['/^(Введение\.?\s*)(.*?)$/ium'],
            ],
            'legal_keywords' => [
                'contract_terms' => ['договор'],
                'legal_entities' => ['заказчик'],
                'actions' => ['поставить'],
            ],
            'anchor_generation' => [
                'prefix' => '<!-- SECTION_ANCHOR_',
                'suffix' => ' -->',
                'max_title_length' => 50,
                'transliteration' => true,
                'normalize_case' => true,
            ],
        ]]);

        $this->detector = new SectionDetector(new AnchorGenerator());
    }

    public function testDetectsSimpleHeaderSections(): void
    {
        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [
                new HeaderElement('Заголовок 1', 1),
                new ParagraphElement('Содержание первой секции'),
                new HeaderElement('Заголовок 2', 1),
                new ParagraphElement('Содержание второй секции'),
            ],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );

        $sections = $this->detector->detectSections($document);

        $this->assertNotEmpty($sections, 'Sections should be detected from headers');
        $this->assertCount(2, $sections);

        $this->assertEquals('Заголовок 1', $sections[0]->title);
        $this->assertEquals('Заголовок 2', $sections[1]->title);
    }
}
