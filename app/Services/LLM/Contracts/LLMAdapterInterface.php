<?php

declare(strict_types=1);

namespace App\Services\LLM\Contracts;

use App\Services\LLM\DTOs\LLMRequest;
use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Exceptions\LLMException;

/**
 * Interface for LLM provider adapters.
 *
 * This interface defines the contract that all LLM adapters must implement
 * to provide a consistent interface for different LLM providers.
 */
interface LLMAdapterInterface
{
    /**
     * Execute a single LLM request.
     *
     * @param LLMRequest $request The request to execute
     *
     * @throws LLMException When the request fails
     *
     * @return LLMResponse The response from the LLM
     */
    public function execute(LLMRequest $request): LLMResponse;

    /**
     * Execute multiple LLM requests in batch.
     *
     * @param array<LLMRequest> $requests Array of requests to execute
     *
     * @throws LLMException When any request fails
     *
     * @return array<LLMResponse> Array of responses
     */
    public function executeBatch(array $requests): array;

    /**
     * Check if the API key and connection are valid.
     *
     * @return bool True if valid, false otherwise
     */
    public function validateConnection(): bool;

    /**
     * Get the provider name (e.g., 'claude', 'openai').
     *
     * @return string Provider name
     */
    public function getProviderName(): string;

    /**
     * Get supported models for this provider.
     *
     * @return array<string> List of supported model names
     */
    public function getSupportedModels(): array;

    /**
     * Calculate the estimated cost for a request.
     *
     * @param int $inputTokens Number of input tokens
     * @param int $outputTokens Number of output tokens
     * @param string $model Model name
     *
     * @return float Cost in USD
     */
    public function calculateCost(int $inputTokens, int $outputTokens, string $model): float;

    /**
     * Count tokens in a given text for the specified model.
     *
     * @param string $text Text to count tokens for
     * @param string $model Model to use for counting
     *
     * @return int Number of tokens
     */
    public function countTokens(string $text, string $model): int;
}
