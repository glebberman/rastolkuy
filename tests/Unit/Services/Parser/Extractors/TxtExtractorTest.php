<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Parser\Extractors;

use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ListElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use App\Services\Parser\Extractors\TxtExtractor;
use InvalidArgumentException;
use Tests\TestCase;

class TxtExtractorTest extends TestCase
{
    private TxtExtractor $extractor;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new TxtExtractor(
            new EncodingDetector(),
            new ElementClassifier(),
            new MetricsCollector(),
        );

        $this->fixturesPath = base_path('tests/Fixtures/extractors');
    }

    public function testSupportsTextMimeTypes(): void
    {
        $this->assertTrue($this->extractor->supports('text/plain'));
        $this->assertTrue($this->extractor->supports('text/txt'));
        $this->assertTrue($this->extractor->supports('application/txt'));
        $this->assertFalse($this->extractor->supports('application/pdf'));
    }

    public function testValidatesExistingFile(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $this->assertTrue($this->extractor->validate($filePath));
    }

    public function testValidatesNonExistingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $this->extractor->validate('/non/existing/file.txt');
    }

    public function testValidatesEmptyFile(): void
    {
        // Create a truly empty file for testing
        $emptyFile = $this->fixturesPath . '/truly_empty.txt';
        file_put_contents($emptyFile, '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File is empty');

        try {
            $this->extractor->validate($emptyFile);
        } finally {
            unlink($emptyFile);
        }
    }

    public function testExtractsSimpleTextFile(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $this->assertEquals('text/plain', $result->mimeType);
        $this->assertEquals($filePath, $result->originalPath);
        $this->assertEquals(1, $result->totalPages);
        $this->assertGreaterThan(0, $result->extractionTime);
        $this->assertIsArray($result->elements);
        $this->assertGreaterThan(0, count($result->elements));
    }

    public function testExtractsWithCustomConfig(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $config = ExtractionConfig::createFast();

        $result = $this->extractor->extract($filePath, $config);

        $this->assertEquals('text/plain', $result->mimeType);
        $this->assertIsArray($result->elements);
    }

    public function testDetectsHeaders(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $headers = $result->getHeaders();
        $this->assertGreaterThan(0, count($headers));

        foreach ($headers as $header) {
            $this->assertInstanceOf(HeaderElement::class, $header);
            $this->assertEquals('header', $header->type);
            $this->assertIsInt($header->level);
        }
    }

    public function testDetectsLists(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $lists = array_filter(
            $result->elements,
            fn ($element) => $element instanceof ListElement,
        );

        $this->assertGreaterThan(0, count($lists));

        foreach ($lists as $list) {
            $this->assertInstanceOf(ListElement::class, $list);
            $this->assertEquals('list', $list->type);
            $this->assertIsArray($list->getItems());
            $this->assertContains($list->getListType(), ['ordered', 'unordered']);
        }
    }

    public function testDetectsParagraphs(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $paragraphs = array_filter(
            $result->elements,
            fn ($element) => $element instanceof ParagraphElement,
        );

        $this->assertGreaterThan(0, count($paragraphs));

        foreach ($paragraphs as $paragraph) {
            $this->assertInstanceOf(ParagraphElement::class, $paragraph);
            $this->assertEquals('paragraph', $paragraph->type);
            $this->assertNotEmpty($paragraph->content);
        }
    }

    public function testGetsMetadata(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $metadata = $this->extractor->getMetadata($filePath);

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('file_size', $metadata);
        $this->assertArrayHasKey('encoding', $metadata);
        $this->assertArrayHasKey('mime_type', $metadata);
        $this->assertArrayHasKey('estimated_processing_time', $metadata);

        $this->assertEquals('text/plain', $metadata['mime_type']);
        $this->assertIsInt($metadata['file_size']);
        $this->assertIsString($metadata['encoding']);
    }

    public function testEstimatesProcessingTime(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $time = $this->extractor->estimateProcessingTime($filePath);

        $this->assertIsInt($time);
        $this->assertGreaterThan(0, $time);
    }

    public function testExtractionIncludesMetrics(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $this->assertIsArray($result->metrics);
        $this->assertArrayHasKey('txt_extraction', $result->metrics);
    }

    public function testSerialization(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $serialized = $result->serialize();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('original_path', $serialized);
        $this->assertArrayHasKey('mime_type', $serialized);
        $this->assertArrayHasKey('elements', $serialized);
        $this->assertArrayHasKey('metadata', $serialized);
        $this->assertArrayHasKey('total_pages', $serialized);
        $this->assertArrayHasKey('extraction_time', $serialized);
    }

    public function testPlainTextExtraction(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $plainText = $result->getPlainText();

        $this->assertIsString($plainText);
        $this->assertNotEmpty($plainText);
        $this->assertStringContainsString('ПРОСТОЙ ЗАГОЛОВОК', $plainText);
    }

    public function testElementsByType(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->extractor->extract($filePath);

        $headers = $result->getElementsByType('header');
        $paragraphs = $result->getElementsByType('paragraph');
        $lists = $result->getElementsByType('list');

        $this->assertIsArray($headers);
        $this->assertIsArray($paragraphs);
        $this->assertIsArray($lists);

        foreach ($headers as $header) {
            $this->assertEquals('header', $header->type);
        }

        foreach ($paragraphs as $paragraph) {
            $this->assertEquals('paragraph', $paragraph->type);
        }

        foreach ($lists as $list) {
            $this->assertEquals('list', $list->type);
        }
    }
}
