<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DocumentProcessing;
use App\Services\FileStorageService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigrateStorageFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:migrate-files
                            {--from=local : Source storage disk}
                            {--to=minio : Target storage disk}
                            {--dry-run : Show what would be migrated without actually doing it}
                            {--batch-size=50 : Number of files to process in each batch}
                            {--continue-on-error : Continue migration even if some files fail}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate files from one storage disk to another (e.g., from local to MinIO)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromDisk = $this->option('from');
        $toDisk = $this->option('to');
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $continueOnError = $this->option('continue-on-error');

        if (!is_string($fromDisk) || !is_string($toDisk)) {
            $this->error('From and to disk options must be strings');
            return self::FAILURE;
        }

        $this->info("Storage Migration Tool");
        $this->info("From: {$fromDisk}");
        $this->info("To: {$toDisk}");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No files will be actually moved");
        }

        // Validate storage disks exist
        if (!$this->validateStorageDisks($fromDisk, $toDisk)) {
            return self::FAILURE;
        }

        // Get file storage services
        $fromStorage = new FileStorageService($fromDisk);
        $toStorage = new FileStorageService($toDisk);

        // Get all document processing records that need migration
        $documentsQuery = DocumentProcessing::whereNotNull('file_path');
        $totalDocuments = $documentsQuery->count();

        if ($totalDocuments === 0) {
            $this->info('No documents found to migrate.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalDocuments} documents to process");

        if (!$dryRun && !$this->confirm('Do you want to proceed with the migration?')) {
            $this->info('Migration cancelled.');
            return self::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($totalDocuments);
        $progressBar->start();

        $documentsQuery->chunk($batchSize, function ($documents) use (
            $fromStorage,
            $toStorage,
            $dryRun,
            $continueOnError,
            &$migrated,
            &$skipped,
            &$failed,
            $progressBar
        ) {
            foreach ($documents as $document) {
                $progressBar->advance();

                try {
                    $result = $this->migrateDocument(
                        $document,
                        $fromStorage,
                        $toStorage,
                        $dryRun
                    );

                    if ($result === 'migrated') {
                        $migrated++;
                    } elseif ($result === 'skipped') {
                        $skipped++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    
                    Log::error('Failed to migrate document file', [
                        'document_uuid' => $document->uuid,
                        'file_path' => $document->file_path,
                        'error' => $e->getMessage(),
                    ]);

                    if (!$continueOnError) {
                        $progressBar->finish();
                        $this->error("\nMigration failed for document {$document->uuid}: {$e->getMessage()}");
                        return false;
                    }
                }
            }

            return true;
        });

        $progressBar->finish();

        // Summary
        $this->newLine(2);
        $this->info("Migration Summary:");
        $this->info("  Migrated: {$migrated}");
        $this->info("  Skipped: {$skipped}");
        $this->info("  Failed: {$failed}");

        if ($failed > 0) {
            $this->warn("Some files failed to migrate. Check the logs for details.");
            return $continueOnError ? self::SUCCESS : self::FAILURE;
        }

        $this->info("Migration completed successfully!");
        return self::SUCCESS;
    }

    /**
     * Validate that storage disks exist and are accessible.
     */
    private function validateStorageDisks(string $fromDisk, string $toDisk): bool
    {
        try {
            // Check if disks are configured
            $fromConfig = config("filesystems.disks.{$fromDisk}");
            $toConfig = config("filesystems.disks.{$toDisk}");

            if (!$fromConfig) {
                $this->error("Source storage disk '{$fromDisk}' is not configured");
                return false;
            }

            if (!$toConfig) {
                $this->error("Target storage disk '{$toDisk}' is not configured");
                return false;
            }

            // Test connectivity
            $fromStorage = Storage::disk($fromDisk);
            $toStorage = Storage::disk($toDisk);

            // Try to create a test file to validate write access
            $testFile = 'storage-migration-test-' . now()->timestamp . '.txt';
            $testContent = 'This is a test file for storage migration validation';

            $toStorage->put($testFile, $testContent);
            
            if (!$toStorage->exists($testFile)) {
                $this->error("Cannot write to target storage disk '{$toDisk}'");
                return false;
            }

            // Clean up test file
            $toStorage->delete($testFile);

            $this->info("✓ Storage disks validated successfully");
            return true;
        } catch (Exception $e) {
            $this->error("Storage validation failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Migrate a single document file.
     */
    private function migrateDocument(
        DocumentProcessing $document,
        FileStorageService $fromStorage,
        FileStorageService $toStorage,
        bool $dryRun
    ): string {
        $filePath = $document->file_path;

        // Check if source file exists
        if (!$fromStorage->exists($filePath)) {
            $this->warn("Source file does not exist: {$filePath}");
            return 'skipped';
        }

        // Check if target file already exists
        if ($toStorage->exists($filePath)) {
            $this->warn("Target file already exists: {$filePath}");
            return 'skipped';
        }

        if ($dryRun) {
            $this->line("Would migrate: {$filePath}");
            return 'migrated';
        }

        // Copy file content
        $fileContent = $fromStorage->get($filePath);
        $success = $toStorage->put($filePath, $fileContent);

        if (!$success) {
            throw new Exception("Failed to write file to target storage: {$filePath}");
        }

        // Verify file size matches (only if put was successful)
        $sourceSize = $fromStorage->size($filePath);
        $targetSize = $toStorage->size($filePath);

        if ($sourceSize !== $targetSize) {
            throw new Exception("File size mismatch for {$filePath}: source={$sourceSize}, target={$targetSize}");
        }

        $this->line("✓ Migrated: {$filePath}");
        return 'migrated';
    }
}