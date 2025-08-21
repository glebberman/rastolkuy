<?php

declare(strict_types=1);

namespace App\Services\Validation\Validators;

use App\Services\Validation\Contracts\ValidatorInterface;
use App\Services\Validation\DTOs\ValidationResult;
use Illuminate\Http\UploadedFile;

final class FileFormatValidator implements ValidatorInterface
{
    /**
     * @var array<string, array{mime_types: array<string>, extensions: array<string>}>
     */
    private array $allowedFormats;

    public function __construct()
    {
        $formats = config('document_validation.allowed_formats', []);
        $this->allowedFormats = is_array($formats) ? $formats : [];
    }

    public function validate(UploadedFile $file): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $metadata = [];

        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        $metadata['extension'] = $extension;

        if (empty($extension)) {
            $errors[] = 'File must have a valid extension';

            return ValidationResult::invalid($errors, $warnings, $metadata);
        }

        // Check if extension is allowed
        $allowedExtensions = $this->getAllowedExtensions();

        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = sprintf(
                'File extension "%s" is not allowed. Allowed extensions: %s',
                $extension,
                implode(', ', $allowedExtensions),
            );
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        $metadata['mime_type'] = $mimeType;

        if ($mimeType === null) {
            $errors[] = 'Could not determine file MIME type';

            return ValidationResult::invalid($errors, $warnings, $metadata);
        }

        $allowedMimeTypes = $this->getAllowedMimeTypes();

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $errors[] = sprintf(
                'File MIME type "%s" is not allowed. Allowed types: %s',
                $mimeType,
                implode(', ', $allowedMimeTypes),
            );
        }

        // Cross-check extension and MIME type consistency
        if (empty($errors) && !$this->isExtensionMimeTypeConsistent($extension, $mimeType)) {
            $warnings[] = sprintf(
                'File extension "%s" does not match MIME type "%s"',
                $extension,
                $mimeType,
            );
        }

        return empty($errors)
            ? new ValidationResult(true, [], $warnings, $metadata)
            : ValidationResult::invalid($errors, $warnings, $metadata);
    }

    public function getName(): string
    {
        return 'file_format';
    }

    public function supports(UploadedFile $file): bool
    {
        return true; // This validator supports all files for format checking
    }

    /**
     * @return array<string>
     */
    private function getAllowedExtensions(): array
    {
        $extensions = [];

        foreach ($this->allowedFormats as $format) {
            array_push($extensions, ...$format['extensions']);
        }

        return array_unique($extensions);
    }

    /**
     * @return array<string>
     */
    private function getAllowedMimeTypes(): array
    {
        $mimeTypes = [];

        foreach ($this->allowedFormats as $format) {
            array_push($mimeTypes, ...$format['mime_types']);
        }

        return array_unique($mimeTypes);
    }

    private function isExtensionMimeTypeConsistent(string $extension, string $mimeType): bool
    {
        foreach ($this->allowedFormats as $format) {
            if (in_array($extension, $format['extensions'], true)
                && in_array($mimeType, $format['mime_types'], true)) {
                return true;
            }
        }

        return false;
    }
}
