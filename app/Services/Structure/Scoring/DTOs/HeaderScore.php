<?php

declare(strict_types=1);

namespace App\Services\Structure\Scoring\DTOs;

/**
 * Header score result from ML-like scoring system.
 *
 * Combines visual, textual, and semantic scores to determine
 * if an element is likely to be a header/section title.
 */
final readonly class HeaderScore
{
    public function __construct(
        public float $total,
        public float $visual,
        public float $textual,
        public float $semantic,
        public string $reason = '',
    ) {
    }

    public function isLikelyHeader(float $threshold = 0.65): bool
    {
        return $this->total >= $threshold;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => round($this->total, 3),
            'visual' => round($this->visual, 3),
            'textual' => round($this->textual, 3),
            'semantic' => round($this->semantic, 3),
            'is_likely_header' => $this->isLikelyHeader(),
            'reason' => $this->reason,
        ];
    }

    public function __toString(): string
    {
        return sprintf(
            'HeaderScore(total=%.2f, visual=%.2f, textual=%.2f, semantic=%.2f)',
            $this->total,
            $this->visual,
            $this->textual,
            $this->semantic,
        );
    }
}
