<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptSystemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:prompt_systems,name',
            'type' => 'required|string|in:translation,contradiction,ambiguity,general',
            'description' => 'nullable|string',
            'system_prompt' => 'required|string',
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
