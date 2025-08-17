<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\DTOs;

final readonly class ExtractionConfig
{
    public function __construct(
        public bool $preserveFormatting = true,
        public bool $extractImages = false,
        public bool $extractTables = true,
        public bool $detectHeaders = true,
        public int $maxPages = 200,
        public int $timeoutSeconds = 60,
        public bool $enableAsync = true,
        public array $allowedMimeTypes = [],
        public bool $collectMetrics = true,
    ) {
    }

    public static function createFromArray(array $config): self
    {
        return new self(
            preserveFormatting: $config['preserve_formatting'] ?? true,
            extractImages: $config['extract_images'] ?? false,
            extractTables: $config['extract_tables'] ?? true,
            detectHeaders: $config['detect_headers'] ?? true,
            maxPages: $config['max_pages'] ?? 200,
            timeoutSeconds: $config['timeout_seconds'] ?? 60,
            enableAsync: $config['enable_async'] ?? true,
            allowedMimeTypes: $config['allowed_mime_types'] ?? [],
            collectMetrics: $config['collect_metrics'] ?? true,
        );
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public static function createForLargeFiles(): self
    {
        return new self(
            preserveFormatting: false,
            extractImages: false,
            extractTables: true,
            detectHeaders: true,
            maxPages: 500,
            timeoutSeconds: 120,
            enableAsync: true,
            allowedMimeTypes: [],
            collectMetrics: true,
        );
    }

    public static function createFast(): self
    {
        return new self(
            preserveFormatting: false,
            extractImages: false,
            extractTables: false,
            detectHeaders: true,
            maxPages: 100,
            timeoutSeconds: 30,
            enableAsync: false,
            allowedMimeTypes: [],
            collectMetrics: false,
        );
    }
}
