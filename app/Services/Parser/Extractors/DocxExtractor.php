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
use DOMDocument;
use DOMXPath;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

readonly class DocxExtractor implements ExtractorInterface
{
    public function __construct(
        private ElementClassifier $classifier,
        private MetricsCollector $metrics,
    ) {
    }

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/docx',
        ]);
    }

    public function extract(string $filePath, ?ExtractionConfig $config = null): ExtractedDocument
    {
        $startTime = microtime(true);
        $config ??= ExtractionConfig::createDefault();

        $this->validate($filePath);

        // Extract text from DOCX
        $xmlContent = $this->extractDocumentXml($filePath);
        $textContent = $this->parseDocumentXml($xmlContent);

        // Validate content for security
        $this->validateContent($textContent);

        // Parse content into elements
        $elements = $this->parseTextContent($textContent, $config);

        $extractionTime = microtime(true) - $startTime;
        $this->metrics->record('docx_extraction', $extractionTime, strlen($textContent));

        return new ExtractedDocument(
            originalPath: $filePath,
            mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            elements: $elements,
            metadata: [
                'file_size' => filesize($filePath),
                'character_count' => mb_strlen($textContent),
                'extraction_method' => 'docx_xml',
            ],
            totalPages: 1, // DOCX doesn't have fixed pages
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

        // Check if it's actually a valid DOCX file
        if (!$this->isValidDocxFile($filePath)) {
            throw new InvalidArgumentException("Invalid DOCX file: {$filePath}");
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
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'estimated_processing_time' => $this->estimateProcessingTime($filePath),
            'format' => 'docx',
        ];
    }

    public function estimateProcessingTime(string $filePath): int
    {
        $fileSize = filesize($filePath);

        if ($fileSize === false) {
            return 5;
        }

        // DOCX processing is slower than plain text: ~500KB per second
        return max(5, (int) ceil($fileSize / (500 * 1024)));
    }

    private function isValidDocxFile(string $filePath): bool
    {
        $zip = new ZipArchive();
        $result = $zip->open($filePath);

        if ($result !== true) {
            return false;
        }

        // Check for required DOCX structure files
        $requiredFiles = ['[Content_Types].xml', 'word/document.xml'];

        foreach ($requiredFiles as $file) {
            if ($zip->locateName($file) === false) {
                $zip->close();

                return false;
            }
        }

        $zip->close();

        return true;
    }

    private function extractDocumentXml(string $filePath): string
    {
        $zip = new ZipArchive();
        $result = $zip->open($filePath);

        if ($result !== true) {
            throw new RuntimeException("Cannot open DOCX file: {$filePath}");
        }

        $documentXml = $zip->getFromName('word/document.xml');

        if ($documentXml === false) {
            $zip->close();

            throw new RuntimeException('Cannot extract document.xml from DOCX file');
        }

        $zip->close();

        return $documentXml;
    }

    private function parseDocumentXml(string $xmlContent): string
    {
        // Suppress XML parsing errors
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);

        // Get all text nodes
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $textNodes = $xpath->query('//w:t');
        $paragraphs = $xpath->query('//w:p');

        $text = '';

        if ($paragraphs && $paragraphs->length > 0) {
            // Process by paragraphs to preserve structure
            foreach ($paragraphs as $paragraph) {
                $paragraphText = '';
                $textNodesInParagraph = $xpath->query('.//w:t', $paragraph);

                if ($textNodesInParagraph) {
                    foreach ($textNodesInParagraph as $textNode) {
                        $paragraphText .= $textNode->textContent;
                    }
                }

                if (trim($paragraphText) !== '') {
                    $text .= trim($paragraphText) . "\n\n";
                }
            }
        } elseif ($textNodes && $textNodes->length > 0) {
            // Fallback: just extract all text
            foreach ($textNodes as $textNode) {
                $text .= $textNode->textContent . ' ';
            }
        }

        return trim($text);
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
