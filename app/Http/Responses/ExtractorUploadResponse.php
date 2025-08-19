<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use Illuminate\Http\UploadedFile;

class ExtractorUploadResponse extends JsonResponse
{
    public function __construct(ExtractedDocument $result, UploadedFile $file, string $configType)
    {
        // Format elements for display
        $elements = [];
        foreach ($result->elements as $element) {
            $elements[] = [
                'type' => $element->type,
                'content' => $element->content,
                'confidence' => round($element->getConfidenceScore(), 2),
                'page' => $element->pageNumber,
                'metadata' => $element->metadata,
            ];
        }

        $data = [
            'status' => 'success',
            'file_info' => [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $result->mimeType,
                'encoding' => $result->metadata['encoding'] ?? 'unknown',
                'line_count' => $result->metadata['line_count'] ?? 0,
                'processing_mode' => $result->metadata['processing_mode'] ?? 'regular',
            ],
            'extraction' => [
                'time' => round($result->extractionTime, 4),
                'elements_count' => count($elements),
                'config_used' => $configType,
            ],
            'elements' => $elements,
            'metrics' => $result->metrics,
        ];

        parent::__construct($data, 200, [], JSON_UNESCAPED_UNICODE);
    }
}