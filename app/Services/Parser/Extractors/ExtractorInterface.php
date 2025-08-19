<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;

interface ExtractorInterface
{
    public function supports(string $mimeType): bool;

    public function extract(string $filePath, ?ExtractionConfig $config = null): ExtractedDocument;

    public function validate(string $filePath): bool;

    public function getMetadata(string $filePath): array;

    public function estimateProcessingTime(string $filePath): int;
}
