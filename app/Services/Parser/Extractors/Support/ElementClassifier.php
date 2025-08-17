<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Support;

class ElementClassifier
{
    private const HEADER_PATTERNS = [
        '/^#{1,6}\s+/', // Markdown headers
        '/^(chapter|глава)\s+\d+/i',
        '/^(section|раздел)\s+\d+/i',
        '/^(part|часть)\s+[IVX\d]+/i',
        '/^\d+\.\s+[А-ЯA-Z]/', // Numbered headers
        '/^[А-ЯA-Z][А-Я\sA-Z]+$/', // All caps (short)
    ];

    private const LIST_PATTERNS = [
        '/^[-*•]\s+/', // Bullet lists
        '/^\d+\.\s+/', // Numbered lists
        '/^[a-z]\)\s+/', // Letter lists
        '/^[ivx]+\.\s+/i', // Roman numerals
    ];

    public function classify(string $text, array $style = [], array $position = []): string
    {
        $text = trim($text);

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

        // Check for lists
        if ($this->isListItem($text)) {
            return 'list';
        }

        // Check for table-like content
        if ($this->isTableContent($text)) {
            return 'table';
        }

        // Default to paragraph for multi-line or regular text
        if (str_contains($text, "\n") || strlen($text) > 50) {
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

            if ($fontSize >= 20) {
                return 1;
            }

            if ($fontSize >= 16) {
                return 2;
            }

            if ($fontSize >= 14) {
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
                return 0.8;
        }
    }

    private function isHeaderByStyle(array $style): bool
    {
        // Large font size
        if (isset($style['font_size']) && (int) $style['font_size'] >= 16) {
            return true;
        }

        // Bold and larger than normal
        if (isset($style['font_weight']) && str_contains($style['font_weight'], 'bold')) {
            if (isset($style['font_size']) && (int) $style['font_size'] >= 12) {
                return true;
            }
        }

        // Underlined
        if (isset($style['text_decoration']) && str_contains($style['text_decoration'], 'underline')) {
            return true;
        }

        return false;
    }

    private function isHeaderByPattern(string $text): bool
    {
        foreach (self::HEADER_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        // Short text in all caps (likely header)
        if (strlen($text) < 100 && preg_match('/^[А-ЯA-Z\s\d\-.,!?]+$/', $text)) {
            return true;
        }

        return false;
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

    private function isTableContent(string $text): bool
    {
        // Look for table-like separators
        $separatorCount = substr_count($text, '|') + substr_count($text, "\t");

        return $separatorCount >= 2;
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
