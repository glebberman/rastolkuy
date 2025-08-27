<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromptFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt_execution_id' => [
                'required',
                'integer',
                Rule::exists('prompt_executions', 'id'),
            ],
            'feedback_type' => [
                'required',
                'string',
                'in:quality,accuracy,relevance,performance,usability,overall',
            ],
            'rating' => 'nullable|numeric|min:0|max:5',
            'comment' => 'nullable|string',
            'details' => 'nullable|array',
            'user_type' => 'nullable|string|in:human,system,automated',
            'user_id' => 'nullable|string',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'prompt_execution_id.required' => 'Выполнение промпта обязательно',
            'prompt_execution_id.exists' => 'Указанное выполнение промпта не найдено',
            'feedback_type.required' => 'Тип обратной связи обязателен',
            'feedback_type.in' => 'Тип должен быть одним из: quality, accuracy, relevance, performance, usability, overall',
            'rating.numeric' => 'Оценка должна быть числом',
            'rating.min' => 'Оценка не может быть меньше 0',
            'rating.max' => 'Оценка не может быть больше 5',
            'user_type.in' => 'Тип пользователя должен быть одним из: human, system, automated',
        ];
    }
}
