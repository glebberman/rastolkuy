<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Export;

use App\Models\DocumentProcessing;
use App\Services\Export\ContentProcessor;
use App\Services\Export\DTOs\ParsedContent;
use App\Services\Export\DTOs\Risk;
use App\Services\Export\DTOs\Section;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Тесты для ContentProcessor с использованием фейковых данных.
 */
final class ContentProcessorTest extends TestCase
{
    private ContentProcessor $processor;

    private string $testContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new ContentProcessor();

        // Загружаем тестовые данные
        $testData = $this->loadTestTranslationResponse();
        $this->testContent = $testData['content'];
    }

    public function testParseDocumentResultWithCompletedDocument(): void
    {
        // Arrange
        $document = new DocumentProcessing();
        $document->result = ['content' => $this->testContent];
        $document->status = 'completed';

        // Act
        $result = $this->processor->parseDocumentResult($document);

        // Assert
        $this->assertInstanceOf(ParsedContent::class, $result);
        $this->assertSame($this->testContent, $result->originalContent);
        $this->assertGreaterThan(0, $result->getSectionsCount());
        $this->assertNotEmpty($result->anchors);
    }

    public function testParseDocumentResultThrowsExceptionForIncompleteDocument(): void
    {
        // Arrange
        $document = new DocumentProcessing();
        $document->result = null;
        $document->status = 'processing';

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document must be completed and have result');

        $this->processor->parseDocumentResult($document);
    }

    public function testParseContentExtractsCorrectNumberOfSections(): void
    {
        // Act
        $result = $this->processor->parseContent($this->testContent);

        // Assert
        $this->assertCount(7, $result->sections); // 7 секций в тестовых данных
        $this->assertCount(7, $result->anchors); // 7 якорей
    }

    public function testParseContentExtractsCorrectSectionData(): void
    {
        // Act
        $result = $this->processor->parseContent($this->testContent);

        // Assert
        $firstSection = $result->sections[0];
        $this->assertInstanceOf(Section::class, $firstSection);
        $this->assertSame('section_1_predmet_dogovora', $firstSection->id);
        $this->assertSame('1. ПРЕДМЕТ ДОГОВОРА', $firstSection->title);
        $this->assertTrue($firstSection->hasTranslations());
        $this->assertTrue($firstSection->hasRisks());
    }

    public function testParseContentExtractsTranslations(): void
    {
        // Act
        $result = $this->processor->parseContent($this->testContent);

        // Assert
        $firstSection = $result->sections[0];
        $translation = $firstSection->getMainTranslation();

        $this->assertStringContainsString('Простыми словами:', $translation);
        $this->assertStringContainsString('Программист будет делать сайт', $translation);
    }

    public function testParseContentExtractsRisks(): void
    {
        // Act
        $result = $this->processor->parseContent($this->testContent);

        // Assert
        $risksFound = false;
        foreach ($result->sections as $section) {
            if ($section->hasRisks()) {
                $risksFound = true;
                $risk = $section->risks[0];
                $this->assertInstanceOf(Risk::class, $risk);
                $this->assertContains($risk->type, ['risk', 'contradiction', 'warning']);
                $this->assertNotEmpty($risk->text);
                break;
            }
        }

        $this->assertTrue($risksFound, 'Должны быть найдены риски в секциях');
    }

    public function testParseContentExtractsContradictions(): void
    {
        // Act
        $result = $this->processor->parseContent($this->testContent);

        // Assert
        $contradictionFound = false;
        foreach ($result->sections as $section) {
            foreach ($section->risks as $risk) {
                if ($risk->isContradiction()) {
                    $contradictionFound = true;
                    $this->assertStringContainsString('пени', $risk->text);
                    break 2;
                }
            }
        }

        $this->assertTrue($contradictionFound, 'Должно быть найдено противоречие');
    }

    public function testRemoveAnchors(): void
    {
        // Act
        $cleanContent = $this->processor->removeAnchors($this->testContent);

        // Assert
        $this->assertStringNotContainsString('<!-- SECTION_ANCHOR_', $cleanContent);
        $this->assertStringNotContainsString(' -->', $cleanContent);
        $this->assertStringContainsString('ПРЕДМЕТ ДОГОВОРА', $cleanContent);
    }

    public function testReplaceAnchors(): void
    {
        // Arrange
        $replacements = [
            'section_1_predmet_dogovora' => '<h2>Замененный заголовок</h2>',
        ];

        // Act
        $result = $this->processor->replaceAnchors($this->testContent, $replacements);

        // Assert
        $this->assertStringContainsString('<h2>Замененный заголовок</h2>', $result);
        $this->assertStringNotContainsString('<!-- SECTION_ANCHOR_section_1_predmet_dogovora -->', $result);
    }

    public function testParseContentHandlesEmptyContent(): void
    {
        // Act
        $result = $this->processor->parseContent('');

        // Assert
        $this->assertInstanceOf(ParsedContent::class, $result);
        $this->assertCount(1, $result->sections); // ContentProcessor всегда создает одну секцию для пустого контента
        $this->assertSame('main', $result->sections[0]->id);
        $this->assertSame('Документ', $result->sections[0]->title);
        $this->assertCount(0, $result->anchors);
    }

    public function testParseContentHandlesContentWithoutAnchors(): void
    {
        // Arrange
        $contentWithoutAnchors = 'Обычный текст без якорей';

        // Act
        $result = $this->processor->parseContent($contentWithoutAnchors);

        // Assert
        $this->assertCount(1, $result->sections);
        $this->assertSame('main', $result->sections[0]->id);
        $this->assertSame('Документ', $result->sections[0]->title);
        $this->assertCount(0, $result->anchors);
    }

    public function testGetSectionById(): void
    {
        // Act
        $result = $this->processor->parseContent($this->testContent);

        // Assert
        $section = $result->getSectionById('section_2_stoimost_i_poryadok_oplaty');
        $this->assertNotNull($section);
        $this->assertSame('section_2_stoimost_i_poryadok_oplaty', $section->id);
        $this->assertStringContainsString('СТОИМОСТЬ И ПОРЯДОК ОПЛАТЫ', $section->title);

        $nonExistentSection = $result->getSectionById('non_existent_section');
        $this->assertNull($nonExistentSection);
    }

    /**
     * Загружает тестовые данные из файла.
     *
     * @return array{content: string, anchors: array<array{id: string, title: string, translation: string}>, risks: array<array{type: string, text: string}>}
     */
    private function loadTestTranslationResponse(): array
    {
        $filePath = base_path('tests/Fixtures/document_translation_response.json');
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException('Failed to load test translation response');
        }

        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON in test translation response');
        }

        return $data;
    }
}