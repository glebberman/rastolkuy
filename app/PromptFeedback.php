<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function promptExecution(): BelongsTo
    {
        return $this->belongsTo(PromptExecution::class);
    }
}
