<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FileStorageService;
use Exception;
use Illuminate\Console\Command;

class CleanupTestFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:cleanup-test-files 
                            {--disk=* : Storage disks to clean (default: all configured)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up test files from storage disks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disks = $this->option('disk');
        $dryRun = $this->option('dry-run');

        if (empty($disks)) {
            $disks = ['local', 'minio', 's3'];
        }

        $this->info('🧹 Cleaning up test files from storage...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be actually deleted');
        }

        $totalCleaned = 0;

        foreach ($disks as $diskName) {
            if (!is_string($diskName)) {
                continue;
            }

            try {
                $cleaned = $this->cleanupDisk($diskName, $dryRun);
                $totalCleaned += $cleaned;
            } catch (Exception $e) {
                $this->error("Failed to cleanup disk '{$diskName}': {$e->getMessage()}");
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("✅ Found {$totalCleaned} test files that would be cleaned up");
        } else {
            $this->info("✅ Cleaned up {$totalCleaned} test files successfully");
        }

        return self::SUCCESS;
    }

    private function cleanupDisk(string $diskName, bool $dryRun): int
    {
        try {
            $storage = new FileStorageService($diskName);
            $disk = $storage->getDisk();

            $this->info("Checking disk: {$diskName}");

            $cleaned = 0;
            $foldersToCheck = ['documents', 'integration-tests', ''];

            foreach ($foldersToCheck as $folder) {
                $files = $folder ? $disk->files($folder) : $disk->allFiles();

                foreach ($files as $file) {
                    if ($this->isTestFile($file)) {
                        if ($dryRun) {
                            $this->line("  Would delete: {$file}");
                        } else {
                            $success = $storage->delete($file);

                            if ($success) {
                                $this->line("  ✓ Deleted: {$file}");
                            } else {
                                $this->warn("  ✗ Failed to delete: {$file}");
                            }
                        }
                        ++$cleaned;
                    }
                }
            }

            if ($cleaned === 0) {
                $this->line("  No test files found on {$diskName}");
            }

            return $cleaned;
        } catch (Exception $e) {
            // Check if it's a config issue (disk doesn't exist)
            $config = config("filesystems.disks.{$diskName}");

            if (!$config) {
                $this->warn("  Disk '{$diskName}' not configured - skipping");

                return 0;
            }

            // Check for missing required config values (like bucket)
            if (($diskName === 's3' || $diskName === 's3-custom') && is_array($config) && empty($config['bucket'] ?? '')) {
                $this->warn("  Disk '{$diskName}' missing bucket configuration - skipping");

                return 0;
            }

            // Check if it's a connection issue
            if (str_contains($e->getMessage(), 'connection')
                || str_contains($e->getMessage(), 'resolve')
                || str_contains($e->getMessage(), 'timeout')) {
                $this->warn("  Disk '{$diskName}' not available - skipping");

                return 0;
            }

            throw $e;
        }
    }

    private function isTestFile(string $filePath): bool
    {
        $filename = basename($filePath);

        return
            // Test files with 'test' in name
            str_contains($filename, 'test-')
            || str_contains($filename, '-test-')
            || str_contains($filename, 'connection-test')
            || str_contains($filename, 'migration-test')
            || str_contains($filename, 'url-test')
            || str_contains($filename, 'integration-test')

            // UUID pattern files (likely from fake uploads in tests)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(pdf|docx|txt)$/i', $filename)

            // Files in test directories
            || str_contains($filePath, '/integration-tests/')
            || str_contains($filePath, '/test-files/');
    }
}
