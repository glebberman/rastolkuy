<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\Services\Validation\Contracts\ValidatorInterface;
use App\Services\Validation\DTOs\ValidationResult;
use Illuminate\Http\UploadedFile;

final class SecurityValidator implements ValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $blockedPatterns;
    private int $maxFileNameLength;

    public function __construct()
    {
        $this->blockedPatterns = config('document_validation.security.blocked_patterns', []);
        $this->maxFileNameLength = config('document_validation.security.max_file_name_length', 255);
    }

    public function validate(UploadedFile $file): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $metadata = [];

        // Validate file name
        $fileName = $file->getClientOriginalName();
        $metadata['original_filename'] = $fileName;

        if (strlen($fileName) > $this->maxFileNameLength) {
            $errors[] = sprintf(
                'File name is too long (%d characters). Maximum length is %d',
                strlen($fileName),
                $this->maxFileNameLength
            );
        }

        // Check for suspicious file name patterns
        if ($this->hasEmbeddedPath($fileName)) {
            $errors[] = 'File name contains path traversal sequences';
        }

        if ($this->hasNullBytes($fileName)) {
            $errors[] = 'File name contains null bytes';
        }

        // Validate file content (read first chunk)
        $fileContent = $this->getFileContentSample($file);
        if ($fileContent !== null) {
            $metadata['content_sample_size'] = strlen($fileContent);
            
            // Check for malicious patterns in content
            foreach ($this->blockedPatterns as $pattern) {
                if (preg_match($pattern, $fileContent)) {
                    $errors[] = 'File contains potentially malicious content';
                    break;
                }
            }

            // Check for binary content where it shouldn't be
            if ($this->isSuspiciousBinaryContent($file, $fileContent)) {
                $warnings[] = 'File may contain unexpected binary content';
            }
        }

        // Additional security checks
        $metadata['security_scan_timestamp'] = now()->toISOString();

        return empty($errors) 
            ? ValidationResult::valid($metadata)
            : ValidationResult::invalid($errors, $warnings, $metadata);
    }

    public function getName(): string
    {
        return 'security';
    }

    public function supports(UploadedFile $file): bool
    {
        return true; // This validator supports all files for security checking
    }

    private function hasEmbeddedPath(string $fileName): bool
    {
        return str_contains($fileName, '../') || 
               str_contains($fileName, '..\\') ||
               str_contains($fileName, '/') ||
               str_contains($fileName, '\\');
    }

    private function hasNullBytes(string $fileName): bool
    {
        return str_contains($fileName, "\0");
    }

    private function getFileContentSample(UploadedFile $file): ?string
    {
        try {
            $handle = fopen($file->getPathname(), 'rb');
            if ($handle === false) {
                return null;
            }

            // Read first 4KB for analysis
            $content = fread($handle, 4096);
            fclose($handle);

            return $content !== false ? $content : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function isSuspiciousBinaryContent(UploadedFile $file, string $content): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        // For text files, check if content is actually binary
        if ($extension === 'txt') {
            return !mb_check_encoding($content, 'UTF-8') && 
                   !mb_check_encoding($content, 'ASCII') &&
                   !mb_check_encoding($content, 'Windows-1251');
        }

        // For PDF files, check for proper PDF header
        if ($extension === 'pdf') {
            return !str_starts_with($content, '%PDF-');
        }

        // For DOCX files, check for ZIP header (DOCX is a ZIP archive)
        if ($extension === 'docx') {
            return !str_starts_with($content, 'PK');
        }

        return false;
    }
}