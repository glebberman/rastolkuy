<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use App\Services\Parser\Extractors\DTOs\ExtractedDocument;

class ExtractorTestResponse extends JsonResponse
{
    public function __construct(ExtractedDocument $result, string $testType = 'basic')
    {
        $elements = [];
        foreach ($result->elements as $element) {
            $elements[] = [
                'type' => $element->type,
                'content' => mb_substr($element->content, 0, 100) . (mb_strlen($element->content) > 100 ? '...' : ''),
                'confidence' => $element->getConfidenceScore(),
            ];
        }

        $data = [
            'status' => 'success',
            'test_type' => $testType,
            'extraction_time' => round($result->extractionTime, 4) . 's',
            'elements_count' => $result->getElementsCount(),
            'file_info' => [
                'mime_type' => $result->mimeType,
                'encoding' => $result->metadata['encoding'] ?? 'unknown',
                'file_size' => $result->metadata['file_size'] ?? 0,
                'line_count' => $result->metadata['line_count'] ?? 0,
            ],
            'elements' => $elements,
            'metrics' => $result->metrics,
        ];

        parent::__construct($data, 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}