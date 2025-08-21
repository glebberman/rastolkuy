<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Support;

use InvalidArgumentException;
use RuntimeException;
use ValueError;

class EncodingDetector
{
    private const array SUPPORTED_ENCODINGS = [
        'UTF-8',
        'UTF-16',
        'UTF-16BE',
        'UTF-16LE',
        'Windows-1251',
        'Windows-1252',
        'ISO-8859-1',
        'ISO-8859-5',
        'CP866',
        'KOI8-R',
        'ASCII',
    ];

    public function detect(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath, false, null, 0, 8192); // Read first 8KB

        if ($content === false) {
            throw new RuntimeException("Cannot read file: {$filePath}");
        }

        // Check for BOM
        $bom = $this->detectBom($content);

        if ($bom !== null) {
            return $bom;
        }

        // Use mb_detect_encoding
        $detected = mb_detect_encoding($content, self::SUPPORTED_ENCODINGS, true);

        if ($detected !== false) {
            return $detected;
        }

        // Fallback heuristics
        return $this->detectByHeuristics($content);
    }

    public function convertToUtf8(string $content, string $encoding): string
    {
        if ($encoding === 'UTF-8') {
            return $content;
        }

        try {
            $converted = mb_convert_encoding($content, 'UTF-8', $encoding);

            if ($converted === false) {
                throw new RuntimeException("Failed to convert encoding from {$encoding} to UTF-8");
            }

            return $converted;
        } catch (ValueError $e) {
            throw new RuntimeException("Invalid encoding: {$encoding}. " . $e->getMessage());
        }
    }

    private function detectBom(string $content): ?string
    {
        // UTF-8 BOM
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return 'UTF-8';
        }

        // UTF-16 BE BOM
        if (str_starts_with($content, "\xFE\xFF")) {
            return 'UTF-16BE';
        }

        // UTF-16 LE BOM
        if (str_starts_with($content, "\xFF\xFE")) {
            return 'UTF-16LE';
        }

        return null;
    }

    private function detectByHeuristics(string $content): string
    {
        // Check if content is valid UTF-8
        if (mb_check_encoding($content, 'UTF-8')) {
            return 'UTF-8';
        }

        // Check for Cyrillic characters in Windows-1251
        if ($this->isCyrillic($content)) {
            return 'Windows-1251';
        }

        // Check for extended ASCII
        if ($this->isExtendedAscii($content)) {
            return 'Windows-1252';
        }

        // Default fallback
        return 'UTF-8';
    }

    private function isCyrillic(string $content): bool
    {
        // Count potential Cyrillic characters in Windows-1251 range
        $cyrillicCount = 0;
        $totalChars = 0;
        $contentLength = strlen($content);

        for ($i = 0; $i < $contentLength; ++$i) {
            $byte = ord($content[$i]);

            if ($byte >= 128) {
                ++$totalChars;

                // Windows-1251 Cyrillic range
                if ($byte >= 192) {
                    ++$cyrillicCount;
                }
            }
        }

        return $totalChars > 0 && ($cyrillicCount / $totalChars) > 0.3;
    }

    private function isExtendedAscii(string $content): bool
    {
        // Check for extended ASCII characters
        $contentLength = strlen($content);
        for ($i = 0; $i < $contentLength; ++$i) {
            $byte = ord($content[$i]);

            if ($byte > 127 && $byte < 192) {
                return true;
            }
        }

        return false;
    }
}
