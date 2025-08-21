<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\Services\Validation\Contracts\ValidatorInterface;
use App\Services\Validation\DTOs\ValidationResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SecurityValidator implements ValidatorInterface
{
    /**
     * @var array<string>
     */
    private array $blockedPatterns;

    private int $maxFileNameLength;

    public function __construct()
    {
        $patterns = config('document_validation.security.blocked_patterns', []);
        $maxLength = config('document_validation.security.max_file_name_length', 255);
        $this->blockedPatterns = is_array($patterns) ? $patterns : [];
        $this->maxFileNameLength = is_numeric($maxLength) ? (int) $maxLength : 255;
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
                $this->maxFileNameLength,
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
            ? new ValidationResult(true, [], $warnings, $metadata)
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
        // Check for path traversal sequences
        if (str_contains($fileName, '../') || str_contains($fileName, '..\\')) {
            return true;
        }

        // Check for absolute paths (Unix and Windows)
        if (str_starts_with($fileName, '/')
            || (strlen($fileName) > 2 && $fileName[1] === ':' && $fileName[2] === '\\')) {
            return true;
        }

        // Check for directory separators in the middle (potential path injection)
        // Allow single filename with extension but block paths like "dir/file.txt"
        if (str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            return true;
        }

        return false;
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
                Log::warning('Failed to open file for security scanning', [
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                ]);

                return null;
            }

            // Read first 4KB for analysis
            $content = fread($handle, 4096);
            fclose($handle);

            if ($content === false) {
                Log::warning('Failed to read file content for security scanning', [
                    'filename' => $file->getClientOriginalName(),
                ]);

                return null;
            }

            return $content;
        } catch (Throwable $e) {
            Log::error('Exception during security content sampling', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function isSuspiciousBinaryContent(UploadedFile $file, string $content): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // For text files, check if content contains binary/non-printable characters
        if ($extension === 'txt') {
            // Check for null bytes or other control characters that shouldn't be in text files
            return str_contains($content, "\x00")
                   || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', $content);
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
