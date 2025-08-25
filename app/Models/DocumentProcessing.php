<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Модель для отслеживания обработки документов.
 *
 * @property int $id
 * @property string $uuid Уникальный идентификатор задачи
 * @property string $original_filename Оригинальное название файла
 * @property string $file_path Путь к загруженному файлу
 * @property string $file_type MIME тип файла
 * @property int $file_size Размер файла в байтах
 * @property string $task_type Тип задачи (translation, contradiction, ambiguity)
 * @property array<string, mixed> $options Опции обработки
 * @property bool $anchor_at_start Позиция якорей (true = начало, false = конец)
 * @property 'completed'|'failed'|'pending'|'processing' $status Статус обработки
 * @property string|null $result Результат обработки
 * @property array<string, mixed>|null $error_details Детали ошибки
 * @property array<string, mixed>|null $processing_metadata Метаданные обработки
 * @property float|null $processing_time_seconds Время обработки в секундах
 * @property float|null $cost_usd Стоимость обработки в USD
 * @property Carbon|null $started_at Время начала обработки
 * @property Carbon|null $completed_at Время завершения обработки
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class DocumentProcessing extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Возможные статусы обработки.
     */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_PROCESSING = 'processing';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';

    /**
     * Возможные типы задач.
     */
    public const string TASK_TRANSLATION = 'translation';
    public const string TASK_CONTRADICTION = 'contradiction';
    public const string TASK_AMBIGUITY = 'ambiguity';

    protected $fillable = [
        'uuid',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'task_type',
        'options',
        'anchor_at_start',
        'status',
        'result',
        'error_details',
        'processing_metadata',
        'processing_time_seconds',
        'cost_usd',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'options' => 'array',
        'anchor_at_start' => 'boolean',
        'error_details' => 'array',
        'processing_metadata' => 'array',
        'processing_time_seconds' => 'float',
        'cost_usd' => 'float',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $hidden = [
        'file_path', // Скрываем внутренний путь к файлу
    ];

    /**
     * Проверяет, завершена ли обработка.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Проверяет, провалилась ли обработка.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Проверяет, в процессе ли обработка.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Проверяет, ожидает ли обработка.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Отмечает начало обработки.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Отмечает успешное завершение обработки.
     */
    public function markAsCompleted(string $result, array $metadata = [], ?float $costUsd = null): void
    {
        $processingTime = $this->started_at ? now()->diffInMilliseconds($this->started_at) / 1000 : null;

        $this->update([
            'status' => self::STATUS_COMPLETED,
            'result' => $result,
            'processing_metadata' => $metadata,
            'processing_time_seconds' => $processingTime,
            'cost_usd' => $costUsd,
            'completed_at' => now(),
        ]);
    }

    /**
     * Отмечает провал обработки.
     */
    public function markAsFailed(string $error, array $errorDetails = []): void
    {
        $processingTime = $this->started_at ? now()->diffInMilliseconds($this->started_at) / 1000 : null;

        $this->update([
            'status' => self::STATUS_FAILED,
            'error_details' => array_merge(['message' => $error], $errorDetails),
            'processing_time_seconds' => $processingTime,
            'completed_at' => now(),
        ]);
    }

    /**
     * Получает прогресс обработки в процентах.
     */
    public function getProgressPercentage(): int
    {
        return match ($this->status) {
            self::STATUS_PENDING => 0,
            self::STATUS_PROCESSING => 50,
            self::STATUS_COMPLETED => 100,
            self::STATUS_FAILED => 0,
        };
    }

    /**
     * Получает человекочитаемое описание статуса.
     */
    public function getStatusDescription(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Ожидает обработки',
            self::STATUS_PROCESSING => 'Обрабатывается',
            self::STATUS_COMPLETED => 'Завершена',
            self::STATUS_FAILED => 'Ошибка обработки',
        };
    }

    /**
     * Получает человекочитаемое описание типа задачи.
     */
    public function getTaskTypeDescription(): string
    {
        return match ($this->task_type) {
            self::TASK_TRANSLATION => 'Перевод в простой язык',
            self::TASK_CONTRADICTION => 'Поиск противоречий',
            self::TASK_AMBIGUITY => 'Поиск неоднозначностей',
            default => 'Неизвестный тип задачи',
        };
    }

    /**
     * Скоуп для получения завершенных обработок.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Скоуп для получения активных обработок.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING]);
    }

    /**
     * Скоуп для получения проваленных обработок.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
