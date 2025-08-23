<?php

declare(strict_types=1);

namespace App\Services\LLM\Adapters;

use App\Services\LLM\Contracts\LLMAdapterInterface;
use App\Services\LLM\DTOs\LLMRequest;
use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Exceptions\LLMConnectionException;
use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\Exceptions\LLMRateLimitException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Claude API adapter for LLM service.
 */
final class ClaudeAdapter implements LLMAdapterInterface
{
    private const string PROVIDER_NAME = 'claude';
    private const string BASE_URL = 'https://api.anthropic.com/v1/messages';
    private const string ANTHROPIC_VERSION = '2023-06-01';

    private Client $httpClient;

    /**
     * @param string $apiKey Claude API key
     * @param string $baseUrl Base URL for Claude API
     * @param int $timeoutSeconds Request timeout in seconds
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = self::BASE_URL,
        private readonly int    $timeoutSeconds = 60,
    ) {
        $this->httpClient = new Client([
            'timeout' => $this->timeoutSeconds,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
            ],
        ]);
    }

    public function execute(LLMRequest $request): LLMResponse
    {
        $this->validateRequest($request);

        $model = $request->model ?? $this->getDefaultModel();
        $startTime = microtime(true);

        $payload = $this->buildRequestPayload($request, $model);

        try {
            Log::info('Executing Claude API request', [
                'model' => $model,
                'content_length' => mb_strlen($request->content),
                'has_system_prompt' => !empty($request->systemPrompt),
                'estimated_input_tokens' => $request->getEstimatedInputTokens(),
            ]);

            $response = $this->httpClient->post($this->baseUrl, [
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            if (!is_array($responseData)) {
                throw new LLMException('Invalid response format from Claude API');
            }

            $llmResponse = LLMResponse::fromClaudeResponse($responseData, $model, $executionTime);

            Log::info('Claude API request completed', [
                'model' => $model,
                'execution_time_ms' => $executionTime,
                'input_tokens' => $llmResponse->inputTokens,
                'output_tokens' => $llmResponse->outputTokens,
                'cost_usd' => $llmResponse->costUsd,
                'tokens_per_second' => $llmResponse->getTokensPerSecond(),
            ]);

            return $llmResponse;
        } catch (ClientException $e) {
            $this->handleClientException($e, $model);
        } catch (ConnectException $e) {
            throw LLMConnectionException::connectionTimeout(self::PROVIDER_NAME, $this->timeoutSeconds);
        } catch (RequestException $e) {
            throw LLMConnectionException::networkError(self::PROVIDER_NAME, $e->getMessage());
        } catch (GuzzleException $e) {
            Log::error('Claude API request failed', [
                'error' => $e->getMessage(),
                'model' => $model,
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            throw new LLMException("Claude API request failed: {$e->getMessage()}");
        } catch (JsonException $e) {
            Log::error('Failed to parse Claude API response', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            throw new LLMException("Failed to parse Claude API response: {$e->getMessage()}");
        }
    }

    public function executeBatch(array $requests): array
    {
        $responses = [];

        foreach ($requests as $index => $request) {
            if (!($request instanceof LLMRequest)) {
                throw new LLMException("Invalid request at index {$index}. Expected LLMRequest instance.");
            }

            try {
                $responses[] = $this->execute($request);
            } catch (LLMException $e) {
                Log::error('Batch request failed', [
                    'batch_index' => $index,
                    'error' => $e->getMessage(),
                    'context' => $e->getContext(),
                ]);

                // Re-throw to let calling code handle batch failures
                throw $e;
            }
        }

        return $responses;
    }

    public function validateConnection(): bool
    {
        $cacheKey = 'llm_connection_valid_' . md5($this->apiKey);

        // Cache validation for 5 minutes to avoid excessive API calls
        $result = Cache::remember($cacheKey, 300, function (): bool {
            try {
                $testRequest = new LLMRequest(
                    content: 'test',
                    maxTokens: 10,
                );

                $response = $this->execute($testRequest);

                return $response->isSuccess();
            } catch (LLMException) {
                return false;
            }
        });
        
        return is_bool($result) ? $result : false;
    }

    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getSupportedModels(): array
    {
        $models = config('llm.models.claude', []);
        
        if (!is_array($models)) {
            return [
                'claude-3-5-sonnet-20241022',
                'claude-3-5-haiku-20241022',  
                'claude-3-opus-20240229',
            ];
        }

        return array_values(array_map(
            static function (array $modelConfig): string {
                return is_string($modelConfig['id'] ?? null) ? $modelConfig['id'] : '';
            },
            $models
        ));
    }

    public function calculateCost(int $inputTokens, int $outputTokens, string $model): float
    {
        $pricing = config("llm.pricing.{$model}");

        if (!$pricing) {
            // Fallback to default pricing
            $pricing = config('llm.pricing.claude-3-5-sonnet-20241022', [
                'input_per_million' => 3.00,
                'output_per_million' => 15.00,
            ]);
        }

        if (!is_array($pricing)) {
            return 0.0;
        }

        $inputCost = ($inputTokens / 1000000) * (float)($pricing['input_per_million'] ?? 0);
        $outputCost = ($outputTokens / 1000000) * (float)($pricing['output_per_million'] ?? 0);

        return round($inputCost + $outputCost, 6);
    }

    public function countTokens(string $text, string $model): int
    {
        // Simple approximation: ~4 characters per token for Claude
        // In production, you might want to use tiktoken or a similar library
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Build the request payload for Claude API.
     *
     * @param LLMRequest $request The request to build payload for
     * @param string $model The model to use
     *
     * @return array<string, mixed>
     */
    private function buildRequestPayload(LLMRequest $request, string $model): array
    {
        $payload = [
            'model' => $model,
            'max_tokens' => $request->maxTokens ?? $this->getDefaultMaxTokens(),
            'temperature' => $request->temperature ?? $this->getDefaultTemperature(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->content,
                ],
            ],
        ];

        if ($request->systemPrompt) {
            $payload['system'] = $request->systemPrompt;
        }

        return $payload;
    }

    /**
     * Handle client exceptions from Claude API.
     *
     * @param ClientException $e The client exception
     * @param string $model The model being used
     *
     * @throws LLMException
     */
    private function handleClientException(ClientException $e, string $model): never
    {
        $statusCode = $e->getCode();
        $response = $e->getResponse();
        $responseBody = '';
        if ($response !== null) {
            $responseBody = $response->getBody()->getContents();
        }

        Log::error('Claude API client error', [
            'status_code' => $statusCode,
            'model' => $model,
            'response_body' => $responseBody,
            'error' => $e->getMessage(),
        ]);

        switch ($statusCode) {
            case 401:
                throw LLMConnectionException::invalidApiKey(self::PROVIDER_NAME);
            case 429:
                $headers = [];
                if ($response !== null) {
                    $headers = $response->getHeaders();
                }

                throw LLMRateLimitException::fromApiResponse(self::PROVIDER_NAME, $headers);
            default:
                throw new LLMException(
                    "Claude API error (HTTP {$statusCode}): " . ($responseBody ?: $e->getMessage()),
                    $statusCode,
                );
        }
    }

    /**
     * Validate the LLM request.
     *
     * @param LLMRequest $request Request to validate
     *
     * @throws LLMException
     */
    private function validateRequest(LLMRequest $request): void
    {
        if (empty(trim($request->content))) {
            throw new LLMException('Request content cannot be empty');
        }

        if ($request->model && !in_array($request->model, $this->getSupportedModels(), true)) {
            throw new LLMException("Unsupported model: {$request->model}");
        }

        if ($request->temperature !== null && ($request->temperature < 0 || $request->temperature > 1)) {
            throw new LLMException('Temperature must be between 0 and 1');
        }

        if ($request->maxTokens !== null && $request->maxTokens < 1) {
            throw new LLMException('Max tokens must be positive');
        }
    }

    private function getDefaultModel(): string
    {
        $model = config('llm.providers.claude.default_model', 'claude-3-5-sonnet-20241022');
        return is_string($model) ? $model : 'claude-3-5-sonnet-20241022';
    }

    private function getDefaultMaxTokens(): int
    {
        $maxTokens = config('llm.providers.claude.max_tokens', 4096);
        return is_int($maxTokens) ? $maxTokens : 4096;
    }

    private function getDefaultTemperature(): float
    {
        $temperature = config('llm.providers.claude.temperature', 0.1);
        return is_float($temperature) || is_int($temperature) ? (float)$temperature : 0.1;
    }
}
