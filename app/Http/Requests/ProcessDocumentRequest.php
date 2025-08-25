<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\DocumentProcessing;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Можно добавить авторизацию по мере необходимости
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,txt',
                'max:52428800', // 50MB максимум
            ],
            'task_type' => [
                'required',
                'string',
                Rule::in([
                    DocumentProcessing::TASK_TRANSLATION,
                    DocumentProcessing::TASK_CONTRADICTION,
                    DocumentProcessing::TASK_AMBIGUITY,
                ]),
            ],
            'anchor_at_start' => [
                'sometimes',
                'boolean',
            ],
            'options' => [
                'sometimes',
                'array',
            ],
            'options.model' => [
                'sometimes',
                'string',
                Rule::in([
                    'claude-3-5-sonnet-20241022',
                    'claude-3-5-haiku-20241022',
                    'claude-sonnet-4',
                ]),
            ],
            'options.max_tokens' => [
                'sometimes',
                'integer',
                'min:100',
                'max:8000',
            ],
            'options.temperature' => [
                'sometimes',
                'numeric',
                'min:0.0',
                'max:2.0',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Файл для обработки обязателен.',
            'file.file' => 'Загруженный файл должен быть валидным файлом.',
            'file.mimes' => 'Поддерживаются только файлы форматов: PDF, DOC, DOCX, TXT.',
            'file.max' => 'Размер файла не должен превышать 50MB.',
            'task_type.required' => 'Тип задачи обязателен.',
            'task_type.in' => 'Недопустимый тип задачи. Разрешены: translation, contradiction, ambiguity.',
            'anchor_at_start.boolean' => 'Параметр anchor_at_start должен быть boolean.',
            'options.array' => 'Опции должны быть переданы в виде объекта.',
            'options.model.in' => 'Недопустимая модель. Поддерживаются: claude-3-5-sonnet-20241022, claude-3-5-haiku-20241022, claude-sonnet-4.',
            'options.max_tokens.min' => 'Минимальное количество токенов: 100.',
            'options.max_tokens.max' => 'Максимальное количество токенов: 8000.',
            'options.temperature.min' => 'Минимальная температура: 0.0.',
            'options.temperature.max' => 'Максимальная температура: 2.0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'файл',
            'task_type' => 'тип задачи',
            'anchor_at_start' => 'позиция якорей',
            'options.model' => 'модель',
            'options.max_tokens' => 'максимальные токены',
            'options.temperature' => 'температура',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Устанавливаем значения по умолчанию
        if (!$this->has('anchor_at_start')) {
            $this->merge(['anchor_at_start' => false]);
        }

        if (!$this->has('options')) {
            $this->merge(['options' => []]);
        }
    }
}