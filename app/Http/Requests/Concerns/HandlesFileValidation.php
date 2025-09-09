<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

trait HandlesFileValidation
{
    /**
     * Get the maximum file size in MB from configuration.
     * Uses the most restrictive limit between document and extractor configs.
     */
    protected function getMaxFileSizeMb(): float
    {
        $documentMaxSizeMb = config('document.structure_analysis.max_file_size_mb', 50);
        assert(is_numeric($documentMaxSizeMb));
        $documentMaxSizeMb = (float) $documentMaxSizeMb;
        
        $extractorMaxSizeBytes = config('extractors.max_file_size', 50 * 1024 * 1024);
        assert(is_numeric($extractorMaxSizeBytes));
        $extractorMaxSizeBytes = (int) $extractorMaxSizeBytes;
        
        $extractorMaxSizeMb = $extractorMaxSizeBytes / (1024 * 1024);
        
        return min($documentMaxSizeMb, $extractorMaxSizeMb);
    }

    /**
     * Get the maximum file size in bytes.
     */
    protected function getMaxFileSizeBytes(): int
    {
        return (int) ($this->getMaxFileSizeMb() * 1024 * 1024);
    }

    /**
     * Get allowed file extensions from configuration.
     * Falls back to standard set if config is incomplete.
     * 
     * @return array<string>
     */
    protected function getAllowedExtensions(): array
    {
        $supportedTypes = config('extractors.supported_types', []);
        assert(is_array($supportedTypes));
        $allowedExtensions = ['txt', 'pdf', 'docx', 'doc']; // Default supported types
        
        // If we have specific config, honor it but ensure PDF is always included for backward compatibility
        if (!empty($supportedTypes)) {
            $configExtensions = [];
            foreach ($supportedTypes as $mimeType => $extractor) {
                assert(is_string($mimeType));
                if (str_contains($mimeType, 'pdf')) {
                    $configExtensions[] = 'pdf';
                } elseif (str_contains($mimeType, 'txt') || str_contains($mimeType, 'plain')) {
                    $configExtensions[] = 'txt';
                }
            }
            // Include docx/doc even if not in extractors yet and always include PDF
            $configExtensions[] = 'docx';
            $configExtensions[] = 'doc';
            $configExtensions[] = 'pdf';
            $allowedExtensions = array_unique($configExtensions);
        }
        
        return $allowedExtensions;
    }

    /**
     * Get allowed MIME types as comma-separated string.
     */
    protected function getAllowedMimeTypes(): string
    {
        return implode(',', $this->getAllowedExtensions());
    }

    /**
     * Get formatted string of supported file formats for error messages.
     */
    protected function getFormatsString(): string
    {
        $supportedTypes = config('extractors.supported_types', []);
        assert(is_array($supportedTypes));
        $formats = ['TXT', 'PDF', 'DOCX', 'DOC']; // Default supported types
        
        if (!empty($supportedTypes)) {
            $configFormats = [];
            foreach (array_keys($supportedTypes) as $mimeType) {
                assert(is_string($mimeType));
                if (str_contains($mimeType, 'pdf')) {
                    $configFormats[] = 'PDF';
                } elseif (str_contains($mimeType, 'txt') || str_contains($mimeType, 'plain')) {
                    $configFormats[] = 'TXT';
                }
            }
            $configFormats[] = 'DOCX';
            $configFormats[] = 'DOC';
            $configFormats[] = 'PDF';
            $formats = array_unique($configFormats);
        }
        
        return implode(', ', $formats);
    }
}