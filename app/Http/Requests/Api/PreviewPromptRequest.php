<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PreviewPromptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'system_name' => ['string', 'max:255'],
            'template_name' => ['string', 'max:255'],
            'task_type' => ['string', 'in:translation,analysis,ambiguity'],
            'options' => ['array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'system_name.string' => 'Название системы промптов должно быть строкой',
            'template_name.string' => 'Название шаблона должно быть строкой',
            'task_type.in' => 'Тип задачи должен быть: translation, analysis или ambiguity',
            'options.array' => 'Параметры должны быть массивом',
        ];
    }
}
