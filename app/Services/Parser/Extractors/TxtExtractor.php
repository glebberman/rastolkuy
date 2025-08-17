<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Parser\Extractors\Elements\ListElement;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Parser\Extractors\Elements\TextElement;
use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use InvalidArgumentException;
use RuntimeException;

class TxtExtractor implements ExtractorInterface
{
    public function __construct(
        private readonly EncodingDetector $encodingDetector,
        private readonly ElementClassifier $classifier,
        private readonly MetricsCollector $metrics,
    ) {
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'text/plain',
            'text/txt',
            'application/txt',
        ]);
    }

    public function extract(string $filePath, ?ExtractionConfig $config = null): ExtractedDocument
    {
        $startTime = microtime(true);
        $config ??= ExtractionConfig::createDefault();

        $this->validate($filePath);

        // Determine encoding and read content
        $encoding = $this->encodingDetector->detect($filePath);
        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        $content = $this->encodingDetector->convertToUtf8($content, $encoding);

        // Parse content into elements
        $elements = $this->parseTextContent($content, $config);

        $extractionTime = microtime(true) - $startTime;
        $this->metrics->record('txt_extraction', $extractionTime, strlen($content));

        return new ExtractedDocument(
            originalPath: $filePath,
            mimeType: 'text/plain',
            elements: $elements,
            metadata: [
                'encoding' => $encoding,
                'file_size' => strlen($content),
                'line_count' => substr_count($content, "\n") + 1,
            ],
            totalPages: 1,
            extractionTime: $extractionTime,
            metrics: $this->metrics->getMetrics(),
        );
    }

    public function validate(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException("File is not readable: {$filePath}");
        }

        $fileSize = filesize($filePath);

        if ($fileSize === false || $fileSize === 0) {
            throw new InvalidArgumentException("File is empty or size cannot be determined: {$filePath}");
        }

        // Check if file is too large (50MB limit)
        if ($fileSize > 50 * 1024 * 1024) {
            throw new InvalidArgumentException("File is too large. Maximum size is 50MB: {$filePath}");
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(string $filePath): array
    {
        $this->validate($filePath);

        $fileSize = filesize($filePath);
        $encoding = $this->encodingDetector->detect($filePath);

        return [
            'file_size' => $fileSize,
            'encoding' => $encoding,
            'mime_type' => 'text/plain',
            'estimated_processing_time' => $this->estimateProcessingTime($filePath),
        ];
    }

    public function estimateProcessingTime(string $filePath): int
    {
        $fileSize = filesize($filePath);

        if ($fileSize === false) {
            return 1;
        }

        // Estimate: ~1MB per second
        return max(1, (int) ceil($fileSize / (1024 * 1024)));
    }

    /**
     * @return array<Elements\DocumentElement>
     */
    private function parseTextContent(string $content, ExtractionConfig $config): array
    {
        $elements = [];
        $paragraphs = $this->splitIntoParagraphs($content);

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if (empty($paragraph)) {
                continue;
            }

            // Detect lists
            if ($this->isListContent($paragraph)) {
                $listItems = $this->parseListItems($paragraph);
                $listType = $this->detectListType($paragraph);
                $elements[] = new ListElement(
                    items: $listItems,
                    listType: $listType,
                    metadata: ['confidence' => $this->classifier->getConfidenceScore('list', $paragraph)],
                );

                continue;
            }

            // Classify individual paragraph
            $type = $this->classifier->classify($paragraph);
            $confidence = $this->classifier->getConfidenceScore($type, $paragraph);

            switch ($type) {
                case 'header':
                    $level = $this->classifier->determineHeaderLevel($paragraph);
                    $elements[] = new HeaderElement(
                        content: $paragraph,
                        level: $level,
                        metadata: ['confidence' => $confidence],
                    );

                    break;

                case 'paragraph':
                    $elements[] = new ParagraphElement(
                        content: $paragraph,
                        metadata: ['confidence' => $confidence],
                    );

                    break;

                default:
                    $elements[] = new TextElement(
                        content: $paragraph,
                        metadata: ['confidence' => $confidence],
                    );

                    break;
            }
        }

        return $elements;
    }

    /**
     * @return array<string>
     */
    private function splitIntoParagraphs(string $content): array
    {
        // Split by double newlines (paragraph breaks)
        $paragraphs = preg_split('/\n\s*\n/', $content);

        if ($paragraphs === false) {
            return [$content];
        }

        return array_filter($paragraphs, fn (string $p) => !empty(trim($p)));
    }

    private function isListContent(string $content): bool
    {
        $lines = explode("\n", $content);
        $listLines = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^[-*•]\s+|^\d+\.\s+|^[a-z]\)\s+/', $line)) {
                ++$listLines;
            }
        }

        return $listLines >= 2 && $listLines / count($lines) > 0.5;
    }

    /**
     * @return array<string>
     */
    private function parseListItems(string $content): array
    {
        $lines = explode("\n", $content);
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Remove list markers
            $item = preg_replace('/^[-*•]\s+|^\d+\.\s+|^[a-z]\)\s+/', '', $line);

            if ($item !== null) {
                $items[] = trim($item);
            }
        }

        return $items;
    }

    private function detectListType(string $content): string
    {
        if (preg_match('/^\d+\.\s+/', $content)) {
            return 'ordered';
        }

        return 'unordered';
    }
}
