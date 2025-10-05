<?php

declare(strict_types=1);

namespace App\Http\Requests\Export;

use App\Models\DocumentExport;
use App\Services\Export\Validators\ExportValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Запрос на экспорт документа.
 */
final class ExportDocumentRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Правила валидации для запроса.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_id' => [
                'required',
                // Accept both UUID and integer ID
                function ($attribute, $value, $fail) {
                    // Check if it's a valid UUID
                    if (is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                        // Check if document with this UUID exists
                        if (!\App\Models\DocumentProcessing::where('uuid', $value)->exists()) {
                            $fail('Документ с указанным ID не найден.');
                        }
                        return;
                    }

                    // Check if it's an integer ID
                    if (is_numeric($value)) {
                        if (!\App\Models\DocumentProcessing::where('id', (int)$value)->exists()) {
                            $fail('Документ с указанным ID не найден.');
                        }
                        return;
                    }

                    $fail('ID документа должен быть UUID или числом.');
                },
            ],
            'format' => [
                'required',
                'string',
                Rule::in([
                    DocumentExport::FORMAT_HTML,
                    DocumentExport::FORMAT_DOCX,
                    DocumentExport::FORMAT_PDF,
                ]),
            ],
            'options' => [
                'sometimes',
                'array',
            ],
            'options.include_original' => [
                'sometimes',
                'boolean',
            ],
            'options.include_anchors' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Кастомные сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_id.required' => 'ID документа обязателен для заполнения.',
            'document_id.integer' => 'ID документа должен быть числом.',
            'document_id.exists' => 'Документ с указанным ID не найден.',
            'format.required' => 'Формат экспорта обязателен для заполнения.',
            'format.in' => 'Недопустимый формат экспорта. Разрешены: html, docx, pdf.',
            'options.array' => 'Опции должны быть массивом.',
            'options.include_original.boolean' => 'Параметр include_original должен быть булевым значением.',
            'options.include_anchors.boolean' => 'Параметр include_anchors должен быть булевым значением.',
        ];
    }

    /**
     * Подготавливает данные для валидации.
     */
    protected function prepareForValidation(): void
    {
        // Нормализуем опции если они не переданы
        if (!$this->has('options')) {
            $this->merge(['options' => []]);
        }

        // Устанавливаем значения по умолчанию для опций
        $options = $this->get('options', []);
        if (is_array($options)) {
            $options['include_original'] = $options['include_original'] ?? true;
            $options['include_anchors'] = $options['include_anchors'] ?? false;
            $this->merge(['options' => $options]);
        }
    }

    /**
     * Получает ID документа (преобразует UUID в числовой ID если нужно).
     */
    public function getDocumentId(): int
    {
        $documentId = $this->validated('document_id');

        // If it's a UUID string, convert to integer ID
        if (is_string($documentId) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $documentId)) {
            $document = \App\Models\DocumentProcessing::where('uuid', $documentId)->first();
            return $document ? $document->id : 0;
        }

        // If it's already an integer or numeric string
        if (is_int($documentId)) {
            return $documentId;
        }

        if (is_string($documentId) || is_numeric($documentId)) {
            return (int) $documentId;
        }

        return 0;
    }

    /**
     * Получает формат экспорта.
     */
    public function getExportFormat(): string
    {
        $format = $this->validated('format');

        return is_string($format) ? $format : '';
    }

    /**
     * Получает опции экспорта.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        $options = $this->validated('options', []);

        return is_array($options) ? $options : [];
    }
}