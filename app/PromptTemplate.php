<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function promptSystem(): BelongsTo
    {
        return $this->belongsTo(PromptSystem::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PromptExecution::class);
    }
}
