<?php

declare(strict_types=1);

namespace App\Services\Validation\Contracts;

use App\Services\Validation\DTOs\ValidationResult;
use Illuminate\Http\UploadedFile;

interface ValidatorInterface
{
    /**
     * Validate the uploaded file.
     */
    public function validate(UploadedFile $file): ValidationResult;

    /**
     * Get validator name for identification.
     */
    public function getName(): string;

    /**
     * Check if validator supports this file type.
     */
    public function supports(UploadedFile $file): bool;
}
