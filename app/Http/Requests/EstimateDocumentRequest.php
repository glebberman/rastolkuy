<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class EstimateDocumentRequest extends DocumentUuidRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string|ValidationRule>
     */
    public function rules(): array
    {
        return array_merge($this->getUuidRules(), [
            'model' => [
                'sometimes',
                'string',
                Rule::in([
                    'claude-3-5-sonnet-20241022',
                    'claude-3-5-haiku-20241022',
                    'claude-sonnet-4',
                ]),
            ],
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->getUuidMessages(), [
            'model.in' => 'Недопустимая модель. Поддерживаются: claude-3-5-sonnet-20241022, claude-3-5-haiku-20241022, claude-sonnet-4.',
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->getUuidAttributes(), [
            'model' => 'модель',
        ]);
    }
}
