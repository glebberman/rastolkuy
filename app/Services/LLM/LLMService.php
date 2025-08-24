<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\Services\LLM\Contracts\LLMAdapterInterface;
use App\Services\LLM\DTOs\LLMRequest;
use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\Support\RateLimiter;
use App\Services\LLM\Support\RetryHandler;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Main LLM service providing high-level interface for LLM operations.
 *
 * This service handles document translation, batch processing, rate limiting,
 * retry logic, and usage metrics collection.
 */
final readonly class LLMService
{
    public function __construct(
        private LLMAdapterInterface $adapter,
        private RateLimiter $rateLimiter,
        private RetryHandler $retryHandler,
        private UsageMetrics $usageMetrics,
    ) {
    }

    /**
     * Translate a single document section into simple language.
     *
     * @param string $sectionContent The document section to translate
     * @param string $documentType Type of document (contract, agreement, etc.)
     * @param array<string, mixed> $context Additional context for translation
     * @param array<string, mixed> $options Translation options
     *
     * @throws LLMException
     *
     * @return LLMResponse The translation response
     */
    public function translateSection(
        string $sectionContent,
        string $documentType = 'legal_document',
        array $context = [],
        array $options = [],
    ): LLMResponse {
        $request = LLMRequest::forSectionTranslation($sectionContent, $documentType, $context, $options);

        Log::info('Starting section translation', [
            'document_type' => $documentType,
            'content_length' => mb_strlen($sectionContent),
            'estimated_tokens' => $request->getEstimatedInputTokens(),
        ]);

        $startTime = microtime(true);

        try {
            // Check rate limits before making request
            $this->rateLimiter->checkAndReserve($request->getEstimatedInputTokens());

            // Execute request with retry logic
            $response = $this->retryHandler->execute(
                operation: fn () => $this->adapter->execute($request),
                operationName: 'section translation',
            );

            // Record actual usage
            $this->rateLimiter->recordUsage($response->getTotalTokens());
            $this->usageMetrics->recordTranslation($response, $documentType, 'section');

            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::info('Section translation completed', [
                'document_type' => $documentType,
                'content_length' => mb_strlen($sectionContent),
                'response_length' => mb_strlen($response->content),
                'execution_time_ms' => $executionTime,
                'cost_usd' => $response->costUsd,
                'model' => $response->model,
            ]);

            return $response;
        } catch (LLMException $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('Section translation failed', [
                'document_type' => $documentType,
                'content_length' => mb_strlen($sectionContent),
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            $this->usageMetrics->recordFailure($e, $documentType, 'section');

            throw $e;
        }
    }

    /**
     * Translate multiple document sections in batch.
     *
     * @param array<string> $sections Array of document sections to translate
     * @param string $documentType Type of document
     * @param array<string, mixed> $context Additional context for translations
     * @param array<string, mixed> $options Translation options
     *
     * @throws LLMException
     *
     * @return Collection<int, LLMResponse> Collection of translation responses
     */
    public function translateBatch(
        array $sections,
        string $documentType = 'legal_document',
        array $context = [],
        array $options = [],
    ): Collection {
        if (empty($sections)) {
            return collect([]);
        }

        $requests = LLMRequest::forBatchTranslation($sections, $documentType, $context, $options);

        Log::info('Starting batch translation', [
            'document_type' => $documentType,
            'sections_count' => count($sections),
            'total_content_length' => array_sum(array_map('mb_strlen', $sections)),
            'estimated_total_tokens' => array_sum(array_map(fn ($req) => $req->getEstimatedInputTokens(), $requests)),
        ]);

        $startTime = microtime(true);
        $responses = collect([]);
        $failures = [];

        try {
            foreach ($requests as $index => $request) {
                try {
                    // Check rate limits for each request
                    $this->rateLimiter->checkAndReserve($request->getEstimatedInputTokens());

                    // Execute with retry logic
                    $response = $this->retryHandler->execute(
                        operation: fn () => $this->adapter->execute($request),
                        operationName: "batch translation item {$index}",
                    );

                    $responses->push($response);

                    // Record usage
                    $this->rateLimiter->recordUsage($response->getTotalTokens());
                    $this->usageMetrics->recordTranslation($response, $documentType, 'batch');

                    Log::debug('Batch item completed', [
                        'batch_index' => $index,
                        'response_length' => mb_strlen($response->content),
                        'cost_usd' => $response->costUsd,
                    ]);
                } catch (LLMException $e) {
                    $failures[] = [
                        'index' => $index,
                        'content' => $sections[$index],
                        'error' => $e->getMessage(),
                        'context' => $e->getContext(),
                    ];

                    $this->usageMetrics->recordFailure($e, $documentType, 'batch');

                    Log::error('Batch item failed', [
                        'batch_index' => $index,
                        'error' => $e->getMessage(),
                        'content_length' => mb_strlen($sections[$index]),
                    ]);

                    // For batch operations, we might want to continue with other items
                    // or fail fast depending on requirements
                    if ($options['fail_fast'] ?? false) {
                        throw $e;
                    }
                }
            }

            $executionTime = (microtime(true) - $startTime) * 1000;
            $totalCost = $responses->sum('costUsd');

            Log::info('Batch translation completed', [
                'document_type' => $documentType,
                'sections_count' => count($sections),
                'successful_translations' => $responses->count(),
                'failed_translations' => count($failures),
                'execution_time_ms' => $executionTime,
                'total_cost_usd' => $totalCost,
            ]);

            // Log failures summary if any
            if (!empty($failures)) {
                Log::warning('Batch translation had failures', [
                    'failed_count' => count($failures),
                    'failure_details' => $failures,
                ]);
            }

            return $responses;
        } catch (LLMException $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('Batch translation failed completely', [
                'document_type' => $documentType,
                'sections_count' => count($sections),
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get usage statistics for the LLM service.
     *
     * @param int $days Number of days to look back (default: 7)
     *
     * @return array<string, mixed>
     */
    public function getUsageStats(int $days = 7): array
    {
        return [
            'provider' => $this->adapter->getProviderName(),
            'rate_limiting' => $this->rateLimiter->getUsageStats(),
            'metrics' => $this->usageMetrics->getStats($days),
        ];
    }

    /**
     * Validate the LLM connection and configuration.
     *
     * @return bool True if connection is valid
     */
    public function validateConnection(): bool
    {
        try {
            return $this->adapter->validateConnection();
        } catch (LLMException $e) {
            Log::error('LLM connection validation failed', [
                'provider' => $this->adapter->getProviderName(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get information about the current LLM provider.
     *
     * @return array<string, mixed>
     */
    public function getProviderInfo(): array
    {
        return [
            'name' => $this->adapter->getProviderName(),
            'supported_models' => $this->adapter->getSupportedModels(),
            'connection_valid' => $this->validateConnection(),
        ];
    }

    /**
     * Estimate the cost for translating given content.
     *
     * @param string $content Content to estimate cost for
     * @param string $model Model to use for estimation
     *
     * @return array<string, mixed> Cost estimation details
     */
    public function estimateCost(string $content, ?string $model = null): array
    {
        $request = new LLMRequest($content, model: $model);
        $estimatedInputTokens = $request->getEstimatedInputTokens();

        // Estimate output tokens as roughly 50% of input for translations
        $estimatedOutputTokens = (int) ($estimatedInputTokens * 0.5);

        $totalTokens = $estimatedInputTokens + $estimatedOutputTokens;
        $actualModel = $model ?? $this->getDefaultModel();
        $cost = $this->adapter->calculateCost($estimatedInputTokens, $estimatedOutputTokens, $actualModel);

        return [
            'content_length' => mb_strlen($content),
            'estimated_input_tokens' => $estimatedInputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
            'estimated_total_tokens' => $totalTokens,
            'estimated_cost_usd' => $cost,
            'model' => $actualModel,
            'provider' => $this->adapter->getProviderName(),
        ];
    }

    /**
     * Execute a general LLM request with custom prompt and options.
     *
     * @param string $prompt The prompt to send
     * @param array<string, mixed> $options Options including model, temperature, etc.
     *
     * @throws LLMException
     *
     * @return LLMResponse The LLM response
     */
    public function generate(string $prompt, array $options = []): LLMResponse
    {
        $systemPrompt = is_string($options['system_prompt'] ?? null) ? $options['system_prompt'] : null;
        $model = is_string($options['model'] ?? null) ? $options['model'] : null;
        $maxTokens = is_int($options['max_tokens'] ?? null) ? $options['max_tokens'] : null;
        $temperature = is_numeric($options['temperature'] ?? null) ? (float) $options['temperature'] : null;

        $request = new LLMRequest(
            content: $prompt,
            systemPrompt: $systemPrompt,
            model: $model,
            maxTokens: $maxTokens,
            temperature: $temperature,
            options: $options,
            metadata: [
                'request_type' => 'general',
                'created_at' => now()->toISOString(),
            ],
        );

        Log::info('Starting general LLM request', [
            'content_length' => mb_strlen($prompt),
            'model' => $request->model ?? 'default',
            'estimated_tokens' => $request->getEstimatedInputTokens(),
        ]);

        $startTime = microtime(true);

        try {
            // Check rate limits before making request
            $this->rateLimiter->checkAndReserve($request->getEstimatedInputTokens());

            // Execute request with retry logic
            $response = $this->retryHandler->execute(
                operation: fn () => $this->adapter->execute($request),
                operationName: 'general LLM request',
            );

            // Record actual usage
            $this->rateLimiter->recordUsage($response->getTotalTokens());
            $this->usageMetrics->recordTranslation($response, 'general', 'prompt');

            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::info('General LLM request completed', [
                'content_length' => mb_strlen($prompt),
                'response_length' => mb_strlen($response->content),
                'execution_time_ms' => $executionTime,
                'cost_usd' => $response->costUsd,
                'model' => $response->model,
            ]);

            return $response;
        } catch (LLMException $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('General LLM request failed', [
                'content_length' => mb_strlen($prompt),
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            $this->usageMetrics->recordFailure($e, 'general', 'prompt');

            throw $e;
        }
    }

    /**
     * Get the default model for the current adapter.
     */
    private function getDefaultModel(): string
    {
        $models = $this->adapter->getSupportedModels();

        return $models[0] ?? 'claude-3-5-sonnet-20241022';
    }
}
