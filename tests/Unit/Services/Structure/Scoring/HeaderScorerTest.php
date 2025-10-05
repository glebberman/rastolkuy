<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure\Scoring;

use App\Services\Parser\Extractors\Elements\TextElement;
use App\Services\Structure\Scoring\DTOs\DocumentContext;
use App\Services\Structure\Scoring\HeaderScorer;
use PHPUnit\Framework\TestCase;

final class HeaderScorerTest extends TestCase
{
    private HeaderScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new HeaderScorer();
    }

    public function testScoresShortTextAsHeader(): void
    {
        $element = new TextElement(
            content: 'ПРАВА И ОБЯЗАННОСТИ СТОРОН',
            fontSize: 14.0,
            isBold: true,
        );

        $context = $this->createContext([$element], 12.0);

        $score = $this->scorer->scoreElement($element, $context);

        $this->assertGreaterThan(0.55, $score->total, 'Short bold text with legal keywords should score reasonably high');
        $this->assertFalse($score->isLikelyHeader(0.7), 'Should not be header with very high threshold');
        $this->assertTrue($score->isLikelyHeader(0.55), 'Should be header with moderate threshold');
    }

    public function testScoresLongTextAsNotHeader(): void
    {
        $element = new TextElement(
            content: str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 10),
            fontSize: 12.0,
        );

        $context = $this->createContext([$element], 12.0);

        $score = $this->scorer->scoreElement($element, $context);

        $this->assertLessThan(0.5, $score->total, 'Long text should score low');
        $this->assertFalse($score->isLikelyHeader());
    }

    public function testVisualScoring(): void
    {
        // Test with bold and large font
        $boldLargeElement = new TextElement(
            content: 'Важный раздел',
            fontSize: 16.0,
            isBold: true,
        );

        $context = $this->createContext([$boldLargeElement], 12.0);
        $score = $this->scorer->scoreElement($boldLargeElement, $context);

        $this->assertGreaterThan(0.6, $score->visual, 'Bold and large font should have high visual score');
    }

    public function testTextualScoring(): void
    {
        // Test short uppercase text without punctuation
        $element = new TextElement(content: 'ПРЕДМЕТ ДОГОВОРА');

        $context = $this->createContext([$element], 12.0);
        $score = $this->scorer->scoreElement($element, $context);

        $this->assertGreaterThan(0.7, $score->textual, 'Short uppercase text should have high textual score');
    }

    public function testSemanticScoring(): void
    {
        // Test with legal keywords
        $element = new TextElement(content: 'Права и обязанности сторон');

        $context = $this->createContext([$element], 12.0);
        $score = $this->scorer->scoreElement($element, $context);

        $this->assertGreaterThan(0.0, $score->semantic, 'Legal keywords should increase semantic score');
        $this->assertLessThan(0.5, $score->semantic, 'Semantic score should be moderate for partial matches');
    }

    public function testNumberedHeaderScoring(): void
    {
        $element = new TextElement(content: '1. ОБЩИЕ ПОЛОЖЕНИЯ');

        $context = $this->createContext([$element], 12.0);
        $score = $this->scorer->scoreElement($element, $context);

        $this->assertGreaterThan(0.5, $score->total, 'Numbered headers should score reasonably high');
        $this->assertTrue($score->isLikelyHeader(0.55), 'Should be header with moderate threshold');
    }

    public function testSubsectionNumbering(): void
    {
        $element = new TextElement(content: '1.1. Термины и определения');

        $context = $this->createContext([$element], 12.0);
        $score = $this->scorer->scoreElement($element, $context);

        $this->assertGreaterThan(0.4, $score->total, 'Subsection numbering should score moderately');
    }

    public function testWithoutVisualData(): void
    {
        $element = new TextElement(content: 'Предмет договора');

        $context = DocumentContext::fromElements([
            new TextElement(content: 'Some text'),
            $element,
        ]);

        $score = $this->scorer->scoreElement($element, $context);

        $this->assertEquals(0.5, $score->visual, 'Visual score should be neutral without visual data');
        $this->assertGreaterThan(0.0, $score->textual, 'Textual score should still work');
        $this->assertGreaterThan(0.0, $score->semantic, 'Semantic score should still work');
    }

    public function testStructureKeywords(): void
    {
        $testCases = [
            'Раздел 1. Общие положения' => true,
            'Статья 5. Права сторон' => true,
            'Глава II. Обязательства' => true,
            'Обычный текст без ключевых слов' => false,
        ];

        foreach ($testCases as $content => $shouldBeHeader) {
            $element = new TextElement(content: $content);
            $context = $this->createContext([$element], 12.0);
            $score = $this->scorer->scoreElement($element, $context);

            if ($shouldBeHeader) {
                $this->assertGreaterThan(0.4, $score->total, "'{$content}' should score reasonably for header");
            } else {
                $this->assertLessThan(0.5, $score->total, "'{$content}' should not score high");
            }
        }
    }

    public function testMarginScoring(): void
    {
        $elementWithLargeTopMargin = new TextElement(
            content: 'Новый раздел',
            fontSize: 12.0,
            topMargin: 30.0,
        );

        $normalElement = new TextElement(
            content: 'Обычный текст',
            fontSize: 12.0,
            topMargin: 10.0,
        );

        $context = $this->createContext([$normalElement, $elementWithLargeTopMargin], 12.0);

        $score = $this->scorer->scoreElement($elementWithLargeTopMargin, $context);

        $this->assertGreaterThan(0.3, $score->visual, 'Large top margin should contribute to visual score');
    }

    public function testScoreToArrayMethod(): void
    {
        $element = new TextElement(content: '1. ОБЩИЕ ПОЛОЖЕНИЯ');
        $context = $this->createContext([$element], 12.0);
        $score = $this->scorer->scoreElement($element, $context);

        $array = $score->toArray();

        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('visual', $array);
        $this->assertArrayHasKey('textual', $array);
        $this->assertArrayHasKey('semantic', $array);
        $this->assertArrayHasKey('is_likely_header', $array);
        $this->assertArrayHasKey('reason', $array);

        $this->assertIsFloat($array['total']);
        $this->assertIsBool($array['is_likely_header']);
    }

    public function testScoreToStringMethod(): void
    {
        $element = new TextElement(content: 'Предмет договора');
        $context = $this->createContext([$element], 12.0);
        $score = $this->scorer->scoreElement($element, $context);

        $string = (string) $score;

        $this->assertStringContainsString('HeaderScore', $string);
        $this->assertStringContainsString('total=', $string);
    }

    /**
     * Helper: create document context with specific average font size.
     *
     * @param array<\App\Services\Parser\Extractors\Elements\DocumentElement> $elements
     */
    private function createContext(array $elements, float $averageFontSize): DocumentContext
    {
        $hasVisualData = false;

        foreach ($elements as $element) {
            if ($element->fontSize !== null) {
                $hasVisualData = true;
                break;
            }
        }

        return new DocumentContext(
            allElements: $elements,
            averageFontSize: $averageFontSize,
            mostCommonFontSize: $averageFontSize,
            hasVisualData: $hasVisualData,
            totalElements: count($elements),
        );
    }
}
