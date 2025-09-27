<?php

declare(strict_types=1);

namespace App\Http\Requests\DocumentProcessing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос на получение статистики по документам.
 */
final class GetStatsRequest extends FormRequest
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
            'period' => [
                'sometimes',
                'string',
                'in:today,week,month,quarter,year,all',
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
            'period.in' => 'Недопустимый период. Доступны: today, week, month, quarter, year, all.',
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
        // Устанавливаем значение по умолчанию
        $this->merge([
            'period' => $this->get('period', 'month'),
        ]);
    }

    /**
     * Получает период для статистики.
     */
    public function getPeriod(): string
    {
        $period = $this->validated('period');

        return is_string($period) ? $period : 'month';
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