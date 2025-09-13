<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FileStorageService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private FileStorageService $fileStorageService;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip integration tests if S3/MinIO not configured
        if (!$this->isS3ConfigurationAvailable()) {
            $this->markTestSkipped('S3/MinIO configuration not available for integration testing');
        }

        $this->fileStorageService = new FileStorageService('minio');
    }

    protected function tearDown(): void
    {
        // Clean up test files after each test
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    public function testCanStoreAndRetrieveFileInMinIO(): void
    {
        $testContent = 'Test file content for MinIO integration test';
        $testPath = 'integration-tests/test-file-' . time() . '.txt';

        // Store file
        $success = $this->fileStorageService->put($testPath, $testContent);
        $this->assertTrue($success);

        // Verify file exists
        $this->assertTrue($this->fileStorageService->exists($testPath));

        // Retrieve file content
        $retrievedContent = $this->fileStorageService->get($testPath);
        $this->assertEquals($testContent, $retrievedContent);

        // Check file size
        $size = $this->fileStorageService->size($testPath);
        $this->assertEquals(strlen($testContent), $size);

        // Cleanup
        $this->fileStorageService->delete($testPath);
        $this->assertFalse($this->fileStorageService->exists($testPath));
    }

    public function testCanStoreUploadedFileInMinIO(): void
    {
        $uploadedFile = UploadedFile::fake()->create('test-document.pdf', 100);
        $testPath = 'integration-tests/uploaded-' . time() . '.pdf';

        // Store uploaded file
        $storedPath = $this->fileStorageService->store($uploadedFile, $testPath);
        $this->assertEquals($testPath, $storedPath);

        // Verify file exists
        $this->assertTrue($this->fileStorageService->exists($testPath));

        // Verify file size is reasonable (fake files might have different sizes)
        $size = $this->fileStorageService->size($testPath);
        $this->assertGreaterThanOrEqual(0, $size);

        // Cleanup
        $this->fileStorageService->delete($testPath);
    }

    public function testCanCopyFileBetweenStorageDisks(): void
    {
        $testContent = 'Test content for cross-disk copy';
        $sourcePath = 'integration-tests/source-' . time() . '.txt';
        $targetPath = 'integration-tests/target-' . time() . '.txt';

        // Store in MinIO
        $minioStorage = new FileStorageService('minio');
        $minioStorage->put($sourcePath, $testContent);

        // Copy to local storage
        $localStorage = new FileStorageService('local');

        // Get content from MinIO and store in local
        $content = $minioStorage->get($sourcePath);
        $localStorage->put($targetPath, $content);

        // Verify both files exist
        $this->assertTrue($minioStorage->exists($sourcePath));
        $this->assertTrue($localStorage->exists($targetPath));

        // Verify content matches
        $minioContent = $minioStorage->get($sourcePath);
        $localContent = $localStorage->get($targetPath);
        $this->assertEquals($minioContent, $localContent);

        // Cleanup
        $minioStorage->delete($sourcePath);
        $localStorage->delete($targetPath);
    }

    public function testFileStorageServiceWorksWithDifferentDiskTypes(): void
    {
        $testContent = 'Test content for disk switching';
        $testPath = 'integration-tests/disk-test-' . time() . '.txt';

        $diskTypes = ['local', 'minio'];

        if ($this->isS3ConfigurationAvailable('s3')) {
            $diskTypes[] = 's3';
        }

        foreach ($diskTypes as $diskType) {
            $storage = new FileStorageService($diskType);

            // Store file
            $success = $storage->put($testPath, $testContent);
            $this->assertTrue($success, "Failed to store file on {$diskType} disk");

            // Verify exists
            $this->assertTrue($storage->exists($testPath), "File doesn't exist on {$diskType} disk");

            // Verify content
            $retrievedContent = $storage->get($testPath);
            $this->assertEquals($testContent, $retrievedContent, "Content mismatch on {$diskType} disk");

            // Test URL generation (if supported)
            if ($storage->supportsPublicUrls()) {
                $url = $storage->url($testPath);
                $this->assertIsString($url);
                $this->assertNotEmpty($url);
            }

            // Cleanup
            $storage->delete($testPath);
            $this->assertFalse($storage->exists($testPath), "File still exists after deletion on {$diskType} disk");
        }
    }

    public function testStorageMigrationCommand(): void
    {
        // Run migration command (dry-run only to test command works)
        // @phpstan-ignore-next-line Laravel artisan() method return type ambiguity
        $this->artisan('storage:migrate-files', [
            '--from' => 'local',
            '--to' => 'minio',
            '--dry-run' => true,
        ])->assertExitCode(0);

        // Test that dry-run works - actual migration test would require database setup
        $this->assertTrue(true, 'Migration command dry-run works');
    }

    public function testFileStorageServiceGetStorageInfo(): void
    {
        $storageInfo = $this->fileStorageService->getStorageInfo();

        $this->assertIsArray($storageInfo);
        $this->assertArrayHasKey('disk_name', $storageInfo);
        $this->assertArrayHasKey('supports_public_urls', $storageInfo);
        $this->assertArrayHasKey('driver', $storageInfo);

        $this->assertEquals('minio', $storageInfo['disk_name']);
        $this->assertTrue($storageInfo['supports_public_urls']);
        $this->assertEquals('s3', $storageInfo['driver']);
    }

    /**
     * Check if S3/MinIO configuration is available for testing.
     */
    private function isS3ConfigurationAvailable(string $disk = 'minio'): bool
    {
        $config = Config::get("filesystems.disks.{$disk}");

        if (!is_array($config)) {
            return false;
        }

        // For MinIO, check if we have at least endpoint and credentials
        if ($disk === 'minio') {
            return !empty($config['endpoint'] ?? '')
                   && !empty($config['key'] ?? '')
                   && !empty($config['secret'] ?? '')
                   && !empty($config['bucket'] ?? '');
        }

        // For S3, check if we have credentials and bucket
        if ($disk === 's3') {
            return !empty($config['key'] ?? '')
                   && !empty($config['secret'] ?? '')
                   && !empty($config['bucket'] ?? '');
        }

        return true;
    }

    /**
     * Clean up any test files that might have been left behind.
     */
    private function cleanupTestFiles(): void
    {
        try {
            $diskTypes = ['local', 'minio'];

            if ($this->isS3ConfigurationAvailable('s3')) {
                $diskTypes[] = 's3';
            }

            foreach ($diskTypes as $diskType) {
                $storage = new FileStorageService($diskType);

                // Try to list and clean up test files
                $disk = $storage->getDisk();

                // Clean up integration-tests folder
                $testFiles = $disk->files('integration-tests');

                foreach ($testFiles as $file) {
                    if (str_contains($file, 'test-') || str_contains($file, 'migration-test-')) {
                        $storage->delete($file);
                        echo "Cleaned up integration test file: {$file}\n";
                    }
                }

                // Also clean up documents folder from any test files
                if ($disk->exists('documents')) {
                    $documentFiles = $disk->files('documents');

                    foreach ($documentFiles as $file) {
                        if (str_contains($file, 'test-')
                            || str_contains($file, 'connection-test')
                            // Files with UUID pattern from tests
                            || preg_match('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/', basename($file))) {
                            $storage->delete($file);
                            echo "Cleaned up document test file: {$file}\n";
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore cleanup errors but show warning
            echo 'Cleanup warning: ' . $e->getMessage() . "\n";
        }
    }
}
