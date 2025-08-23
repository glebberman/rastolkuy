<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Система промптов - основная конфигурация для группы шаблонов.
 *
 * Определяет общие параметры, системный промпт, схему валидации
 * и другие настройки для связанных шаблонов промптов.
 *
 * @property int $id Уникальный идентификатор системы
 * @property string $name Название системы промптов
 * @property string $type Тип системы (translation, analysis, generation, etc.)
 * @property string|null $description Описание назначения системы
 * @property string|null $system_prompt Базовый системный промпт
 * @property array<string, mixed>|null $default_parameters Параметры по умолчанию
 * @property array<string, mixed>|null $schema JSON-схема для валидации ответов
 * @property bool $is_active Активна ли система для использования
 * @property string|null $version Версия системы промптов
 * @property array<string, mixed>|null $metadata Дополнительные метаданные
 * @property Carbon|null $created_at Дата создания
 * @property Carbon|null $updated_at Дата последнего обновления
 * @property Collection<int, PromptTemplate> $templates Все шаблоны системы
 * @property Collection<int, PromptExecution> $executions Все выполнения системы
 * @property Collection<int, PromptTemplate> $activeTemplates Активные шаблоны системы
 */
final class PromptSystem extends Model
{
    protected $table = 'prompt_systems';

    protected $fillable = [
        'name',
        'type',
        'description',
        'system_prompt',
        'default_parameters',
        'schema',
        'is_active',
        'version',
        'metadata',
    ];

    protected $casts = [
        'default_parameters' => 'array',
        'schema' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Получить все шаблоны промптов данной системы.
     *
     * @return HasMany<PromptTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(PromptTemplate::class);
    }

    /**
     * Получить все выполнения промптов данной системы.
     *
     * @return HasMany<PromptExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(PromptExecution::class);
    }

    /**
     * Получить только активные шаблоны промптов данной системы.
     *
     * @return HasMany<PromptTemplate, $this>
     */
    public function activeTemplates(): HasMany
    {
        return $this->templates()->where('is_active', true);
    }
}
