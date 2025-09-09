<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Parser\Extractors;

use App\Services\Parser\Extractors\ExtractorFactory;
use App\Services\Parser\Extractors\ExtractorInterface;
use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use App\Services\Parser\Extractors\TxtExtractor;
use InvalidArgumentException;
use stdClass;
use Tests\TestCase;

class ExtractorFactoryTest extends TestCase
{
    private ExtractorFactory $factory;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new ExtractorFactory(
            new EncodingDetector(),
            new ElementClassifier(),
            new MetricsCollector(),
        );

        $this->fixturesPath = base_path('tests/Fixtures/extractors');
    }

    public function testCreatesExtractorByMimeType(): void
    {
        $extractor = $this->factory->create('text/plain');

        $this->assertInstanceOf(ExtractorInterface::class, $extractor);
        $this->assertInstanceOf(TxtExtractor::class, $extractor);
    }

    public function testCreatesExtractorFromFile(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $extractor = $this->factory->createFromFile($filePath);

        $this->assertInstanceOf(ExtractorInterface::class, $extractor);
        $this->assertInstanceOf(TxtExtractor::class, $extractor);
    }

    public function testThrowsExceptionForUnsupportedMimeType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No extractor found for MIME type');

        $this->factory->create('application/unsupported');
    }

    public function testThrowsExceptionForNonExistingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $this->factory->createFromFile('/non/existing/file.txt');
    }

    public function testReturnsSupportedMimeTypes(): void
    {
        $mimeTypes = $this->factory->getSupportedMimeTypes();

        $this->assertIsArray($mimeTypes);
        $this->assertContains('text/plain', $mimeTypes);
        $this->assertContains('text/txt', $mimeTypes);
        $this->assertContains('application/txt', $mimeTypes);
        $this->assertContains('application/pdf', $mimeTypes); // Now supported
        $this->assertContains('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $mimeTypes); // DOCX support
    }

    public function testSupportsMimeType(): void
    {
        $this->assertTrue($this->factory->supports('text/plain'));
        $this->assertTrue($this->factory->supports('text/txt'));
        $this->assertTrue($this->factory->supports('application/pdf')); // Now supported
        $this->assertFalse($this->factory->supports('application/unsupported'));
    }

    public function testRegistersNewExtractor(): void
    {
        // This is a mock class name - in real implementation we'd need actual class
        $this->factory->register('application/test', TxtExtractor::class);

        $supportedTypes = $this->factory->getSupportedMimeTypes();
        $this->assertContains('application/test', $supportedTypes);
    }

    public function testThrowsExceptionForInvalidExtractorClass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Extractor class must implement ExtractorInterface');

        /** @var class-string<ExtractorInterface> $invalidClass */
        $invalidClass = stdClass::class; // @phpstan-ignore-line
        $this->factory->register('application/test', $invalidClass);
    }
}
