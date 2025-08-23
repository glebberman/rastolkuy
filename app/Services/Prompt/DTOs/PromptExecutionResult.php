<?php

declare(strict_types=1);

namespace App\Services\Prompt\DTOs;

final readonly class PromptExecutionResult
{
    public function __construct(
        public string $executionId,
        public string $response,
        public float $executionTimeMs,
        public int $tokensUsed,
        public float $costUsd,
        public array $qualityMetrics,
        public array $metadata = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return !empty($this->response);
    }

    public function getQualityScore(): ?float
    {
        return $this->qualityMetrics['overall_score'] ?? null;
    }

    public function hasHighQuality(): bool
    {
        $score = $this->getQualityScore();

        return $score !== null && $score >= 0.8;
    }
}
