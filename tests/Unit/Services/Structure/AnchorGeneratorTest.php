<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure;

use App\Services\Structure\AnchorGenerator;
use PHPUnit\Framework\TestCase;

class AnchorGeneratorTest extends TestCase
{
    private AnchorGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new AnchorGenerator();
    }

    public function testGeneratesAnchorFromSectionIdAndTitle(): void
    {
        $anchor = $this->generator->generate('section_123', 'Общие положения');

        $this->assertStringStartsWith('<!-- SECTION_ANCHOR_', $anchor);
        $this->assertStringEndsWith(' -->', $anchor);
        $this->assertStringContainsString('section_123', $anchor);
        $this->assertStringContainsString('obschie_polozheniya', $anchor);
    }

    public function testTransliteratesCyrillicCharacters(): void
    {
        $anchor = $this->generator->generate('test', 'Договор аренды');

        $this->assertStringContainsString('dogovor_arendy', $anchor);
    }

    public function testNormalizesTitleToSnakeCase(): void
    {
        $anchor = $this->generator->generate('test', 'Rights and Obligations');

        $this->assertStringContainsString('rights_and_obligations', $anchor);
    }

    public function testLimitsTitleLength(): void
    {
        $longTitle = str_repeat('Very long title text ', 10);
        $anchor = $this->generator->generate('test', $longTitle);

        // Проверяем, что заголовок был обрезан
        $anchorId = $this->generator->extractAnchorId($anchor);
        $this->assertNotNull($anchorId);
        $this->assertLessThanOrEqual(100, strlen($anchorId));
    }

    public function testHandlesSpecialCharacters(): void
    {
        $anchor = $this->generator->generate('test', 'Section #1: "Important" (Note)');

        $this->assertStringContainsString('section_1_important_note', $anchor);
    }

    public function testEnsuresUniqueAnchors(): void
    {
        $anchor1 = $this->generator->generate('test', 'Same Title');
        $anchor2 = $this->generator->generate('test', 'Same Title');

        $this->assertNotSame($anchor1, $anchor2);

        $id1 = $this->generator->extractAnchorId($anchor1);
        $id2 = $this->generator->extractAnchorId($anchor2);

        $this->assertNotSame($id1, $id2);
    }

    public function testGeneratesBatchAnchors(): void
    {
        $sections = [
            'sec1' => 'Введение',
            'sec2' => 'Основная часть',
            'sec3' => 'Заключение',
        ];

        $anchors = $this->generator->generateBatch($sections);

        $this->assertCount(3, $anchors);
        $this->assertArrayHasKey('sec1', $anchors);
        $this->assertArrayHasKey('sec2', $anchors);
        $this->assertArrayHasKey('sec3', $anchors);

        foreach ($anchors as $anchor) {
            $this->assertTrue($this->generator->isValidAnchor($anchor));
        }
    }

    public function testExtractsAnchorId(): void
    {
        $anchor = '<!-- SECTION_ANCHOR_test_section_title -->';
        $id = $this->generator->extractAnchorId($anchor);

        $this->assertSame('test_section_title', $id);
    }

    public function testValidatesAnchorFormat(): void
    {
        $validAnchor = '<!-- SECTION_ANCHOR_test -->';
        $invalidAnchor = '<div>not an anchor</div>';

        $this->assertTrue($this->generator->isValidAnchor($validAnchor));
        $this->assertFalse($this->generator->isValidAnchor($invalidAnchor));
    }

    public function testFindsAnchorsInText(): void
    {
        $text = 'Some text <!-- SECTION_ANCHOR_intro --> more text <!-- SECTION_ANCHOR_conclusion --> end';
        $anchors = $this->generator->findAnchorsInText($text);

        $this->assertCount(2, $anchors);
        $this->assertContains('<!-- SECTION_ANCHOR_intro -->', $anchors);
        $this->assertContains('<!-- SECTION_ANCHOR_conclusion -->', $anchors);
    }

    public function testReplacesAnchorInText(): void
    {
        $text = 'Before <!-- SECTION_ANCHOR_test --> after';
        $result = $this->generator->replaceAnchor($text, 'test', 'REPLACED');

        $this->assertSame('Before REPLACED after', $result);
    }

    public function testInsertsAfterAnchor(): void
    {
        $text = 'Before <!-- SECTION_ANCHOR_test --> after';
        $result = $this->generator->insertAfterAnchor($text, 'test', 'INSERTED');

        $expected = 'Before <!-- SECTION_ANCHOR_test -->
INSERTED after';
        $this->assertSame($expected, $result);
    }

    public function testRemovesAnchorFromText(): void
    {
        $text = 'Before <!-- SECTION_ANCHOR_test --> after';
        $result = $this->generator->removeAnchor($text, 'test');

        $this->assertSame('Before  after', $result);
    }

    public function testResetsUsedAnchors(): void
    {
        $this->generator->generate('test1', 'Title 1');
        $this->generator->generate('test2', 'Title 2');

        $this->assertCount(2, $this->generator->getUsedAnchors());

        $this->generator->resetUsedAnchors();

        $this->assertEmpty($this->generator->getUsedAnchors());
    }

    public function testTracksUsedAnchors(): void
    {
        $this->generator->generate('test1', 'Title 1');
        $this->generator->generate('test2', 'Title 2');

        $usedAnchors = $this->generator->getUsedAnchors();

        $this->assertCount(2, $usedAnchors);
        $this->assertContains('test1_title_1', $usedAnchors);
        $this->assertContains('test2_title_2', $usedAnchors);
    }

    public function testHandlesEmptyTitle(): void
    {
        $anchor = $this->generator->generate('test', '');

        $this->assertTrue($this->generator->isValidAnchor($anchor));
        $this->assertStringContainsString('test_section', $anchor);
    }

    public function testHandlesWhitespaceOnlyTitle(): void
    {
        $anchor = $this->generator->generate('test', '   ');

        $this->assertTrue($this->generator->isValidAnchor($anchor));
        $this->assertStringContainsString('test_section', $anchor);
    }

    public function testReturnsNullForInvalidAnchorExtraction(): void
    {
        $this->assertNull($this->generator->extractAnchorId('invalid'));
        $this->assertNull($this->generator->extractAnchorId('<!-- SECTION_ANCHOR_incomplete'));
    }
}
