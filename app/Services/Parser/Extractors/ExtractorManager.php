<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

readonly class ExtractorManager
{
    public function __construct(
        private ExtractorFactory $factory,
    ) {
    }

    public function extract(string $filePath, ?ExtractionConfig $config = null): ExtractedDocument
    {
        $config ??= ExtractionConfig::createDefault();
        $startTime = microtime(true);

        try {
            Log::info('Starting document extraction', [
                'file' => $filePath,
                'config' => $config,
            ]);

            // Create appropriate extractor
            $extractor = $this->factory->createFromFile($filePath);

            // Validate file before processing
            if (!$extractor->validate($filePath)) {
                throw new InvalidArgumentException("File validation failed: $filePath");
            }

            // Check if processing time might exceed timeout
            $estimatedTime = $extractor->estimateProcessingTime($filePath);

            if ($estimatedTime > $config->timeoutSeconds) {
                Log::warning('Estimated processing time exceeds timeout', [
                    'file' => $filePath,
                    'estimated_time' => $estimatedTime,
                    'timeout' => $config->timeoutSeconds,
                ]);
            }

            // Perform extraction
            $result = $this->executeWithTimeout($extractor, $filePath, $config);

            $totalTime = microtime(true) - $startTime;
            Log::info('Document extraction completed', [
                'file' => $filePath,
                'elements_count' => $result->getElementsCount(),
                'total_time' => $totalTime,
                'has_errors' => $result->hasErrors(),
            ]);

            return $result;
        } catch (Exception $e) {
            $totalTime = microtime(true) - $startTime;
            Log::error('Document extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'total_time' => $totalTime,
            ]);

            throw $e;
        }
    }

    public function extractBatch(array $filePaths, ?ExtractionConfig $config = null): array
    {
        $results = [];
        $config ??= ExtractionConfig::createDefault();

        foreach ($filePaths as $filePath) {
            try {
                $results[$filePath] = $this->extract($filePath, $config);
            } catch (Exception $e) {
                Log::error('Batch extraction failed for file', [
                    'file' => $filePath,
                    'error' => $e->getMessage(),
                ]);

                $results[$filePath] = $e;
            }
        }

        return $results;
    }

    public function supports(string $filePath): bool
    {
        try {
            $this->factory->createFromFile($filePath);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array
    {
        return $this->factory->getSupportedMimeTypes();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFileMetadata(string $filePath): array
    {
        try {
            return $this->factory->createFromFile($filePath)->getMetadata($filePath);
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : null,
            ];
        }
    }

    private function executeWithTimeout(
        ExtractorInterface $extractor,
        string $filePath,
        ExtractionConfig $config,
    ): ExtractedDocument {
        $startTime = microtime(true);
        $timeoutReached = false;

        // Set up timeout handling using signal alarm (if available)
        if (function_exists('pcntl_alarm') && function_exists('pcntl_signal')) {
            pcntl_signal(SIGALRM, function () use (&$timeoutReached): void {
                $timeoutReached = true;
            });
            pcntl_alarm($config->timeoutSeconds);
        }

        try {
            // Execute extraction with periodic timeout checks
            $result = $this->extractWithTimeoutChecks($extractor, $filePath, $config, $startTime);

            // Clear alarm
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }

            return $result;
        } catch (Exception $e) {
            // Clear alarm on exception
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }

            if ($timeoutReached || (microtime(true) - $startTime) > $config->timeoutSeconds) {
                throw new RuntimeException("Extraction timeout exceeded ({$config->timeoutSeconds}s) for file: {$filePath}");
            }

            throw $e;
        }
    }

    private function extractWithTimeoutChecks(
        ExtractorInterface $extractor,
        string $filePath,
        ExtractionConfig $config,
        float $startTime,
    ): ExtractedDocument {
        // Create a wrapper that periodically checks timeout
        $timeoutChecker = function () use ($startTime, $config, $filePath): void {
            if ((microtime(true) - $startTime) > $config->timeoutSeconds) {
                throw new RuntimeException("Extraction timeout exceeded ({$config->timeoutSeconds}s) for file: {$filePath}");
            }
        };

        // For streaming extraction, timeout checks are built-in
        // For regular extraction, we execute and hope it completes within timeout
        $result = $extractor->extract($filePath, $config);

        // Final timeout check
        $timeoutChecker();

        return $result;
    }
}
