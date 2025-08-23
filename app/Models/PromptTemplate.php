<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Шаблон промпта для системы генерации запросов к LLM.
 *
 * Содержит параметризованный шаблон с переменными, которые подставляются
 * при выполнении запроса к языковой модели.
 *
 * @property int $id Уникальный идентификатор шаблона
 * @property int $prompt_system_id Идентификатор связанной системы промптов
 * @property string $name Название шаблона
 * @property string $template Текст шаблона с переменными {{variable}}
 * @property array<int, string>|null $required_variables Список обязательных переменных
 * @property array<int, string>|null $optional_variables Список опциональных переменных
 * @property string|null $description Описание назначения шаблона
 * @property bool $is_active Активен ли шаблон для использования
 * @property array<string, mixed>|null $metadata Дополнительные метаданные
 * @property Carbon|null $created_at Дата создания
 * @property Carbon|null $updated_at Дата последнего обновления
 * @property PromptSystem $promptSystem Связанная система промптов
 * @property Collection<int, PromptExecution> $executions Выполнения шаблона
 */
final class PromptTemplate extends Model
{
    protected $table = 'prompt_templates';

    protected $fillable = [
        'prompt_system_id',
        'name',
        'template',
        'required_variables',
        'optional_variables',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'required_variables' => 'array',
        'optional_variables' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Получить систему промптов, к которой принадлежит данный шаблон.
     *
     * @return BelongsTo<PromptSystem, $this>
     */
    public function promptSystem(): BelongsTo
    {
        return $this->belongsTo(PromptSystem::class);
    }

    /**
     * Получить все выполнения данного шаблона.
     *
     * @return HasMany<PromptExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(PromptExecution::class);
    }
}
