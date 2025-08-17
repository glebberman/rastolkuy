<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ExtractorManager
{
    public function __construct(
        private readonly ExtractorFactory $factory,
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
                throw new InvalidArgumentException("File validation failed: {$filePath}");
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
            $extractor = $this->factory->createFromFile($filePath);

            return $extractor->getMetadata($filePath);
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
        // For now, we'll just execute normally
        // In the future, this could implement actual timeout handling
        // using pcntl_alarm or similar mechanisms

        $result = $extractor->extract($filePath, $config);

        // Check if extraction took too long
        if ($result->extractionTime > $config->timeoutSeconds) {
            Log::warning('Extraction exceeded configured timeout', [
                'file' => $filePath,
                'extraction_time' => $result->extractionTime,
                'timeout' => $config->timeoutSeconds,
            ]);
        }

        return $result;
    }
}
