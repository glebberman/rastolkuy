<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use App\Services\Structure\DTOs\DocumentSection;
use App\Services\Structure\StructureAnalyzer;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class StructureAnalyzerTest extends TestCase
{
    private StructureAnalyzer $analyzer;

    private SectionDetectorInterface&MockObject $sectionDetector;

    private AnchorGeneratorInterface&MockObject $anchorGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        // Мокаем конфигурацию для тестов
        config(['structure_analysis.detection' => [
            'min_confidence_threshold' => 0.3,
            'max_analysis_time_seconds' => 120,
        ]]);

        $this->sectionDetector = $this->createMock(SectionDetectorInterface::class);
        $this->anchorGenerator = $this->createMock(AnchorGeneratorInterface::class);

        $this->analyzer = new StructureAnalyzer(
            $this->sectionDetector,
            $this->anchorGenerator,
        );
    }

    public function testAnalyzesDocumentSuccessfully(): void
    {
        $document = $this->createExtractedDocument();
        $sections = [$this->createDocumentSection()];

        $this->anchorGenerator->expects($this->once())
            ->method('resetUsedAnchors');

        $this->sectionDetector->expects($this->once())
            ->method('detectSections')
            ->with($document)
            ->willReturn($sections);

        $result = $this->analyzer->analyze($document);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(1, $result->getSectionsCount());
        $this->assertGreaterThan(0, $result->analysisTime);
        $this->assertFalse($result->hasWarnings());
    }

    public function testFiltersSectionsByConfidence(): void
    {
        $document = $this->createExtractedDocument();

        $highConfidenceSection = $this->createDocumentSection('high', 0.8);
        $lowConfidenceSection = $this->createDocumentSection('low', 0.2);

        $this->anchorGenerator->method('resetUsedAnchors');
        $this->sectionDetector->method('detectSections')
            ->willReturn([$highConfidenceSection, $lowConfidenceSection]);

        $result = $this->analyzer->analyze($document);

        // Только секция с высоким confidence должна остаться (>= 0.3)
        $this->assertSame(1, $result->getSectionsCount());
        $this->assertSame('high', $result->sections[0]->id);
    }

    public function testCalculatesStatistics(): void
    {
        $document = $this->createExtractedDocument('Long document content for testing statistics calculation');
        $sections = [
            $this->createDocumentSection('sec1', 0.9, 'Content 1'),
            $this->createDocumentSection('sec2', 0.8, 'Content 2'),
        ];

        $this->anchorGenerator->method('resetUsedAnchors');
        $this->sectionDetector->method('detectSections')->willReturn($sections);

        $result = $this->analyzer->analyze($document);

        $this->assertArrayHasKey('total_sections', $result->statistics);
        $this->assertArrayHasKey('sections_by_level', $result->statistics);
        $this->assertArrayHasKey('average_section_length', $result->statistics);
        $this->assertArrayHasKey('coverage_percentage', $result->statistics);
        $this->assertSame(2, $result->statistics['total_sections']);
    }

    public function testCalculatesAverageConfidence(): void
    {
        $document = $this->createExtractedDocument();
        $sections = [
            $this->createDocumentSection('sec1', 0.9),
            $this->createDocumentSection('sec2', 0.7),
        ];

        $this->anchorGenerator->method('resetUsedAnchors');
        $this->sectionDetector->method('detectSections')->willReturn($sections);

        $result = $this->analyzer->analyze($document);

        $this->assertSame(0.8, $result->averageConfidence);
    }

    public function testGeneratesWarningsForNoSections(): void
    {
        $document = $this->createExtractedDocument();

        $this->anchorGenerator->method('resetUsedAnchors');
        $this->sectionDetector->method('detectSections')->willReturn([]);

        $result = $this->analyzer->analyze($document);

        $this->assertTrue($result->hasWarnings());
        $this->assertContains('No sections detected in document', $result->warnings);
    }

    public function testGeneratesWarningsForLowConfidence(): void
    {
        $document = $this->createExtractedDocument();
        $sections = [
            $this->createDocumentSection('sec1', 0.6), // Low confidence
            $this->createDocumentSection('sec2', 0.5), // Low confidence
        ];

        $this->anchorGenerator->method('resetUsedAnchors');
        $this->sectionDetector->method('detectSections')->willReturn($sections);

        $result = $this->analyzer->analyze($document);

        $this->assertTrue($result->hasWarnings());
        $warningFound = false;

        foreach ($result->warnings as $warning) {
            if (str_contains($warning, 'sections have low confidence scores')) {
                $warningFound = true;
                break;
            }
        }
        $this->assertTrue($warningFound);
    }

    public function testCanAnalyzeChecksDocumentRequirements(): void
    {
        // Document with no elements
        $emptyDocument = new ExtractedDocument(
            originalPath: '/test/empty.txt',
            mimeType: 'text/plain',
            elements: [],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );

        $this->assertFalse($this->analyzer->canAnalyze($emptyDocument));

        // Document with sufficient content
        $validDocument = $this->createExtractedDocument();
        $this->assertTrue($this->analyzer->canAnalyze($validDocument));
    }

    public function testAnalyzesBatchDocuments(): void
    {
        $doc1 = $this->createExtractedDocument('Document 1 content');
        $doc2 = $this->createExtractedDocument('Document 2 content');

        $section1 = $this->createDocumentSection('sec1');
        $section2 = $this->createDocumentSection('sec2');

        $this->anchorGenerator->method('resetUsedAnchors');
        $this->sectionDetector->method('detectSections')
            ->willReturnOnConsecutiveCalls([$section1], [$section2]);

        $results = $this->analyzer->analyzeBatch(['doc1' => $doc1, 'doc2' => $doc2]);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('doc1', $results);
        $this->assertArrayHasKey('doc2', $results);
        $this->assertTrue($results['doc1']->isSuccessful());
        $this->assertTrue($results['doc2']->isSuccessful());
    }

    public function testHandlesAnalysisException(): void
    {
        $document = $this->createExtractedDocument();

        $this->anchorGenerator->method('resetUsedAnchors');
        $this->sectionDetector->method('detectSections')
            ->willThrowException(new Exception('Detection failed'));

        $result = $this->analyzer->analyze($document);

        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->hasWarnings());
        $this->assertContains('Analysis failed: Detection failed', $result->warnings);
        $this->assertArrayHasKey('error', $result->metadata);
    }

    private function createExtractedDocument(string $content = 'Sample document content for testing purposes. This should be long enough to pass validation.'): ExtractedDocument
    {
        $elements = [
            new HeaderElement('Test Header', 1),
            new ParagraphElement($content),
        ];

        return new ExtractedDocument(
            originalPath: '/test/document.txt',
            mimeType: 'text/plain',
            elements: $elements,
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );
    }

    private function createDocumentSection(string $id = 'test-section', float $confidence = 0.9, string $content = 'Sample section content'): DocumentSection
    {
        return new DocumentSection(
            id: $id,
            title: 'Test Section',
            content: $content,
            level: 1,
            startPosition: 0,
            endPosition: 10,
            anchor: '<!-- SECTION_ANCHOR_' . $id . ' -->',
            elements: [new ParagraphElement($content)],
            confidence: $confidence,
        );
    }
}
