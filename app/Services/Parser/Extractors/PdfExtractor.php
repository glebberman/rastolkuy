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
use App\Services\Parser\Extractors\Support\MetricsCollector;
use Exception;
use InvalidArgumentException;
use RuntimeException;

readonly class PdfExtractor implements ExtractorInterface
{
    public function __construct(
        private ElementClassifier $classifier,
        private MetricsCollector $metrics,
    ) {
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/pdf',
        ]);
    }

    public function extract(string $filePath, ?ExtractionConfig $config = null): ExtractedDocument
    {
        $startTime = microtime(true);
        $config ??= ExtractionConfig::createDefault();

        $this->validate($filePath);

        // Extract text from PDF using available method
        $textContent = $this->extractTextFromPdf($filePath);

        // Validate content for security
        $this->validateContent($textContent);

        // Parse content into elements
        $elements = $this->parseTextContent($textContent, $config);

        $extractionTime = microtime(true) - $startTime;
        $this->metrics->record('pdf_extraction', $extractionTime, strlen($textContent));

        return new ExtractedDocument(
            originalPath: $filePath,
            mimeType: 'application/pdf',
            elements: $elements,
            metadata: [
                'file_size' => filesize($filePath),
                'character_count' => mb_strlen($textContent),
                'extraction_method' => 'pdf_text',
            ],
            totalPages: $this->estimatePageCount($textContent),
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

        // Check if file is too large
        $maxSize = config('extractors.limits.max_file_size', 50 * 1024 * 1024);

        if ($fileSize > $maxSize) {
            throw new InvalidArgumentException('File is too large. Maximum size is ' . round($maxSize / 1024 / 1024, 1) . "MB: {$filePath}");
        }

        // Check if it's actually a PDF file
        if (!$this->isValidPdfFile($filePath)) {
            throw new InvalidArgumentException("Invalid PDF file: {$filePath}");
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

        return [
            'file_size' => $fileSize,
            'mime_type' => 'application/pdf',
            'estimated_processing_time' => $this->estimateProcessingTime($filePath),
            'format' => 'pdf',
        ];
    }

    public function estimateProcessingTime(string $filePath): int
    {
        $fileSize = filesize($filePath);

        if ($fileSize === false) {
            return 10;
        }

        // PDF processing is slower: ~100KB per second
        return max(10, (int) ceil($fileSize / (100 * 1024)));
    }

    private function isValidPdfFile(string $filePath): bool
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return false;
        }

        // Check PDF header
        $header = fread($handle, 5);
        fclose($handle);

        return $header === '%PDF-';
    }

    private function extractTextFromPdf(string $filePath): string
    {
        // Try different methods to extract text from PDF
        
        // Method 1: Try pdftotext command if available
        if ($this->commandExists('pdftotext')) {
            $text = $this->extractWithPdfToText($filePath);
            if ($text !== '') {
                return $text;
            }
        }

        // Method 2: Try simple extraction for text-based PDFs
        $text = $this->extractWithSimpleMethod($filePath);
        if ($text !== '') {
            return $text;
        }

        // If no text could be extracted, provide a fallback message
        return "PDF содержимое не может быть извлечено автоматически. " . 
               "Возможно, это отсканированный документ или содержит только изображения. " . 
               "Размер файла: " . $this->formatFileSize(filesize($filePath));
    }

    private function commandExists(string $command): bool
    {
        $result = shell_exec("which $command 2>/dev/null");

        return !empty($result);
    }

    private function extractWithPdfToText(string $filePath): string
    {
        $escapedPath = escapeshellarg($filePath);
        $command = "pdftotext -enc UTF-8 -nopgbrk $escapedPath - 2>/dev/null";
        
        $text = shell_exec($command);

        return is_string($text) ? trim($text) : '';
    }

    private function extractWithSimpleMethod(string $filePath): string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return '';
        }

        // Simple text extraction from PDF streams (works only for text-based PDFs)
        $text = '';
        
        // Look for text streams in PDF
        if (preg_match_all('/BT\s+(.*?)\s+ET/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                // Extract text from PDF operators
                if (preg_match_all('/\((.*?)\)\s*Tj/s', $match, $textMatches)) {
                    foreach ($textMatches[1] as $textMatch) {
                        $text .= $textMatch . ' ';
                    }
                }
            }
        }

        // Also try to find strings in parentheses (common in PDF text)
        if (empty(trim($text))) {
            if (preg_match_all('/\(([^)]+)\)/s', $content, $stringMatches)) {
                foreach ($stringMatches[1] as $stringMatch) {
                    // Filter out non-text content (binary, short strings, etc.)
                    if (mb_strlen($stringMatch) > 3 && $this->looksLikeText($stringMatch)) {
                        $text .= $stringMatch . ' ';
                    }
                }
            }
        }

        return trim($text);
    }

    private function looksLikeText(string $content): bool
    {
        // Check if content looks like readable text
        $printableChars = 0;
        $totalChars = mb_strlen($content);

        if ($totalChars === 0) {
            return false;
        }

        for ($i = 0; $i < min($totalChars, 100); ++$i) {
            $char = mb_substr($content, $i, 1);
            $ord = mb_ord($char);

            // Count printable ASCII and common Unicode characters
            if (($ord >= 32 && $ord <= 126) || $ord > 127 || in_array($ord, [9, 10, 13], true)) {
                ++$printableChars;
            }
        }

        $printableRatio = $printableChars / min($totalChars, 100);

        return $printableRatio > 0.7; // More than 70% printable = likely text
    }

    private function estimatePageCount(string $content): int
    {
        // Rough estimation based on content length
        $contentLength = mb_strlen($content);

        if ($contentLength === 0) {
            return 1;
        }

        // Estimate ~2000 characters per page (rough approximation)
        return max(1, (int) ceil($contentLength / 2000));
    }

    private function formatFileSize(int|false $bytes): string
    {
        if ($bytes === false) {
            return 'неизвестен';
        }

        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
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

            // Classify paragraph
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
        // Split by double newlines or multiple spaces (common in PDF extraction)
        try {
            $paragraphs = preg_split('/\n\s*\n|\s{3,}/', $content);

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
     * Validate content for security issues.
     */
    private function validateContent(string $content): void
    {
        // Check for extremely long content
        $maxLength = config('extractors.limits.max_content_length', 10 * 1024 * 1024); // 10MB
        $maxLengthInt = is_numeric($maxLength) ? (int) $maxLength : 10 * 1024 * 1024;

        if (mb_strlen($content) > $maxLengthInt) {
            throw new InvalidArgumentException('Extracted content exceeds maximum length of ' . ($maxLengthInt / 1024 / 1024) . 'MB');
        }

        // Check for reasonable line count
        $maxLines = config('extractors.limits.max_lines', 100000);
        $maxLinesInt = is_numeric($maxLines) ? (int) $maxLines : 100000;
        $lines = explode("\n", $content);

        if (count($lines) > $maxLinesInt) {
            throw new InvalidArgumentException('Extracted content contains too many lines. Maximum is ' . $maxLinesInt);
        }
    }
}