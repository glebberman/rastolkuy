<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ExecutePromptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'system_name' => 'required|string|exists:prompt_systems,name',
            'template_name' => 'nullable|string',
            'variables' => 'required|array',
            'options' => 'nullable|array',
            'options.model' => 'nullable|string',
            'options.max_tokens' => 'nullable|integer|min:1|max:8192',
            'options.temperature' => 'nullable|numeric|min:0|max:2',
        ];
    }

    public function messages(): array
    {
        return [
            'system_name.required' => 'Название системы промптов обязательно',
            'system_name.exists' => 'Система промптов не найдена',
            'variables.required' => 'Переменные для промпта обязательны',
            'variables.array' => 'Переменные должны быть массивом',
            'options.max_tokens.max' => 'Максимальное количество токенов не может превышать 8192',
            'options.temperature.max' => 'Температура не может превышать 2',
        ];
    }
}
