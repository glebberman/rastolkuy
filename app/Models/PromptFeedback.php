<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Обратная связь по выполнению промпта.
 *
 * Содержит оценки качества ответа языковой модели,
 * комментарии пользователей и детальные метрики
 * для улучшения системы промптов.
 *
 * @property int $id Уникальный идентификатор отзыва
 * @property int $prompt_execution_id Идентификатор связанного выполнения
 * @property string $feedback_type Тип обратной связи (quality, accuracy, relevance)
 * @property float|null $rating Числовая оценка (обычно 1-5 или 0-1)
 * @property string|null $comment Текстовый комментарий
 * @property array<string, mixed>|null $details Детализированные метрики оценки
 * @property string|null $user_type Тип пользователя (human, system, automated)
 * @property string|null $user_id Идентификатор пользователя/системы
 * @property array<string, mixed>|null $metadata Дополнительные метаданные
 * @property Carbon|null $created_at Дата создания
 * @property Carbon|null $updated_at Дата последнего обновления
 * @property PromptExecution $promptExecution Связанное выполнение промпта
 */
final class PromptFeedback extends Model
{
    protected $table = 'prompt_feedback';

    protected $fillable = [
        'prompt_execution_id',
        'feedback_type',
        'rating',
        'comment',
        'details',
        'user_type',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'details' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Получить выполнение промпта, к которому относится данная обратная связь.
     *
     * @return BelongsTo<PromptExecution, $this>
     */
    public function promptExecution(): BelongsTo
    {
        return $this->belongsTo(PromptExecution::class);
    }
}
