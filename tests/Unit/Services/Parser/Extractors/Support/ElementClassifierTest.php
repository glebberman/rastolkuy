<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Parser\Extractors\Support;

use App\Services\Parser\Extractors\Support\ElementClassifier;
use Tests\TestCase;

class ElementClassifierTest extends TestCase
{
    private ElementClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new ElementClassifier();
    }

    public function testClassifiesHeaders(): void
    {
        $headerTexts = [
            'CHAPTER 1',
            'ВАЖНАЯ ИНФОРМАЦИЯ',
            '1. ОБЩИЕ ПОЛОЖЕНИЯ',
            '# Markdown Header',
            'SOME ALL CAPS TITLE',
        ];

        foreach ($headerTexts as $text) {
            $type = $this->classifier->classify($text);
            $this->assertEquals('header', $type, "Failed to classify '{$text}' as header");
        }
    }

    public function testClassifiesParagraphs(): void
    {
        $paragraphTexts = [
            'This is a regular paragraph with multiple sentences. It contains normal text.',
            'В соответствии с действующим законодательством Российской Федерации настоящий договор регулирует отношения между сторонами.',
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        ];

        foreach ($paragraphTexts as $text) {
            $type = $this->classifier->classify($text);
            $this->assertEquals('paragraph', $type, "Failed to classify '{$text}' as paragraph");
        }
    }

    public function testClassifiesShortTextAsText(): void
    {
        $shortTexts = [
            'short text',
            'normal word',
            'yes please',
            'да, конечно',
            'test content',
        ];

        foreach ($shortTexts as $text) {
            $type = $this->classifier->classify($text);
            $this->assertEquals('text', $type, "Failed to classify '{$text}' as text");
        }
    }

    public function testDeterminesHeaderLevels(): void
    {
        $headers = [
            '# Markdown Header' => 1,
            '## Section Header' => 2,
            '### Subsection Header' => 3,
        ];

        foreach ($headers as $text => $expectedLevel) {
            $level = $this->classifier->determineHeaderLevel($text);
            $this->assertEquals($expectedLevel, $level, "Wrong header level for '{$text}'");
        }

        // Test default level for regular text
        $defaultLevel = $this->classifier->determineHeaderLevel('Regular header text');
        $this->assertEquals(3, $defaultLevel);
    }

    public function testCalculatesConfidenceScores(): void
    {
        $testCases = [
            ['header', 'CHAPTER 1', 0.8],
            ['paragraph', 'This is a long paragraph with multiple sentences.', 0.7],
            ['text', 'Short', 0.6],
        ];

        foreach ($testCases as [$type, $text, $minExpected]) {
            $score = $this->classifier->getConfidenceScore($type, $text);
            $this->assertGreaterThanOrEqual($minExpected, $score, "Confidence too low for '{$text}' as {$type}");
            $this->assertLessThanOrEqual(1.0, $score, "Confidence too high for '{$text}' as {$type}");
        }
    }

    public function testHandlesEmptyText(): void
    {
        $type = $this->classifier->classify('');
        $this->assertEquals('text', $type);

        $confidence = $this->classifier->getConfidenceScore('text', '');
        $this->assertGreaterThan(0, $confidence);
    }

    public function testClassifiesTableContent(): void
    {
        $tableTexts = [
            "Name\tAge\tCity\nJohn\t25\tNew York\nJane\t30\tLondon",
            "| Column 1 | Column 2 | Column 3 |\n|----------|----------|----------|\n| Data 1   | Data 2   | Data 3   |",
            "№\tНаименование\tЦена\n1\tТовар А\t100\n2\tТовар Б\t200",
        ];

        foreach ($tableTexts as $text) {
            $type = $this->classifier->classify($text);
            $this->assertEquals('table', $type, "Failed to classify table content: '{$text}'");
        }
    }

    public function testClassifiesListContent(): void
    {
        $listTexts = [
            "* Item 1\n* Item 2\n* Item 3",
            "1. First item\n2. Second item\n3. Third item",
            "- Point A\n- Point B\n- Point C",
            "a) пункт первый\nb) пункт второй\nc) пункт третий",
        ];

        foreach ($listTexts as $text) {
            $type = $this->classifier->classify($text);
            $this->assertEquals('list', $type, "Failed to classify list content: '{$text}'");
        }
    }

    public function testHandlesCyrillicContent(): void
    {
        $cyrillicTexts = [
            'ГЛАВА 1. ОБЩИЕ ПОЛОЖЕНИЯ' => 'header',
            'В соответствии с настоящим договором стороны обязуются выполнить следующие условия.' => 'paragraph',
            'Краткий текст' => 'text',
        ];

        foreach ($cyrillicTexts as $text => $expectedType) {
            $type = $this->classifier->classify($text);
            $this->assertEquals($expectedType, $type, "Failed to classify Cyrillic text: '{$text}'");
        }
    }

    public function testHeaderLevelBoundaries(): void
    {
        $level = $this->classifier->determineHeaderLevel('A');
        $this->assertGreaterThanOrEqual(1, $level);
        $this->assertLessThanOrEqual(6, $level);

        $longHeader = str_repeat('LONG HEADER TEXT ', 20);
        $level = $this->classifier->determineHeaderLevel($longHeader);
        $this->assertGreaterThanOrEqual(1, $level);
        $this->assertLessThanOrEqual(6, $level);
    }

    public function testConfidenceScoreBoundaries(): void
    {
        $score = $this->classifier->getConfidenceScore('header', 'CHAPTER');
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);

        $score = $this->classifier->getConfidenceScore('unknown_type', 'text');
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }
}
