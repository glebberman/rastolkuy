<?php

declare(strict_types=1);

namespace App\Services\Validation;

use App\Services\Validation\Contracts\ValidatorInterface;
use App\Services\Validation\DTOs\ValidationResult;
use App\Services\Validation\Validators\ContentValidator;
use App\Services\Validation\Validators\FileFormatValidator;
use App\Services\Validation\Validators\FileSizeValidator;
use App\Services\Validation\Validators\SecurityValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

final class DocumentValidator
{
    /**
     * @var array<ValidatorInterface>
     */
    private array $validators;

    public function __construct()
    {
        $this->validators = [
            new FileFormatValidator(),
            new FileSizeValidator(),
            new SecurityValidator(),
            new ContentValidator(),
        ];
    }

    /**
     * Validate uploaded document through all validators
     */
    public function validate(UploadedFile $file): ValidationResult
    {
        $startTime = microtime(true);
        
        Log::info('Starting document validation', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        $overallResult = ValidationResult::valid(['validation_start_time' => $startTime]);
        $validatorResults = [];

        foreach ($this->validators as $validator) {
            if (!$validator->supports($file)) {
                Log::debug('Skipping validator (not supported)', [
                    'validator' => $validator->getName(),
                    'filename' => $file->getClientOriginalName(),
                ]);
                continue;
            }

            try {
                $validatorStartTime = microtime(true);
                $result = $validator->validate($file);
                $validatorEndTime = microtime(true);

                $validatorResults[$validator->getName()] = [
                    'result' => $result,
                    'execution_time' => round(($validatorEndTime - $validatorStartTime) * 1000, 2),
                ];

                $overallResult = $overallResult->merge($result);

                Log::debug('Validator completed', [
                    'validator' => $validator->getName(),
                    'is_valid' => $result->isValid,
                    'errors_count' => count($result->errors),
                    'warnings_count' => count($result->warnings),
                    'execution_time_ms' => $validatorResults[$validator->getName()]['execution_time'],
                ]);

                // If critical validation fails, stop processing
                if (!$result->isValid && $this->isCriticalValidator($validator)) {
                    Log::warning('Critical validator failed, stopping validation', [
                        'validator' => $validator->getName(),
                        'errors' => $result->errors,
                    ]);
                    break;
                }

            } catch (\Throwable $e) {
                Log::error('Validator threw exception', [
                    'validator' => $validator->getName(),
                    'filename' => $file->getClientOriginalName(),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $errorResult = ValidationResult::invalid([
                    sprintf('Validation failed for %s: %s', $validator->getName(), $e->getMessage())
                ]);
                
                $overallResult = $overallResult->merge($errorResult);
                $validatorResults[$validator->getName()] = [
                    'result' => $errorResult,
                    'execution_time' => 0,
                    'exception' => $e->getMessage(),
                ];
            }
        }

        $endTime = microtime(true);
        $totalExecutionTime = round(($endTime - $startTime) * 1000, 2);

        // Add execution metadata
        $executionMetadata = [
            'validation_end_time' => $endTime,
            'total_execution_time_ms' => $totalExecutionTime,
            'validators_executed' => array_keys($validatorResults),
            'validator_results' => $validatorResults,
        ];

        $finalResult = new ValidationResult(
            $overallResult->isValid,
            $overallResult->errors,
            $overallResult->warnings,
            array_merge($overallResult->metadata, $executionMetadata)
        );

        Log::info('Document validation completed', [
            'filename' => $file->getClientOriginalName(),
            'is_valid' => $finalResult->isValid,
            'total_errors' => count($finalResult->errors),
            'total_warnings' => count($finalResult->warnings),
            'execution_time_ms' => $totalExecutionTime,
        ]);

        return $finalResult;
    }

    /**
     * Add a custom validator
     */
    public function addValidator(ValidatorInterface $validator): self
    {
        $this->validators[] = $validator;
        return $this;
    }

    /**
     * Check if a validator is critical (validation should stop if it fails)
     */
    private function isCriticalValidator(ValidatorInterface $validator): bool
    {
        $criticalValidators = [
            'file_format',
            'file_size',
            'security',
        ];

        return in_array($validator->getName(), $criticalValidators, true);
    }

    /**
     * Get list of supported file extensions
     * 
     * @return array<string>
     */
    public function getSupportedExtensions(): array
    {
        $extensions = [];
        $formats = (array) config('document_validation.allowed_formats', []);
        
        foreach ($formats as $format) {
            if (is_array($format) && isset($format['extensions']) && is_array($format['extensions'])) {
                $extensions = array_merge($extensions, $format['extensions']);
            }
        }
        
        return array_values(array_unique($extensions));
    }

    /**
     * Get maximum allowed file size
     */
    public function getMaxFileSize(): int
    {
        $size = config('document_validation.file_size.max_size', 10485760);
        return is_numeric($size) ? (int) $size : 10485760;
    }
}