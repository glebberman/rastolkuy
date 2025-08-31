<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ProcessDocumentEstimatedRequest extends DocumentUuidRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge($this->getUuidRules(), [
            'confirm' => [
                'sometimes',
                'boolean',
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
            'confirm.boolean' => 'Подтверждение должно быть boolean значением.',
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
            'confirm' => 'подтверждение',
        ]);
    }
}
