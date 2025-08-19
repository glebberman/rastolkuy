<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtractorDemoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document' => 'required|file|max:10240', // max 10MB
            'config' => 'string|in:default,fast,streaming,large',
        ];
    }

    public function messages(): array
    {
        return [
            'document.required' => 'Document file is required',
            'document.file' => 'Document must be a valid file',
            'document.max' => 'Document file size cannot exceed 10MB',
            'config.in' => 'Config must be one of: default, fast, streaming, large',
        ];
    }

    public function getConfigType(): string
    {
        return $this->input('config', 'default');
    }
}