<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentProcessing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Запрос на получение админского списка всех документов.
 */
final class GetAdminDocumentListRequest extends FormRequest
{
    /**
     * Определяет, авторизован ли пользователь для выполнения этого запроса.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('admin');
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
            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in(['created_at', 'updated_at', 'completed_at', 'file_size', 'processing_time_seconds', 'cost_usd']),
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
            'date_from' => [
                'sometimes',
                'date',
            ],
            'date_to' => [
                'sometimes',
                'date',
                'after_or_equal:date_from',
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
            'user_id.integer' => 'ID пользователя должен быть числом.',
            'user_id.exists' => 'Пользователь с указанным ID не найден.',
            'sort_by.in' => 'Недопустимое поле для сортировки.',
            'sort_direction.in' => 'Направление сортировки должно быть asc или desc.',
            'search.max' => 'Поисковый запрос не может быть длиннее 255 символов.',
            'date_from.date' => 'Дата начала должна быть в правильном формате.',
            'date_to.date' => 'Дата окончания должна быть в правильном формате.',
            'date_to.after_or_equal' => 'Дата окончания должна быть не раньше даты начала.',
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
            'per_page' => $this->get('per_page', 20),
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

        return is_int($page) ? $page : (int) $page;
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

        return 20;
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
     * Получает ID пользователя для фильтрации.
     */
    public function getUserId(): ?int
    {
        $userId = $this->validated('user_id');

        if (is_int($userId)) {
            return $userId;
        }

        if (is_string($userId) && is_numeric($userId)) {
            return (int) $userId;
        }

        return null;
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

    /**
     * Получает дату начала фильтрации.
     */
    public function getDateFrom(): ?string
    {
        $dateFrom = $this->validated('date_from');

        return is_string($dateFrom) ? $dateFrom : null;
    }

    /**
     * Получает дату окончания фильтрации.
     */
    public function getDateTo(): ?string
    {
        $dateTo = $this->validated('date_to');

        return is_string($dateTo) ? $dateTo : null;
    }
}