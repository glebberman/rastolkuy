<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentProcessing;
use App\Services\Export\ContentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Интеграционный тест для полного цикла обработки документа с тестовыми данными.
 */
final class DocumentTranslationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function testFullDocumentProcessingCycleWithFakeData(): void
    {
        // Arrange - создаем документ с тестовыми данными
        $testData = $this->loadTestTranslationResponse();

        $content = is_string($testData['content']) ? $testData['content'] : '';
        $document = DocumentProcessing::factory()->create([
            'status' => 'completed',
            'result' => ['content' => $content],
            'original_filename' => 'test_contract.pdf',
        ]);

        $contentProcessor = new ContentProcessor();

        // Act - обрабатываем документ
        $parsedContent = $contentProcessor->parseDocumentResult($document);

        // Assert - проверяем результаты обработки
        $this->assertGreaterThan(5, $parsedContent->getSectionsCount());
        $this->assertNotEmpty($parsedContent->anchors);

        // Проверяем что все ожидаемые секции присутствуют
        $expectedSections = [
            'section_1_predmet_dogovora',
            'section_2_stoimost_i_poryadok_oplaty',
            'section_3_sroki_vypolneniya',
            'section_4_otvetstvennost_storon',
            'section_5_poryadok_sdachi_priemki',
            'section_6_konfidentsialnost',
            'section_7_razreshenie_sporov',
        ];

        foreach ($expectedSections as $expectedId) {
            $section = $parsedContent->getSectionById($expectedId);
            $this->assertNotNull($section, "Секция {$expectedId} должна существовать");
            $this->assertTrue($section->hasTranslations(), "Секция {$expectedId} должна иметь переводы");
        }

        // Проверяем специфические риски
        $allRisks = [];
        foreach ($parsedContent->sections as $section) {
            foreach ($section->risks as $risk) {
                $allRisks[] = $risk;
            }
        }

        $this->assertGreaterThanOrEqual(6, count($allRisks), 'Должно быть минимум 6 рисков');

        // Проверяем типы рисков
        $riskTypes = array_map(fn($risk) => $risk->type, $allRisks);
        $this->assertContains('risk', $riskTypes);
        $this->assertContains('contradiction', $riskTypes);
        $this->assertContains('warning', $riskTypes);

        // Проверяем конкретные риски из тестовых данных
        $riskTexts = array_map(fn($risk) => $risk->text, $allRisks);
        $expectedRiskPatterns = [
            'технического задания',
            'пени',
            'завышенным',
            'существенные изменения',
            'широкие основания',
            'Арбитражный суд',
        ];

        foreach ($expectedRiskPatterns as $pattern) {
            $found = false;
            foreach ($riskTexts as $text) {
                if (str_contains($text, $pattern)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Должен быть найден риск, содержащий: {$pattern}");
        }
    }

    public function testDocumentStructureAnalysisWithAnchors(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();
        $contentProcessor = new ContentProcessor();

        // Act
        $content = is_string($testData['content']) ? $testData['content'] : '';
        $parsedContent = $contentProcessor->parseContent($content);

        // Assert - проверяем корректность извлечения якорей
        $this->assertCount(7, $parsedContent->anchors);

        $expectedAnchors = [
            'section_1_predmet_dogovora',
            'section_2_stoimost_i_poryadok_oplaty',
            'section_3_sroki_vypolneniya',
            'section_4_otvetstvennost_storon',
            'section_5_poryadok_sdachi_priemki',
            'section_6_konfidentsialnost',
            'section_7_razreshenie_sporov',
        ];

        foreach ($expectedAnchors as $expectedAnchor) {
            $this->assertContains($expectedAnchor, $parsedContent->anchors);
        }

        // Проверяем соответствие якорей и секций
        foreach ($parsedContent->sections as $section) {
            if ($section->anchor !== null) {
                $this->assertStringContainsString($section->id, $section->anchor);
            }
        }
    }

    public function testTranslationQualityWithFakeData(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();
        $contentProcessor = new ContentProcessor();

        // Act
        $content = is_string($testData['content']) ? $testData['content'] : '';
        $parsedContent = $contentProcessor->parseContent($content);

        // Assert - проверяем качество переводов
        $translationsCount = 0;
        $qualityMarkers = 0;

        foreach ($parsedContent->sections as $section) {
            foreach ($section->translatedContent as $translation) {
                $translationsCount++;

                // Проверяем наличие маркеров качественного перевода
                if (str_contains($translation, 'Простыми словами:')) {
                    $qualityMarkers++;
                }

                // Проверяем, что перевод содержательный
                $this->assertGreaterThan(10, strlen($translation), 'Перевод должен быть содержательным');
                $this->assertStringNotContainsString('<!-- SECTION_ANCHOR_', $translation, 'Перевод не должен содержать якоря');
            }
        }

        $this->assertGreaterThan(5, $translationsCount, 'Должно быть более 5 переводов');
        $this->assertGreaterThan(5, $qualityMarkers, 'Большинство переводов должны иметь маркер "Простыми словами:"');
    }

    public function testContentManipulationWithFakeData(): void
    {
        // Arrange
        $testData = $this->loadTestTranslationResponse();
        $contentProcessor = new ContentProcessor();
        $originalContent = $testData['content'];

        // Test 1: Удаление якорей
        $originalContentStr = is_string($originalContent) ? $originalContent : '';
        $cleanContent = $contentProcessor->removeAnchors($originalContentStr);
        $this->assertStringNotContainsString('<!-- SECTION_ANCHOR_', $cleanContent);
        $this->assertStringContainsString('ПРЕДМЕТ ДОГОВОРА', $cleanContent);

        // Test 2: Замена якорей
        $replacements = [
            'section_1_predmet_dogovora' => '<section id="subject"><h2>Предмет договора</h2></section>',
            'section_2_stoimost_i_poryadok_oplaty' => '<section id="payment"><h2>Оплата</h2></section>',
        ];

        $replacedContent = $contentProcessor->replaceAnchors($originalContentStr, $replacements);
        $this->assertStringContainsString('<section id="subject"><h2>Предмет договора</h2></section>', $replacedContent);
        $this->assertStringContainsString('<section id="payment"><h2>Оплата</h2></section>', $replacedContent);

        // Test 3: Парсинг после замены
        $parsedReplaced = $contentProcessor->parseContent($replacedContent);
        $this->assertGreaterThan(0, $parsedReplaced->getSectionsCount());
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