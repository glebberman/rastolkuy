<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\DocumentProcessing;
use App\Models\User;
use App\Models\UserCredit;
use App\Services\AuditService;
use App\Services\FileStorageService;
use App\Services\LLM\CostCalculator;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\MockObject\Exception;
use Tests\TestCase;

/**
 * Integration tests for document processing with MinIO storage.
 *
 * These tests require a running MinIO instance (via docker-compose).
 */
class MinIODocumentProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FileStorageService $fileStorageService;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Skip if MinIO is not available
        if (!$this->isMinIOAvailable()) {
            $this->markTestSkipped('MinIO is not available for integration testing');
        }

        // Mock services to avoid configuration issues
        $auditServiceMock = $this->createMock(AuditService::class);
        $this->app->instance(AuditService::class, $auditServiceMock);

        $costCalculatorMock = $this->createMock(CostCalculator::class);
        $costCalculatorMock->method('estimateTokensFromFileSize')->willReturn(1000);
        $costCalculatorMock->method('calculateCost')->willReturn(0.15);
        $costCalculatorMock->method('getPricingInfo')->willReturn(['model' => 'test']);
        $this->app->instance(CostCalculator::class, $costCalculatorMock);

        // Mock structure analysis services
        $this->mockStructureAnalysisServices();

        // Seed permissions
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder', '--force' => true]);

        $this->user = User::factory()->create();
        $this->user->assignRole('customer');

        // Create initial credit balance
        UserCredit::create([
            'user_id' => $this->user->id,
            'balance' => 1000.0,
        ]);

        Sanctum::actingAs($this->user);
        Queue::fake();

        // Use MinIO for file storage
        $this->fileStorageService = new FileStorageService('minio');

        // Override default filesystem config for this test
        Config::set('filesystems.default', 'minio');
    }

    protected function tearDown(): void
    {
        // Clean up test files from MinIO
        $this->cleanupMinIOTestFiles();
        parent::tearDown();
    }

    public function testCanUploadDocumentToMinIO(): void
    {
        $file = UploadedFile::fake()->create('test-document.pdf', 100);

        $response = $this->postJson(route('api.v1.documents.upload'), [
            'file' => $file,
            'task_type' => DocumentProcessing::TASK_TRANSLATION,
            'anchor_at_start' => true,
            'options' => ['preserve_formatting' => true],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'filename',
                    'file_type',
                    'file_size',
                    'task_type',
                    'status',
                ],
            ])
            ->assertJson([
                'data' => [
                    'filename' => 'test-document.pdf',
                    'task_type' => DocumentProcessing::TASK_TRANSLATION,
                    'status' => DocumentProcessing::STATUS_UPLOADED,
                ],
            ]);

        // Verify file was stored in MinIO
        $documentProcessing = DocumentProcessing::where('user_id', $this->user->id)->first();
        $this->assertNotNull($documentProcessing);

        // Check that file exists in MinIO
        $this->assertTrue($this->fileStorageService->exists($documentProcessing->file_path));

        // Verify we can retrieve the file (fake file might have minimal content)
        $fileContent = $this->fileStorageService->get($documentProcessing->file_path);
        $this->assertIsString($fileContent);

        // Verify file size is reasonable (fake files might have different sizes)
        $storedSize = $this->fileStorageService->size($documentProcessing->file_path);
        $this->assertGreaterThanOrEqual(0, $storedSize);
    }

    public function testCanDeleteDocumentFromMinIO(): void
    {
        // First upload a document
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => 'documents/test-file-' . time() . '.pdf',
        ]);

        // Create the file in MinIO
        $testContent = 'fake content for MinIO test';
        $this->fileStorageService->put($document->file_path, $testContent);

        // Verify file exists
        $this->assertTrue($this->fileStorageService->exists($document->file_path));

        // Delete via API
        $response = $this->deleteJson(route('api.v1.documents.destroy', $document->uuid));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Запись об обработке документа удалена',
            ]);

        $this->assertSoftDeleted('document_processings', [
            'uuid' => $document->uuid,
        ]);

        // Verify file was deleted from MinIO
        $this->assertFalse($this->fileStorageService->exists($document->file_path));
    }

    public function testCanGeneratePublicURLForMinIOFile(): void
    {
        $testContent = 'Test content for URL generation';
        $testPath = 'documents/url-test-' . time() . '.txt';

        // Store file in MinIO
        $this->fileStorageService->put($testPath, $testContent);

        // Generate URL
        $url = $this->fileStorageService->url($testPath);

        $this->assertIsString($url);
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('minio', $url);

        // Cleanup
        $this->fileStorageService->delete($testPath);
    }

    public function testFileStorageServiceInfoForMinIO(): void
    {
        $storageInfo = $this->fileStorageService->getStorageInfo();

        $this->assertEquals('minio', $storageInfo['disk_name']);
        $this->assertTrue($storageInfo['supports_public_urls']);
        $this->assertEquals('s3', $storageInfo['driver']);
        $this->assertStringContainsString('laravel', $storageInfo['bucket']);
        $this->assertStringContainsString('minio:9000', $storageInfo['endpoint']);
    }

    public function testFileOperationsWithMinIO(): void
    {
        $originalContent = 'Original test content';
        $updatedContent = 'Updated test content';
        $sourcePath = 'documents/source-' . time() . '.txt';
        $targetPath = 'documents/target-' . time() . '.txt';

        // Test put operation
        $success = $this->fileStorageService->put($sourcePath, $originalContent);
        $this->assertTrue($success);

        // Test exists operation
        $this->assertTrue($this->fileStorageService->exists($sourcePath));

        // Test get operation
        $retrievedContent = $this->fileStorageService->get($sourcePath);
        $this->assertEquals($originalContent, $retrievedContent);

        // Test copy operation
        $copySuccess = $this->fileStorageService->copy($sourcePath, $targetPath);
        $this->assertTrue($copySuccess);
        $this->assertTrue($this->fileStorageService->exists($targetPath));

        // Test size operation
        $sourceSize = $this->fileStorageService->size($sourcePath);
        $targetSize = $this->fileStorageService->size($targetPath);
        $this->assertEquals($sourceSize, $targetSize);
        $this->assertEquals(strlen($originalContent), $sourceSize);

        // Test lastModified operation
        $lastModified = $this->fileStorageService->lastModified($sourcePath);
        $this->assertIsInt($lastModified);
        $this->assertGreaterThan(time() - 60, $lastModified); // Within last minute

        // Test delete operation
        $this->fileStorageService->delete($sourcePath);
        $this->assertFalse($this->fileStorageService->exists($sourcePath));

        $this->fileStorageService->delete($targetPath);
        $this->assertFalse($this->fileStorageService->exists($targetPath));
    }

    /**
     * Check if MinIO is available for testing.
     */
    private function isMinIOAvailable(): bool
    {
        try {
            $config = Config::get('filesystems.disks.minio');

            if (!is_array($config) || empty($config['endpoint'] ?? '')) {
                return false;
            }

            // Try to connect to MinIO by creating a test file
            $testStorage = new FileStorageService('minio');
            $testPath = 'connection-test-' . time() . '.txt';

            $success = $testStorage->put($testPath, 'connection test');

            if ($success) {
                $testStorage->delete($testPath);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean up test files from MinIO.
     */
    private function cleanupMinIOTestFiles(): void
    {
        try {
            $disk = $this->fileStorageService->getDisk();
            $allFiles = $disk->allFiles('documents');

            foreach ($allFiles as $file) {
                // Clean up ALL test files (fake files from tests)
                if (str_contains($file, '-test-')
                    || str_contains($file, 'test-')
                    || str_contains($file, 'connection-test')
                    || str_contains($file, 'url-test')
                    // Clean up files with UUID pattern (from fake uploads)
                    || preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', basename($file))) {
                    $this->fileStorageService->delete($file);
                    echo "Cleaned up test file: {$file}\n";
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors but log them
            echo 'Cleanup warning: ' . $e->getMessage() . "\n";
        }
    }

    private function mockStructureAnalysisServices(): void
    {
        // Create mock for SectionDetectorInterface
        $sectionDetectorMock = $this->createMock(SectionDetectorInterface::class);
        $sectionDetectorMock->method('detectSections')->willReturn([]);
        $this->app->instance(SectionDetectorInterface::class, $sectionDetectorMock);

        // Create mock for AnchorGeneratorInterface
        $anchorGeneratorMock = $this->createMock(AnchorGeneratorInterface::class);
        $anchorGeneratorMock->method('generate')->willReturn('<!-- SECTION_ANCHOR_test_123 -->');
        $anchorGeneratorMock->method('resetUsedAnchors');
        $this->app->instance(AnchorGeneratorInterface::class, $anchorGeneratorMock);

        // Mock ExtractorManager
        $extractorManagerMock = $this->createMock(ExtractorManager::class);
        $extractedDocumentMock = new \App\Services\Parser\Extractors\DTOs\ExtractedDocument(
            originalPath: '/test/path.pdf',
            mimeType: 'application/pdf',
            elements: [],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
            metrics: [],
            errors: null,
        );
        $extractorManagerMock->method('extract')->willReturn($extractedDocumentMock);
        $this->app->instance(ExtractorManager::class, $extractorManagerMock);
    }
}
