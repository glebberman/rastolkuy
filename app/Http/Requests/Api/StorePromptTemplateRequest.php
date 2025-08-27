<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt_system_id' => [
                'required',
                'integer',
                Rule::exists('prompt_systems', 'id')->where('is_active', true),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('prompt_templates', 'name')
                    ->where(function ($query): void {
                        $promptSystemId = $this->input('prompt_system_id');
                        if (is_numeric($promptSystemId)) {
                            $query->where('prompt_system_id', (int) $promptSystemId);
                        }
                    }),
            ],
            'template' => 'required|string',
            'required_variables' => 'nullable|array',
            'required_variables.*' => 'string|max:100',
            'optional_variables' => 'nullable|array',
            'optional_variables.*' => 'string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'prompt_system_id.required' => 'Система промптов обязательна',
            'prompt_system_id.exists' => 'Указанная система промптов не найдена или неактивна',
            'name.required' => 'Название шаблона обязательно',
            'name.unique' => 'Шаблон с таким названием уже существует в данной системе',
            'template.required' => 'Текст шаблона обязателен',
            'required_variables.*.string' => 'Названия переменных должны быть строками',
            'optional_variables.*.string' => 'Названия переменных должны быть строками',
        ];
    }
}
