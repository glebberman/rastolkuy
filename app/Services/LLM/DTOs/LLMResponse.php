<?php

declare(strict_types=1);

namespace App\Services\LLM\DTOs;

/**
 * Data Transfer Object for LLM responses.
 */
final readonly class LLMResponse
{
    /**
     * @param string $content The generated content from the LLM
     * @param string $model The model that generated the response
     * @param int $inputTokens Number of input tokens used
     * @param int $outputTokens Number of output tokens generated
     * @param float $executionTimeMs Time taken to execute the request in milliseconds
     * @param float $costUsd Cost of the request in USD
     * @param string|null $stopReason Reason why generation stopped
     * @param array<string, mixed> $metadata Additional provider-specific metadata
     * @param array<string, mixed> $usage Detailed usage statistics
     */
    public function __construct(
        public string $content,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public float $executionTimeMs,
        public float $costUsd,
        public ?string $stopReason = null,
        public array $metadata = [],
        public array $usage = [],
    ) {
    }

    /**
     * Get the total number of tokens used (input + output).
     *
     * @return int Total tokens used
     */
    public function getTotalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }

    /**
     * Check if the response indicates a successful completion.
     *
     * @return bool True if successful, false otherwise
     */
    public function isSuccess(): bool
    {
        return !empty($this->content) && $this->stopReason !== 'error';
    }

    /**
     * Get the cost per token for this response.
     *
     * @return float Cost per token in USD
     */
    public function getCostPerToken(): float
    {
        $totalTokens = $this->getTotalTokens();

        return $totalTokens > 0 ? $this->costUsd / $totalTokens : 0.0;
    }

    /**
     * Get tokens per second throughput.
     *
     * @return float Tokens per second
     */
    public function getTokensPerSecond(): float
    {
        if ($this->executionTimeMs <= 0) {
            return 0.0;
        }

        return $this->outputTokens / ($this->executionTimeMs / 1000);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->getTotalTokens(),
            'execution_time_ms' => $this->executionTimeMs,
            'cost_usd' => $this->costUsd,
            'stop_reason' => $this->stopReason,
            'metadata' => $this->metadata,
            'usage' => $this->usage,
            'performance' => [
                'tokens_per_second' => $this->getTokensPerSecond(),
                'cost_per_token' => $this->getCostPerToken(),
            ],
        ];
    }

    /**
     * Create a response from Claude API response data.
     *
     * @param array<string, mixed> $responseData Raw response data from Claude API
     * @param string $model Model used
     * @param float $executionTimeMs Execution time in milliseconds
     */
    public static function fromClaudeResponse(array $responseData, string $model, float $executionTimeMs): self
    {
        $content = '';
        $usage = $responseData['usage'] ?? [];
        if (!is_array($usage)) {
            $usage = [];
        }
        $inputTokens = is_int($usage['input_tokens'] ?? 0) ? $usage['input_tokens'] : 0;
        $outputTokens = is_int($usage['output_tokens'] ?? 0) ? $usage['output_tokens'] : 0;

        // Extract content from Claude's response format
        if (!empty($responseData['content']) && is_array($responseData['content'])) {
            foreach ($responseData['content'] as $contentBlock) {
                if ($contentBlock['type'] === 'text') {
                    $content .= $contentBlock['text'];
                }
            }
        }

        // Calculate cost (this should ideally use the pricing config)
        $cost = self::calculateClaudeCost(
            is_int($inputTokens) ? $inputTokens : 0,
            is_int($outputTokens) ? $outputTokens : 0,
            $model
        );

        return new self(
            content: $content,
            model: $model,
            inputTokens: is_int($inputTokens) ? $inputTokens : 0,
            outputTokens: is_int($outputTokens) ? $outputTokens : 0,
            executionTimeMs: $executionTimeMs,
            costUsd: $cost,
            stopReason: is_string($responseData['stop_reason'] ?? null) ? $responseData['stop_reason'] : null,
            metadata: [
                'provider' => 'claude',
                'stop_sequence' => $responseData['stop_sequence'] ?? null,
            ],
            usage: $usage,
        );
    }

    /**
     * Calculate cost for Claude API usage.
     *
     * @param int $inputTokens Input tokens
     * @param int $outputTokens Output tokens
     * @param string $model Model name
     *
     * @return float Cost in USD
     */
    private static function calculateClaudeCost(int $inputTokens, int $outputTokens, string $model): float
    {
        $pricing = config('llm.pricing.' . $model, config('llm.pricing.claude-3-5-sonnet-20241022', [
            'input_per_million' => 3.00,
            'output_per_million' => 15.00,
        ]));

        if (!is_array($pricing)) {
            return 0.0;
        }

        $inputCost = ($inputTokens / 1000000) * (float)($pricing['input_per_million'] ?? 0);
        $outputCost = ($outputTokens / 1000000) * (float)($pricing['output_per_million'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }
}
