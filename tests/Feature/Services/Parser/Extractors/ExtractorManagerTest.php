<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Parser\Extractors;

use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use App\Services\Parser\Extractors\ExtractorFactory;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use Exception;
use InvalidArgumentException;
use Tests\TestCase;

class ExtractorManagerTest extends TestCase
{
    private ExtractorManager $manager;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $factory = new ExtractorFactory(
            new EncodingDetector(),
            new ElementClassifier(),
            new MetricsCollector(),
        );

        $this->manager = new ExtractorManager($factory);
        $this->fixturesPath = base_path('tests/Fixtures/extractors');
    }

    public function testExtractsSingleFile(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $result = $this->manager->extract($filePath);

        $this->assertEquals('text/plain', $result->mimeType);
        $this->assertEquals($filePath, $result->originalPath);
        $this->assertGreaterThan(0, $result->getElementsCount());
        $this->assertGreaterThan(0, $result->extractionTime);
    }

    public function testExtractsWithCustomConfig(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $config = ExtractionConfig::createFast();

        $result = $this->manager->extract($filePath, $config);

        $this->assertEquals('text/plain', $result->mimeType);
        $this->assertGreaterThan(0, $result->getElementsCount());
    }

    public function testExtractsBatchFiles(): void
    {
        $filePaths = [
            $this->fixturesPath . '/simple.txt',
            $this->fixturesPath . '/encoding_test.txt',
        ];

        $results = $this->manager->extractBatch($filePaths);

        $this->assertCount(2, $results);

        foreach ($filePaths as $filePath) {
            $this->assertArrayHasKey($filePath, $results);
            $this->assertObjectHasProperty('mimeType', $results[$filePath]);
        }
    }

    public function testHandlesBatchExtractionErrors(): void
    {
        $filePaths = [
            $this->fixturesPath . '/simple.txt',
            '/non/existing/file.txt',
        ];

        $results = $this->manager->extractBatch($filePaths);

        $this->assertCount(2, $results);
        $this->assertObjectHasProperty('mimeType', $results[$filePaths[0]]);
        $this->assertInstanceOf(Exception::class, $results[$filePaths[1]]);
    }

    public function testChecksFileSupport(): void
    {
        $txtFile = $this->fixturesPath . '/simple.txt';
        $this->assertTrue($this->manager->supports($txtFile));

        $this->assertFalse($this->manager->supports('/non/existing/file.pdf'));
    }

    public function testReturnsSupportedMimeTypes(): void
    {
        $mimeTypes = $this->manager->getSupportedMimeTypes();

        $this->assertIsArray($mimeTypes);
        $this->assertContains('text/plain', $mimeTypes);
    }

    public function testGetsFileMetadata(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $metadata = $this->manager->getFileMetadata($filePath);

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('file_size', $metadata);
        $this->assertArrayHasKey('encoding', $metadata);
        $this->assertArrayHasKey('mime_type', $metadata);
    }

    public function testHandlesMetadataErrors(): void
    {
        $metadata = $this->manager->getFileMetadata('/non/existing/file.txt');

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('error', $metadata);
        $this->assertArrayHasKey('file_exists', $metadata);
        $this->assertFalse($metadata['file_exists']);
    }

    public function testThrowsExceptionForInvalidFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->extract('/non/existing/file.txt');
    }
}
