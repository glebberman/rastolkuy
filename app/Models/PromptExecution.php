<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Выполнение промпта - запись о конкретном запросе к LLM.
 *
 * Содержит полную информацию о выполнении: входные параметры,
 * сгенерированный промпт, ответ модели, метрики производительности
 * и качества.
 *
 * @property int $id Уникальный идентификатор выполнения
 * @property int $prompt_system_id Идентификатор системы промптов
 * @property int|null $prompt_template_id Идентификатор использованного шаблона
 * @property string $execution_id Внешний идентификатор выполнения
 * @property string $rendered_prompt Сгенерированный итоговый промпт
 * @property string|null $llm_response Ответ от языковой модели
 * @property array<string, mixed> $input_variables Входные переменные
 * @property string|null $model_used Название использованной модели
 * @property int|null $tokens_used Количество использованных токенов
 * @property float|null $execution_time_ms Время выполнения в миллисекундах
 * @property float|null $cost_usd Стоимость выполнения в долларах США
 * @property 'completed'|'failed'|'pending' $status Статус выполнения
 * @property string|null $error_message Сообщение об ошибке при неудаче
 * @property array<string, mixed>|null $quality_metrics Метрики качества ответа
 * @property array<string, mixed>|null $metadata Дополнительные метаданные
 * @property Carbon|null $started_at Время начала выполнения
 * @property Carbon|null $completed_at Время завершения выполнения
 * @property Carbon|null $created_at Дата создания записи
 * @property Carbon|null $updated_at Дата последнего обновления
 * @property PromptSystem $promptSystem Связанная система промптов
 * @property PromptTemplate|null $promptTemplate Использованный шаблон
 */
final class PromptExecution extends Model
{
    protected $table = 'prompt_executions';

    protected $fillable = [
        'prompt_system_id',
        'prompt_template_id',
        'execution_id',
        'rendered_prompt',
        'llm_response',
        'input_variables',
        'model_used',
        'tokens_used',
        'execution_time_ms',
        'cost_usd',
        'status',
        'error_message',
        'quality_metrics',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'input_variables' => 'array',
        'quality_metrics' => 'array',
        'metadata' => 'array',
        'execution_time_ms' => 'decimal:2',
        'cost_usd' => 'decimal:6',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Получить систему промптов для данного выполнения.
     *
     * @return BelongsTo<PromptSystem, $this>
     */
    public function promptSystem(): BelongsTo
    {
        return $this->belongsTo(PromptSystem::class);
    }

    /**
     * Получить шаблон промпта, использованный в данном выполнении.
     *
     * @return BelongsTo<PromptTemplate, $this>
     */
    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }
}
