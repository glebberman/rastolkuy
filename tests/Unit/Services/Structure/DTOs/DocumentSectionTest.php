<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure\DTOs;

use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\DTOs\DocumentSection;
use PHPUnit\Framework\TestCase;

class DocumentSectionTest extends TestCase
{
    public function testCreatesDocumentSectionWithRequiredFields(): void
    {
        $elements = [
            new HeaderElement('Test Header', 1),
            new ParagraphElement('Test content'),
        ];

        $section = new DocumentSection(
            id: 'test-id',
            title: 'Test Section',
            content: 'Test content',
            level: 1,
            startPosition: 0,
            endPosition: 10,
            anchor: '<!-- SECTION_ANCHOR_test -->',
            elements: $elements,
        );

        $this->assertSame('test-id', $section->id);
        $this->assertSame('Test Section', $section->title);
        $this->assertSame('Test content', $section->content);
        $this->assertSame(1, $section->level);
        $this->assertSame(0, $section->startPosition);
        $this->assertSame(10, $section->endPosition);
        $this->assertSame('<!-- SECTION_ANCHOR_test -->', $section->anchor);
        $this->assertSame($elements, $section->elements);
        $this->assertSame([], $section->subsections);
        $this->assertSame(1.0, $section->confidence);
        $this->assertSame([], $section->metadata);
    }

    public function testCreatesSectionWithSubsections(): void
    {
        $subsection = new DocumentSection(
            id: 'sub-id',
            title: 'Subsection',
            content: 'Sub content',
            level: 2,
            startPosition: 5,
            endPosition: 8,
            anchor: '<!-- SECTION_ANCHOR_sub -->',
            elements: [],
        );

        $section = new DocumentSection(
            id: 'main-id',
            title: 'Main Section',
            content: 'Main content',
            level: 1,
            startPosition: 0,
            endPosition: 10,
            anchor: '<!-- SECTION_ANCHOR_main -->',
            elements: [],
            subsections: [$subsection],
        );

        $this->assertTrue($section->hasSubsections());
        $this->assertSame(1, $section->getSubsectionCount());
        $this->assertSame([$subsection], $section->subsections);
    }

    public function testCalculatesElementsCount(): void
    {
        $elements = [
            new HeaderElement('Header', 1),
            new ParagraphElement('Paragraph 1'),
            new ParagraphElement('Paragraph 2'),
        ];

        $section = new DocumentSection(
            id: 'test',
            title: 'Test',
            content: 'Content',
            level: 1,
            startPosition: 0,
            endPosition: 5,
            anchor: '<!-- ANCHOR -->',
            elements: $elements,
        );

        $this->assertSame(3, $section->getElementsCount());
    }

    public function testCalculatesTotalLength(): void
    {
        $section = new DocumentSection(
            id: 'test',
            title: 'Test',
            content: 'Hello world',
            level: 1,
            startPosition: 0,
            endPosition: 5,
            anchor: '<!-- ANCHOR -->',
            elements: [],
        );

        $this->assertSame(11, $section->getTotalLength());
    }

    public function testGetsAllSubsectionsRecursively(): void
    {
        $subsubsection = new DocumentSection(
            id: 'subsub',
            title: 'Sub-subsection',
            content: 'Deep content',
            level: 3,
            startPosition: 7,
            endPosition: 9,
            anchor: '<!-- ANCHOR_SUBSUB -->',
            elements: [],
        );

        $subsection = new DocumentSection(
            id: 'sub',
            title: 'Subsection',
            content: 'Sub content',
            level: 2,
            startPosition: 5,
            endPosition: 9,
            anchor: '<!-- ANCHOR_SUB -->',
            elements: [],
            subsections: [$subsubsection],
        );

        $section = new DocumentSection(
            id: 'main',
            title: 'Main',
            content: 'Main content',
            level: 1,
            startPosition: 0,
            endPosition: 10,
            anchor: '<!-- ANCHOR_MAIN -->',
            elements: [],
            subsections: [$subsection],
        );

        $allSubsections = $section->getAllSubsections();
        $this->assertCount(2, $allSubsections);
        $this->assertContains($subsection, $allSubsections);
        $this->assertContains($subsubsection, $allSubsections);
    }

    public function testGetsPlainTextWithSubsections(): void
    {
        $subsection = new DocumentSection(
            id: 'sub',
            title: 'Sub',
            content: 'Sub content',
            level: 2,
            startPosition: 5,
            endPosition: 8,
            anchor: '<!-- ANCHOR -->',
            elements: [],
        );

        $section = new DocumentSection(
            id: 'main',
            title: 'Main',
            content: 'Main content',
            level: 1,
            startPosition: 0,
            endPosition: 10,
            anchor: '<!-- ANCHOR -->',
            elements: [],
            subsections: [$subsection],
        );

        $plainText = $section->getPlainText();
        $this->assertStringContainsString('Main content', $plainText);
        $this->assertStringContainsString('Sub content', $plainText);
    }

    public function testSerializesToArray(): void
    {
        $elements = [new HeaderElement('Header', 1)];
        $subsection = new DocumentSection(
            id: 'sub',
            title: 'Sub',
            content: 'Sub content',
            level: 2,
            startPosition: 5,
            endPosition: 8,
            anchor: '<!-- ANCHOR_SUB -->',
            elements: [],
        );

        $section = new DocumentSection(
            id: 'main',
            title: 'Main Section',
            content: 'Main content',
            level: 1,
            startPosition: 0,
            endPosition: 10,
            anchor: '<!-- ANCHOR_MAIN -->',
            elements: $elements,
            subsections: [$subsection],
            confidence: 0.85,
            metadata: ['test' => 'value'],
        );

        $serialized = $section->serialize();

        $this->assertSame('main', $serialized['id']);
        $this->assertSame('Main Section', $serialized['title']);
        $this->assertSame('Main content', $serialized['content']);
        $this->assertSame(1, $serialized['level']);
        $this->assertSame(0, $serialized['start_position']);
        $this->assertSame(10, $serialized['end_position']);
        $this->assertSame('<!-- ANCHOR_MAIN -->', $serialized['anchor']);
        $this->assertSame(1, $serialized['elements_count']);
        $this->assertSame(1, $serialized['subsections_count']);
        $this->assertSame(12, $serialized['total_length']);
        $this->assertSame(0.85, $serialized['confidence']);
        $this->assertSame(['test' => 'value'], $serialized['metadata']);
        $this->assertIsArray($serialized['subsections']);
        $this->assertCount(1, $serialized['subsections']);
    }

    public function testHandlesEmptySubsections(): void
    {
        $section = new DocumentSection(
            id: 'test',
            title: 'Test',
            content: 'Content',
            level: 1,
            startPosition: 0,
            endPosition: 5,
            anchor: '<!-- ANCHOR -->',
            elements: [],
        );

        $this->assertFalse($section->hasSubsections());
        $this->assertSame(0, $section->getSubsectionCount());
        $this->assertEmpty($section->getAllSubsections());
    }
}
