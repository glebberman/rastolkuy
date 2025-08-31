<?php

declare(strict_types=1);

namespace App\Http\Requests;

class DocumentResultRequest extends DocumentUuidRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->getUuidRules();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->getUuidMessages();
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->getUuidAttributes();
    }
}
