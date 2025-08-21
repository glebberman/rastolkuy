<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Validation\DocumentValidator;
use App\Services\Validation\DTOs\ValidationResult;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

final class DocumentValidationRule implements ValidationRule
{
    private DocumentValidator $validator;

    private ?ValidationResult $lastResult = null;

    public function __construct(DocumentValidator $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Run the validation rule.
     *
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            $fail('The :attribute must be a valid uploaded file.');

            return;
        }

        $this->lastResult = $this->validator->validate($value);

        if (!$this->lastResult->isValid) {
            foreach ($this->lastResult->errors as $error) {
                $fail($error);
            }
        }

        // Also report warnings as notices (non-blocking)
        if ($this->lastResult->hasWarnings()) {
            foreach ($this->lastResult->warnings as $warning) {
                // In Laravel, we can't easily show warnings without failing validation
                // But we can log them for monitoring
                Log::info('Document validation warning', [
                    'file' => $value->getClientOriginalName(),
                    'warning' => $warning,
                ]);
            }
        }
    }

    /**
     * Get the last validation result for access to metadata.
     */
    public function getLastResult(): ?ValidationResult
    {
        return $this->lastResult;
    }
}
