<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FileStorageService;
use Exception;
use Illuminate\Console\Command;

class TestStorageConnection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:test-connection 
                            {disk=minio : Storage disk to test}';

    /**
     * The console command description.
     */
    protected $description = 'Test connection to storage disk (MinIO, S3, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diskName = $this->argument('disk');
        
        if (!is_string($diskName)) {
            $this->error('Invalid disk name');
            return self::FAILURE;
        }

        $this->info("Testing connection to '{$diskName}' storage disk...");

        try {
            $fileStorageService = new FileStorageService($diskName);
            
            // Test basic connection and configuration
            $storageInfo = $fileStorageService->getStorageInfo();
            $this->info('Storage Configuration:');
            $this->table(
                ['Property', 'Value'],
                [
                    ['Disk Name', $storageInfo['disk_name']],
                    ['Driver', $storageInfo['driver']],
                    ['Bucket', $storageInfo['bucket'] ?? 'N/A'],
                    ['Endpoint', $storageInfo['endpoint'] ?? 'N/A'],
                    ['Supports Public URLs', $storageInfo['supports_public_urls'] ? 'Yes' : 'No'],
                ]
            );

            // Test file operations
            $this->info("\nTesting file operations...");
            
            $testFileName = 'connection-test-' . time() . '.txt';
            $testContent = 'This is a test file created at ' . now()->toISOString();

            // Test write
            $this->info('1. Testing file write...');
            $success = $fileStorageService->put($testFileName, $testContent);
            
            if (!$success) {
                throw new Exception('Failed to write test file');
            }
            $this->info('✓ File write successful');

            // Test exists
            $this->info('2. Testing file existence check...');
            if (!$fileStorageService->exists($testFileName)) {
                throw new Exception('File existence check failed');
            }
            $this->info('✓ File exists check successful');

            // Test read
            $this->info('3. Testing file read...');
            $retrievedContent = $fileStorageService->get($testFileName);
            
            if ($retrievedContent !== $testContent) {
                throw new Exception('Content mismatch - read operation failed');
            }
            $this->info('✓ File read successful');

            // Test size
            $this->info('4. Testing file size check...');
            $size = $fileStorageService->size($testFileName);
            $expectedSize = strlen($testContent);
            
            if ($size !== $expectedSize) {
                throw new Exception("Size mismatch: expected {$expectedSize}, got {$size}");
            }
            $this->info("✓ File size check successful ({$size} bytes)");

            // Test URL generation (if supported)
            if ($fileStorageService->supportsPublicUrls()) {
                $this->info('5. Testing URL generation...');
                $url = $fileStorageService->url($testFileName);
                
                if (empty($url)) {
                    throw new Exception('URL generation failed');
                }
                $this->info('✓ URL generation successful');
                $this->line("   URL: {$url}");
            } else {
                $this->info('5. Skipping URL generation (not supported)');
            }

            // Test copy
            $this->info('6. Testing file copy...');
            $copyFileName = 'connection-test-copy-' . time() . '.txt';
            $copySuccess = $fileStorageService->copy($testFileName, $copyFileName);
            
            if (!$copySuccess) {
                throw new Exception('File copy failed');
            }
            
            if (!$fileStorageService->exists($copyFileName)) {
                throw new Exception('Copied file does not exist');
            }
            $this->info('✓ File copy successful');

            // Test delete
            $this->info('7. Testing file deletion...');
            $fileStorageService->delete($testFileName);
            $fileStorageService->delete($copyFileName);
            
            // PHPStan: Using variables to avoid false positive about always true conditions
            $testFileExists = $fileStorageService->exists($testFileName);
            $copyFileExists = $fileStorageService->exists($copyFileName);
            
            // @phpstan-ignore-next-line PHPStan false positive - files may not be deleted if deletion fails
            if ($testFileExists || $copyFileExists) {
                throw new Exception('File deletion failed');
            }
            // @phpstan-ignore-next-line PHPStan false positive - this line is reachable if deletion succeeds
            $this->info('✓ File deletion successful');

            $this->newLine();
            $this->info("✅ All tests passed! Storage disk '{$diskName}' is working correctly.");
            
            return self::SUCCESS;
            
        } catch (Exception $e) {
            $this->newLine();
            $this->error("❌ Storage test failed: {$e->getMessage()}");
            
            if ($this->option('verbose')) {
                $this->error("Exception: " . get_class($e));
                $this->error("File: {$e->getFile()}");
                $this->error("Line: {$e->getLine()}");
            }
            
            return self::FAILURE;
        }
    }
}