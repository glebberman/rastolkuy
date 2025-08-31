<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class DocumentUuidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the base validation rules for UUID.
     *
     * @return array<string, array<mixed>|string>
     */
    protected function getUuidRules(): array
    {
        return [
            'uuid' => [
                'required',
                'string',
                'uuid',
            ],
        ];
    }

    /**
     * Get the base custom messages for UUID validation.
     *
     * @return array<string, string>
     */
    protected function getUuidMessages(): array
    {
        return [
            'uuid.required' => 'UUID документа обязателен.',
            'uuid.uuid' => 'UUID документа должен быть в правильном формате.',
        ];
    }

    /**
     * Get the base custom attributes for UUID validation.
     *
     * @return array<string, string>
     */
    protected function getUuidAttributes(): array
    {
        return [
            'uuid' => 'UUID документа',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'uuid' => $this->route('uuid'),
        ]);
    }
}
