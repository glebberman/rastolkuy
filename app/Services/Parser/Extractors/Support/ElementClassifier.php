<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Support;

class ElementClassifier
{
    private const array HEADER_PATTERNS = [
        '/^#{1,6}\s+/', // Markdown headers
        '/^(chapter|глава)\s+\d+/i',
        '/^(section|раздел)\s+\d+/i',
        '/^(part|часть)\s+[IVX\d]+/i',
        '/^\d+\.\s+[А-ЯA-Z][А-ЯA-Z\s]+$/', // Numbered headers (all caps after number)
        '/^[А-ЯA-Z][А-Я\sA-Z]+$/', // All caps (short)
    ];

    private const array LIST_PATTERNS = [
        '/^[-*•]\s+/', // Bullet lists
        '/^\d+\.\s+/', // Numbered lists
        '/^[a-z]\)\s+/', // Letter lists
        '/^[ivx]+\.\s+/i', // Roman numerals
    ];

    public function classify(string $text, array $style = [], array $position = []): string
    {
        $text = trim($text);
        
        // Position parameter reserved for future use (page numbers, coordinates, etc.)
        unset($position);

        if (empty($text)) {
            return 'text';
        }

        // Check for headers based on style
        if ($this->isHeaderByStyle($style)) {
            return 'header';
        }

        // Check for headers based on patterns
        if ($this->isHeaderByPattern($text)) {
            return 'header';
        }

        // Check for lists (should be checked before paragraph)
        if ($this->isListItem($text)) {
            return 'list';
        }

        // Check for table-like content (should be checked before paragraph)
        if ($this->isTableContent($text)) {
            return 'table';
        }

        // Check if it's a multi-line list
        if (str_contains($text, "\n") && $this->isMultilineList($text)) {
            return 'list';
        }

        // Default to paragraph for multi-line or regular text
        $paragraphMinLength = config('extractors.classification.paragraph_min_length', 50);

        if (str_contains($text, "\n") || strlen($text) > $paragraphMinLength) {
            return 'paragraph';
        }

        return 'text';
    }

    public function determineHeaderLevel(string $text, array $style = []): int
    {
        // Check markdown headers
        if (preg_match('/^(#{1,6})\s+/', $text, $matches)) {
            return strlen($matches[1]);
        }

        // Use font size if available
        if (isset($style['font_size'])) {
            $fontSize = (int) $style['font_size'];
            $fontSizes = (array) config('extractors.classification.font_sizes', [
                'h1' => 20,
                'h2' => 16,
                'h3' => 14,
            ]);

            if ($fontSize >= ($fontSizes['h1'] ?? 20)) {
                return 1;
            }

            if ($fontSize >= ($fontSizes['h2'] ?? 16)) {
                return 2;
            }

            if ($fontSize >= ($fontSizes['h3'] ?? 14)) {
                return 3;
            }

            return 4;
        }

        // Use font weight
        if (isset($style['font_weight']) && str_contains($style['font_weight'], 'bold')) {
            return 2;
        }

        // Default level
        return 3;
    }

    public function getConfidenceScore(string $type, string $text, array $style = []): float
    {
        switch ($type) {
            case 'header':
                return $this->getHeaderConfidence($text, $style);
            case 'list':
                return $this->getListConfidence($text);
            case 'table':
                return $this->getTableConfidence($text);
            default:
                $confidence = config('extractors.classification.default_confidence', 0.8);

                return is_numeric($confidence) ? (float) $confidence : 0.8;
        }
    }

    private function isHeaderByStyle(array $style): bool
    {
        // Large font size
        $headerMinFontSize = config('extractors.classification.header_min_font_size', 16);

        if (isset($style['font_size']) && (int) $style['font_size'] >= $headerMinFontSize) {
            return true;
        }

        // Bold and larger than normal
        $boldMinFontSize = config('extractors.classification.bold_min_font_size', 12);

        if (isset($style['font_weight'], $style['font_size']) && (int) $style['font_size'] >= $boldMinFontSize
            && str_contains($style['font_weight'], 'bold')) {
            return true;
        }

        // Underlined
        return isset($style['text_decoration']) && str_contains($style['text_decoration'], 'underline');
    }

    private function isHeaderByPattern(string $text): bool
    {
        foreach (self::HEADER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        // Short text in all caps (likely header)
        $headerMaxLength = config('extractors.classification.header_max_length', 100);

        return strlen($text) < $headerMaxLength && preg_match('/^[А-ЯA-Z\s\d\-.,!?]+$/u', $text);
    }

    private function isListItem(string $text): bool
    {
        foreach (self::LIST_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function isMultilineList(string $text): bool
    {
        $lines = explode("\n", $text);
        $listLines = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue; // Skip empty lines
            }

            foreach (self::LIST_PATTERNS as $pattern) {
                if (preg_match($pattern, $line)) {
                    ++$listLines;
                    break;
                }
            }
        }

        // At least 50% of non-empty lines should be list items
        $nonEmptyLines = count(array_filter($lines, fn ($line) => !empty(trim($line))));

        return $nonEmptyLines > 0 && ($listLines / $nonEmptyLines) >= 0.5;
    }

    private function isTableContent(string $text): bool
    {
        // Look for table-like separators
        $separatorCount = substr_count($text, '|') + substr_count($text, "\t");
        $minSeparators = config('extractors.classification.table_min_separators', 2);

        return $separatorCount >= $minSeparators;
    }

    private function getHeaderConfidence(string $text, array $style): float
    {
        $confidence = 0.5;

        // Pattern matching
        foreach (self::HEADER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                $confidence += 0.3;

                break;
            }
        }

        // Style indicators
        if (isset($style['font_size']) && (int) $style['font_size'] >= 16) {
            $confidence += 0.2;
        }

        if (isset($style['font_weight']) && str_contains($style['font_weight'], 'bold')) {
            $confidence += 0.2;
        }

        // Length (headers are usually shorter)
        if (strlen($text) < 100) {
            $confidence += 0.1;
        }

        return min(1.0, $confidence);
    }

    private function getListConfidence(string $text): float
    {
        foreach (self::LIST_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return 0.9;
            }
        }

        return 0.3;
    }

    private function getTableConfidence(string $text): float
    {
        $separatorCount = substr_count($text, '|') + substr_count($text, "\t");
        $lineCount = substr_count($text, "\n") + 1;

        if ($separatorCount >= $lineCount * 2) {
            return 0.8;
        }

        if ($separatorCount >= 2) {
            return 0.6;
        }

        return 0.2;
    }
}
