<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function templates(): HasMany
    {
        return $this->hasMany(PromptTemplate::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PromptExecution::class);
    }

    public function activeTemplates(): HasMany
    {
        return $this->templates()->where('is_active', true);
    }
}
