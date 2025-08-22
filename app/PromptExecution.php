<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function promptSystem(): BelongsTo
    {
        return $this->belongsTo(PromptSystem::class);
    }

    public function promptTemplate(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class);
    }
}
