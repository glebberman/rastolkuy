<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromptSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $systemId = $this->route('prompt_system');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('prompt_systems', 'name')->ignore($systemId),
            ],
            'type' => 'sometimes|required|string|in:translation,contradiction,ambiguity,general',
            'description' => 'nullable|string',
            'system_prompt' => 'sometimes|required|string',
            'default_parameters' => 'nullable|array',
            'schema' => 'nullable|array',
            'is_active' => 'boolean',
            'version' => 'nullable|string|max:10',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Название системы промптов обязательно',
            'name.unique' => 'Система промптов с таким названием уже существует',
            'type.required' => 'Тип системы промптов обязателен',
            'type.in' => 'Тип должен быть одним из: translation, contradiction, ambiguity, general',
            'system_prompt.required' => 'Системный промпт обязателен',
        ];
    }
}
