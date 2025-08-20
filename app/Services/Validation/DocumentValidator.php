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
use InvalidArgumentException;

final class DocumentValidator
{
    private const int MAX_EXECUTION_TIME_SECONDS = 30;
    private const int PERFORMANCE_WARNING_THRESHOLD_MS = 5000;
    private const int DEFAULT_MAX_FILE_SIZE = 10485760; // 10MB
    private const int DEFAULT_MIN_FILE_SIZE = 1024; // 1KB
    
    /** @var array<string> */
    private const array CRITICAL_VALIDATORS = [
        'file_format',
        'file_size',
        'security',
    ];

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
        
        $this->validateConfiguration();
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
                
                // Check for timeout (max 30 seconds per validator)
                set_time_limit(self::MAX_EXECUTION_TIME_SECONDS);
                
                $result = $validator->validate($file);
                $validatorEndTime = microtime(true);
                $executionTime = round(($validatorEndTime - $validatorStartTime) * 1000, 2);

                $validatorResults[$validator->getName()] = [
                    'result' => $result,
                    'execution_time' => $executionTime,
                ];

                $overallResult = $overallResult->merge($result);

                Log::debug('Validator completed', [
                    'validator' => $validator->getName(),
                    'is_valid' => $result->isValid,
                    'errors_count' => count($result->errors),
                    'warnings_count' => count($result->warnings),
                    'execution_time_ms' => $executionTime,
                ]);

                // Warn if validator takes too long
                if ($executionTime > self::PERFORMANCE_WARNING_THRESHOLD_MS) {
                    Log::warning('Validator execution time exceeded threshold', [
                        'validator' => $validator->getName(),
                        'execution_time_ms' => $executionTime,
                        'filename' => $file->getClientOriginalName(),
                    ]);
                }

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
        return in_array($validator->getName(), self::CRITICAL_VALIDATORS, true);
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
                array_push($extensions, ...$format['extensions']);
            }
        }
        
        return array_values(array_unique($extensions));
    }

    /**
     * Get maximum allowed file size
     */
    public function getMaxFileSize(): int
    {
        $size = config('document_validation.file_size.max_size', self::DEFAULT_MAX_FILE_SIZE);
        return is_numeric($size) ? (int) $size : self::DEFAULT_MAX_FILE_SIZE;
    }

    /**
     * Validate DocumentValidator configuration
     */
    private function validateConfiguration(): void
    {
        if (empty($this->validators)) {
            throw new InvalidArgumentException('No validators configured for DocumentValidator');
        }

        // Validate file size configuration
        $maxSize = config('document_validation.file_size.max_size');
        $minSize = config('document_validation.file_size.min_size');
        
        if (!is_numeric($maxSize) || $maxSize <= 0) {
            Log::warning('Invalid max file size configuration, using default', [
                'configured_value' => $maxSize,
                'default_value' => self::DEFAULT_MAX_FILE_SIZE,
            ]);
        }
        
        if (!is_numeric($minSize) || $minSize <= 0) {
            Log::warning('Invalid min file size configuration, using default', [
                'configured_value' => $minSize,
                'default_value' => self::DEFAULT_MIN_FILE_SIZE,
            ]);
        }

        // Validate allowed formats configuration
        $allowedFormats = config('document_validation.allowed_formats', []);
        if (!is_array($allowedFormats) || empty($allowedFormats)) {
            throw new InvalidArgumentException('No allowed file formats configured');
        }

        foreach ($allowedFormats as $formatName => $format) {
            if (!is_array($format) ||
                !is_array($format['extensions']) || 
                !is_array($format['mime_types']) ||
                empty($format['extensions']) || 
                empty($format['mime_types'])) {
                throw new InvalidArgumentException(
                    "Invalid format configuration for '{$formatName}': must have non-empty extensions and mime_types arrays"
                );
            }
        }

        Log::info('DocumentValidator configuration validated successfully', [
            'validators_count' => count($this->validators),
            'supported_formats' => array_keys($allowedFormats),
        ]);
    }
}