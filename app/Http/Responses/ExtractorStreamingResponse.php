<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use Illuminate\Http\JsonResponse;

class ExtractorStreamingResponse extends JsonResponse
{
    public function __construct(ExtractedDocument $result, ExtractionConfig $config)
    {
        $data = [
            'status' => 'success',
            'processing_mode' => $result->metadata['processing_mode'] ?? 'regular',
            'extraction_time' => round($result->extractionTime, 4) . 's',
            'elements_count' => $result->getElementsCount(),
            'file_size' => $result->metadata['file_size'] ?? 0,
            'config' => [
                'stream_processing' => $config->streamProcessing,
                'chunk_size' => $config->chunkSize,
                'timeout' => $config->timeoutSeconds,
            ],
            'metrics' => $result->metrics,
        ];

        parent::__construct($data, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
