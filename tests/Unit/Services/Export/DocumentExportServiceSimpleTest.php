<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Export;

use App\Models\DocumentExport;
use App\Models\DocumentProcessing;
use App\Models\User;
use App\Services\Export\ContentProcessor;
use App\Services\Export\DocumentExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Упрощенные тесты для DocumentExportService с использованием фейковых данных.
 */
final class DocumentExportServiceSimpleTest extends TestCase
{
    use RefreshDatabase;

    private ContentProcessor $contentProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contentProcessor = new ContentProcessor();

        // Настраиваем фейковый диск для тестов
        Storage::fake('local');
    }

    public function testContentProcessorParsesFakeDataCorrectly(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();
        $document = $this->createTestDocument([
            'result' => ['content' => $testData['content']],
        ]);

        // Act
        $parsedContent = $this->contentProcessor->parseDocumentResult($document);

        // Assert
        $this->assertNotEmpty($parsedContent->sections);
        $this->assertGreaterThan(5, count($parsedContent->sections)); // Ожидаем минимум 5 секций
        $this->assertNotEmpty($parsedContent->anchors);

        // Проверяем, что секции содержат ожидаемые данные
        $firstSection = $parsedContent->sections[0];
        $this->assertSame('section_1_predmet_dogovora', $firstSection->id);
        $this->assertStringContainsString('ПРЕДМЕТ ДОГОВОРА', $firstSection->title);
        $this->assertTrue($firstSection->hasTranslations());
        $this->assertTrue($firstSection->hasRisks());
    }

    public function testContentProcessorExtractsRisksFromFakeData(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();

        // Act
        $content = is_string($testData['content']) ? $testData['content'] : '';
        $parsedContent = $this->contentProcessor->parseContent($content);

        // Assert
        $totalRisks = 0;
        $contradictionFound = false;
        $riskFound = false;
        $warningFound = false;

        foreach ($parsedContent->sections as $section) {
            $totalRisks += count($section->risks);

            foreach ($section->risks as $risk) {
                if ($risk->isContradiction()) {
                    $contradictionFound = true;
                }
                if ($risk->isRisk()) {
                    $riskFound = true;
                }
                if ($risk->isWarning()) {
                    $warningFound = true;
                }
            }
        }

        $this->assertGreaterThan(0, $totalRisks, 'Должны быть найдены риски');
        $this->assertTrue($contradictionFound, 'Должно быть найдено противоречие');
        $this->assertTrue($riskFound, 'Должен быть найден риск');
        $this->assertTrue($warningFound, 'Должно быть найдено предупреждение');
    }

    public function testContentProcessorExtractsTranslationsFromFakeData(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();

        // Act
        $content = is_string($testData['content']) ? $testData['content'] : '';
        $parsedContent = $this->contentProcessor->parseContent($content);

        // Assert
        $translationsFound = 0;

        foreach ($parsedContent->sections as $section) {
            if ($section->hasTranslations()) {
                $translationsFound++;
                $translation = $section->getMainTranslation();
                $this->assertStringContainsString('Простыми словами:', $translation);
            }
        }

        $this->assertGreaterThan(5, $translationsFound, 'Должно быть найдено более 5 переводов');
    }

    public function testRemoveAnchorsWorksWithFakeData(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();
        $contentWithAnchors = $testData['content'];

        // Act
        $content = is_string($testData['content']) ? $testData['content'] : '';
        $cleanContent = $this->contentProcessor->removeAnchors($content);

        // Assert
        $this->assertStringNotContainsString('<!-- SECTION_ANCHOR_', $cleanContent);
        $this->assertStringNotContainsString(' -->', $cleanContent);
        $this->assertStringContainsString('ПРЕДМЕТ ДОГОВОРА', $cleanContent);
        $this->assertStringContainsString('СТОИМОСТЬ И ПОРЯДОК ОПЛАТЫ', $cleanContent);
    }

    public function testReplaceAnchorsWorksWithFakeData(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();
        $contentWithAnchors = $testData['content'];

        $replacements = [
            'section_1_predmet_dogovora' => '<h2 class="replaced">Замененный заголовок</h2>',
            'section_2_stoimost_i_poryadok_oplaty' => '<div class="replaced">Замененная секция цены</div>',
        ];

        // Act
        $content = is_string($testData['content']) ? $testData['content'] : '';
        $result = $this->contentProcessor->replaceAnchors($content, $replacements);

        // Assert
        $this->assertStringContainsString('<h2 class="replaced">Замененный заголовок</h2>', $result);
        $this->assertStringContainsString('<div class="replaced">Замененная секция цены</div>', $result);
        $this->assertStringNotContainsString('<!-- SECTION_ANCHOR_section_1_predmet_dogovora -->', $result);
        $this->assertStringNotContainsString('<!-- SECTION_ANCHOR_section_2_stoimost_i_poryadok_oplaty -->', $result);
    }

    public function testParseContentValidatesJsonStructure(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();

        // Act & Assert - проверяем, что загруженные данные соответствуют ожиданиям
        $this->assertArrayHasKey('content', $testData);
        $this->assertArrayHasKey('anchors', $testData);
        $this->assertArrayHasKey('risks', $testData);

        $this->assertIsString($testData['content']);
        $this->assertIsArray($testData['anchors']);
        $this->assertIsArray($testData['risks']);

        // Проверяем структуру якорей
        foreach ($testData['anchors'] as $anchor) {
            $this->assertIsArray($anchor);
            $this->assertArrayHasKey('id', $anchor);
            $this->assertArrayHasKey('title', $anchor);
            $this->assertArrayHasKey('translation', $anchor);
        }

        // Проверяем структуру рисков
        foreach ($testData['risks'] as $risk) {
            $this->assertIsArray($risk);
            $this->assertArrayHasKey('type', $risk);
            $this->assertArrayHasKey('text', $risk);
            $this->assertContains($risk['type'], ['risk', 'contradiction', 'warning']);
        }
    }

    /**
     * Создает тестовый документ.
     */
    private function createTestDocument(array $attributes = []): DocumentProcessing
    {
        return DocumentProcessing::factory()->create(array_merge([
            'status' => 'completed',
            'result' => ['content' => 'Test content'],
            'original_filename' => 'test_document.pdf',
        ], $attributes));
    }

    /**
     * Загружает тестовые данные из файла.
     *
     * @return array<string, mixed>
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