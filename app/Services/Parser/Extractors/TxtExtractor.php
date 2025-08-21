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
use Exception;
use InvalidArgumentException;
use RuntimeException;

readonly class TxtExtractor implements ExtractorInterface
{
    public function __construct(
        private EncodingDetector $encodingDetector,
        private ElementClassifier $classifier,
        private MetricsCollector $metrics,
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

        // Determine encoding
        $encoding = $this->encodingDetector->detect($filePath);

        // Check if we should use streaming for large files
        $fileSize = filesize($filePath);
        $streamThreshold = config('extractors.limits.stream_threshold', 10 * 1024 * 1024);

        if ($fileSize > $streamThreshold && $config->streamProcessing) {
            return $this->extractWithStreaming($filePath, $encoding, $config, $startTime);
        }

        // Regular processing for smaller files
        $content = $this->readFileContent($filePath);

        // Validate content for security
        $this->validateContent($content);

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

        // Check if file is too large (configurable limit)
        $maxSize = config('extractors.limits.max_file_size', 50 * 1024 * 1024);

        if ($fileSize > $maxSize) {
            throw new InvalidArgumentException('File is too large. Maximum size is ' . round($maxSize / 1024 / 1024, 1) . "MB: {$filePath}");
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
        try {
            $paragraphs = preg_split('/\n\s*\n/', $content);

            if ($paragraphs === false) {
                return [$content];
            }
        } catch (Exception) {
            // Fallback to simple line splitting if regex fails
            return explode("\n", $content);
        }

        return array_filter($paragraphs, static fn (string $p) => !empty(trim($p)));
    }

    private function isListContent(string $content): bool
    {
        $lines = explode("\n", $content);
        $listLines = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            try {
                if (preg_match('/^[-*•]\s+|^\d+\.\s+|^[a-z]\)\s+/u', $line)) {
                    ++$listLines;
                }
            } catch (Exception) {
                // Skip invalid regex patterns
                continue;
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
            $item = preg_replace('/^[-*•]\s+|^\d+\.\s+|^[a-z]\)\s+/u', '', $line);

            if ($item !== null) {
                $items[] = trim($item);
            }
        }

        return $items;
    }

    private function detectListType(string $content): string
    {
        if (preg_match('/^\d+\.\s+/u', $content)) {
            return 'ordered';
        }

        return 'unordered';
    }

    /**
     * Safely read file content with error handling.
     */
    private function readFileContent(string $filePath): string
    {
        $content = @file_get_contents($filePath);

        if ($content === false) {
            $error = error_get_last();

            throw new RuntimeException("Cannot read file: {$filePath}. Error: " . ($error['message'] ?? 'Unknown error'));
        }

        return $content;
    }

    /**
     * Validate content for security issues.
     */
    private function validateContent(string $content): void
    {
        // Check for extremely long lines that could cause DoS
        $maxLineLength = config('extractors.limits.max_line_length', 10000);
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            $maxLength = is_numeric($maxLineLength) ? (int) $maxLineLength : 10000;

            if (mb_strlen($line) > $maxLength) {
                throw new InvalidArgumentException('Line ' . ($lineNumber + 1) . ' exceeds maximum length of ' . $maxLength . ' characters');
            }
        }

        // Check for suspicious binary content
        if ($this->containsBinaryData($content)) {
            throw new InvalidArgumentException('File contains binary data and cannot be processed as text');
        }

        // Check for reasonable line count
        $maxLines = config('extractors.limits.max_lines', 100000);
        $maxLinesInt = is_numeric($maxLines) ? (int) $maxLines : 100000;

        if (count($lines) > $maxLinesInt) {
            throw new InvalidArgumentException('File contains too many lines. Maximum is ' . $maxLinesInt);
        }
    }

    /**
     * Check if content contains binary data.
     */
    private function containsBinaryData(string $content): bool
    {
        // Check for null bytes and other control characters
        if (str_contains($content, "\0")) {
            return true;
        }

        // Check percentage of non-printable characters
        $printableChars = 0;
        $totalChars = mb_strlen($content);

        if ($totalChars === 0) {
            return false;
        }

        for ($i = 0; $i < min($totalChars, 1000); ++$i) {
            $char = mb_substr($content, $i, 1);
            $ord = mb_ord($char);

            // Printable ASCII, common whitespace, or UTF-8 multibyte
            if ($ord > 127 || ($ord >= 32 && $ord <= 126) || in_array($ord, [9, 10, 13], true)) {
                ++$printableChars;
            }
        }

        $printableRatio = $printableChars / min($totalChars, 1000);

        return $printableRatio < 0.7; // Less than 70% printable = likely binary
    }

    /**
     * Extract document using streaming for large files.
     */
    private function extractWithStreaming(string $filePath, string $encoding, ExtractionConfig $config, float $startTime): ExtractedDocument
    {
        $elements = [];
        $chunkSizeConfig = config('extractors.limits.chunk_size', 1024 * 1024);
        $chunkSize = is_numeric($chunkSizeConfig) ? (int) $chunkSizeConfig : 1024 * 1024;
        $chunkSize = max(1, $chunkSize); // Ensure positive value
        $lineBuffer = '';
        $paragraphBuffer = '';
        $totalChars = 0;
        $lineCount = 0;

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open file for streaming: {$filePath}");
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);

                if ($chunk === false) {
                    break;
                }

                $chunk = $this->encodingDetector->convertToUtf8($chunk, $encoding);
                $totalChars += strlen($chunk);
                $lineBuffer .= $chunk;

                // Process complete lines
                while (($pos = strpos($lineBuffer, "\n")) !== false) {
                    $line = substr($lineBuffer, 0, $pos);
                    $lineBuffer = substr($lineBuffer, $pos + 1);
                    ++$lineCount;

                    // Security check for line length
                    $maxLineLength = config('extractors.limits.max_line_length', 10000);

                    if (mb_strlen($line) > $maxLineLength) {
                        fclose($handle);

                        throw new InvalidArgumentException("Line {$lineCount} exceeds maximum length");
                    }

                    // Build paragraphs
                    if (trim($line) === '') {
                        if (!empty($paragraphBuffer)) {
                            $parsedElements = $this->parseTextContent($paragraphBuffer, $config);
                            array_push($elements, ...$parsedElements);
                            $paragraphBuffer = '';
                        }
                    } else {
                        $paragraphBuffer .= $line . "\n";
                    }

                    // Check line limit
                    $maxLinesConfig = config('extractors.limits.max_lines', 100000);
                    $maxLines = is_numeric($maxLinesConfig) ? (int) $maxLinesConfig : 100000;

                    if ($lineCount > $maxLines) {
                        fclose($handle);

                        throw new InvalidArgumentException('File contains too many lines. Maximum is ' . $maxLines);
                    }

                    // Check timeout periodically (every 1000 lines)
                    if ($lineCount % 1000 === 0) {
                        $currentTime = microtime(true);

                        if (($currentTime - $startTime) > $config->timeoutSeconds) {
                            fclose($handle);

                            throw new RuntimeException("Extraction timeout exceeded ({$config->timeoutSeconds}s) while processing line {$lineCount}");
                        }
                    }
                }
            }

            // Process remaining content
            if (!empty($paragraphBuffer)) {
                $parsedElements = $this->parseTextContent($paragraphBuffer, $config);
                array_push($elements, ...$parsedElements);
            }
        } finally {
            fclose($handle);
        }

        $extractionTime = microtime(true) - $startTime;
        $this->metrics->record('txt_extraction_streaming', $extractionTime, $totalChars);

        return new ExtractedDocument(
            originalPath: $filePath,
            mimeType: 'text/plain',
            elements: $elements,
            metadata: [
                'encoding' => $encoding,
                'file_size' => $totalChars,
                'line_count' => $lineCount,
                'processing_mode' => 'streaming',
            ],
            totalPages: 1,
            extractionTime: $extractionTime,
            metrics: $this->metrics->getMetrics(),
        );
    }
}
