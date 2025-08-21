<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\DocumentValidationRule;
use App\Services\Validation\DocumentValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ExtractorDemoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validator = new DocumentValidator();

        return [
            'document' => [
                'required',
                'file',
                sprintf('max:%d', $validator->getMaxFileSize() / 1024), // Convert bytes to KB for Laravel validation
                new DocumentValidationRule($validator),
            ],
            'config' => 'string|in:default,fast,streaming,large',
        ];
    }

    public function messages(): array
    {
        $validator = new DocumentValidator();
        $maxSizeMB = round($validator->getMaxFileSize() / 1024 / 1024, 1);
        $supportedExtensions = implode(', ', $validator->getSupportedExtensions());

        return [
            'document.required' => 'Document file is required',
            'document.file' => 'Document must be a valid file',
            'document.max' => sprintf('Document file size cannot exceed %sMB', $maxSizeMB),
            'config.in' => 'Config must be one of: default, fast, streaming, large',
        ];
    }

    public function getConfigType(): string
    {
        $config = $this->input('config', 'default');

        return is_string($config) ? $config : 'default';
    }

    /**
     * Get the uploaded document file.
     */
    public function getDocument(): UploadedFile
    {
        return $this->file('document');
    }
}
