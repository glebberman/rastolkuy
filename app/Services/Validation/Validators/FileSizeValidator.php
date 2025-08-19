<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\Services\Validation\Contracts\ValidatorInterface;
use App\Services\Validation\DTOs\ValidationResult;
use Illuminate\Http\UploadedFile;

final class FileSizeValidator implements ValidatorInterface
{
    private int $maxSize;
    private int $minSize;

    public function __construct()
    {
        $this->maxSize = config('document_validation.file_size.max_size', 10485760);
        $this->minSize = config('document_validation.file_size.min_size', 1024);
    }

    public function validate(UploadedFile $file): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $metadata = [];

        $fileSize = $file->getSize();
        $metadata['file_size'] = $fileSize;
        $metadata['file_size_human'] = $this->formatBytes($fileSize);

        if ($fileSize === false) {
            $errors[] = 'Could not determine file size';
            return ValidationResult::invalid($errors, $warnings, $metadata);
        }

        // Check minimum size
        if ($fileSize < $this->minSize) {
            $errors[] = sprintf(
                'File is too small (%s). Minimum size is %s',
                $this->formatBytes($fileSize),
                $this->formatBytes($this->minSize)
            );
        }

        // Check maximum size
        if ($fileSize > $this->maxSize) {
            $errors[] = sprintf(
                'File is too large (%s). Maximum size is %s',
                $this->formatBytes($fileSize),
                $this->formatBytes($this->maxSize)
            );
        }

        // Warning for very large files (80% of max size)
        $warningThreshold = (int) ($this->maxSize * 0.8);
        if ($fileSize > $warningThreshold && $fileSize <= $this->maxSize) {
            $warnings[] = sprintf(
                'File is quite large (%s). Processing may take longer',
                $this->formatBytes($fileSize)
            );
        }

        return empty($errors) 
            ? ValidationResult::valid($metadata)
            : ValidationResult::invalid($errors, $warnings, $metadata);
    }

    public function getName(): string
    {
        return 'file_size';
    }

    public function supports(UploadedFile $file): bool
    {
        return true; // This validator supports all files for size checking
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 1) . ' ' . $units[$pow];
    }
}