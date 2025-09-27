<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentProcessing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Запрос на получение списка документов пользователя.
 */
final class GetDocumentListRequest extends FormRequest
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
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::in(['pending', 'processing', 'analyzing', 'completed', 'failed', 'uploaded', 'estimated']),
            ],
            'task_type' => [
                'sometimes',
                'string',
                Rule::in(['translation', 'contradiction', 'ambiguity']),
            ],
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in(['created_at', 'updated_at', 'completed_at', 'file_size', 'processing_time_seconds']),
            ],
            'sort_direction' => [
                'sometimes',
                'string',
                Rule::in(['asc', 'desc']),
            ],
            'search' => [
                'sometimes',
                'string',
                'max:255',
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
            'page.integer' => 'Номер страницы должен быть числом.',
            'page.min' => 'Номер страницы должен быть больше 0.',
            'per_page.integer' => 'Количество элементов на странице должно быть числом.',
            'per_page.min' => 'Количество элементов на странице должно быть больше 0.',
            'per_page.max' => 'Максимальное количество элементов на странице: 100.',
            'status.in' => 'Недопустимый статус документа.',
            'task_type.in' => 'Недопустимый тип задачи.',
            'sort_by.in' => 'Недопустимое поле для сортировки.',
            'sort_direction.in' => 'Направление сортировки должно быть asc или desc.',
            'search.max' => 'Поисковый запрос не может быть длиннее 255 символов.',
        ];
    }

    /**
     * Подготавливает данные для валидации.
     */
    protected function prepareForValidation(): void
    {
        // Устанавливаем значения по умолчанию
        $this->merge([
            'page' => $this->get('page', 1),
            'per_page' => $this->get('per_page', 15),
            'sort_by' => $this->get('sort_by', 'created_at'),
            'sort_direction' => $this->get('sort_direction', 'desc'),
        ]);
    }

    /**
     * Получает номер страницы.
     */
    public function getPage(): int
    {
        $page = $this->validated('page');

        if (is_int($page)) {
            return $page;
        }

        if (is_string($page) && is_numeric($page)) {
            return (int) $page;
        }

        return 1;
    }

    /**
     * Получает количество элементов на странице.
     */
    public function getPerPage(): int
    {
        $perPage = $this->validated('per_page');

        if (is_int($perPage)) {
            return $perPage;
        }

        if (is_string($perPage) && is_numeric($perPage)) {
            return (int) $perPage;
        }

        return 15;
    }

    /**
     * Получает статус для фильтрации.
     */
    public function getStatus(): ?string
    {
        $status = $this->validated('status');

        return is_string($status) ? $status : null;
    }

    /**
     * Получает тип задачи для фильтрации.
     */
    public function getTaskType(): ?string
    {
        $taskType = $this->validated('task_type');

        return is_string($taskType) ? $taskType : null;
    }

    /**
     * Получает поле для сортировки.
     */
    public function getSortBy(): string
    {
        $sortBy = $this->validated('sort_by');

        return is_string($sortBy) ? $sortBy : 'created_at';
    }

    /**
     * Получает направление сортировки.
     */
    public function getSortDirection(): string
    {
        $sortDirection = $this->validated('sort_direction');

        return is_string($sortDirection) ? $sortDirection : 'desc';
    }

    /**
     * Получает поисковый запрос.
     */
    public function getSearch(): ?string
    {
        $search = $this->validated('search');

        return is_string($search) && !empty(trim($search)) ? trim($search) : null;
    }
}