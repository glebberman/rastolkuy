<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use App\PromptSystem;
use App\Services\Prompt\Exceptions\PromptException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JsonException;

final readonly class ClaudeApiClient
{
    private const BASE_URL = 'https://api.anthropic.com/v1/messages';
    private const DEFAULT_MODEL = 'claude-3-5-sonnet-20241022';
    private const DEFAULT_MAX_TOKENS = 4096;

    private Client $httpClient;

    private string $apiKey;

    public function __construct()
    {
        $apiKey = Config::get('services.claude.api_key');
        $this->apiKey = is_string($apiKey) ? $apiKey : '';

        if (empty($this->apiKey)) {
            throw new PromptException('Claude API key not configured');
        }

        $this->httpClient = new Client([
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ],
        ]);
    }

    public function execute(PromptSystem $system, string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? self::DEFAULT_MODEL;
        $maxTokens = $options['max_tokens'] ?? self::DEFAULT_MAX_TOKENS;
        $temperature = $options['temperature'] ?? 0.1;

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        if (!empty($system->system_prompt)) {
            $payload['system'] = $system->system_prompt;
        }

        $startTime = microtime(true);

        try {
            Log::info('Sending request to Claude API', [
                'model' => $model,
                'system_name' => $system->name,
                'prompt_length' => mb_strlen($prompt),
            ]);

            $response = $this->httpClient->post(self::BASE_URL, [
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $executionTime = (microtime(true) - $startTime) * 1000;

            $usage = is_array($responseData) && isset($responseData['usage']) && is_array($responseData['usage']) 
                ? $responseData['usage'] 
                : [];

            Log::info('Claude API response received', [
                'model' => $model,
                'execution_time_ms' => $executionTime,
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
            ]);

            return $this->formatResponse(is_array($responseData) ? $responseData : [], $model, $executionTime);
        } catch (GuzzleException $e) {
            Log::error('Claude API request failed', [
                'error' => $e->getMessage(),
                'system_name' => $system->name,
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            throw new PromptException("Claude API request failed: {$e->getMessage()}");
        } catch (JsonException $e) {
            Log::error('Failed to parse Claude API response', [
                'error' => $e->getMessage(),
                'system_name' => $system->name,
            ]);

            throw new PromptException("Failed to parse Claude API response: {$e->getMessage()}");
        }
    }

    public function validateApiKey(): bool
    {
        try {
            $response = $this->httpClient->post(self::BASE_URL, [
                'json' => [
                    'model' => self::DEFAULT_MODEL,
                    'max_tokens' => 10,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'test',
                        ],
                    ],
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (GuzzleException) {
            return false;
        }
    }

    public function estimateCost(int $inputTokens, int $outputTokens, string $model = self::DEFAULT_MODEL): float
    {
        $pricing = $this->getModelPricing($model);

        $inputCost = ($inputTokens / 1000000) * $pricing['input_per_million'];
        $outputCost = ($outputTokens / 1000000) * $pricing['output_per_million'];

        return round($inputCost + $outputCost, 6);
    }

    private function formatResponse(array $responseData, string $model, float $executionTime): array
    {
        $content = '';
        $inputTokens = $responseData['usage']['input_tokens'] ?? 0;
        $outputTokens = $responseData['usage']['output_tokens'] ?? 0;

        if (!empty($responseData['content']) && is_array($responseData['content'])) {
            foreach ($responseData['content'] as $contentBlock) {
                if ($contentBlock['type'] === 'text') {
                    $content .= $contentBlock['text'];
                }
            }
        }

        return [
            'content' => $content,
            'model' => $model,
            'tokens' => $inputTokens + $outputTokens,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $this->estimateCost($inputTokens, $outputTokens, $model),
            'execution_time_ms' => $executionTime,
            'metadata' => [
                'usage' => $responseData['usage'] ?? [],
                'stop_reason' => $responseData['stop_reason'] ?? null,
                'stop_sequence' => $responseData['stop_sequence'] ?? null,
            ],
        ];
    }

    private function getModelPricing(string $model): array
    {
        $pricing = [
            'claude-3-5-sonnet-20241022' => [
                'input_per_million' => 3.00,
                'output_per_million' => 15.00,
            ],
            'claude-3-5-haiku-20241022' => [
                'input_per_million' => 0.25,
                'output_per_million' => 1.25,
            ],
            'claude-3-opus-20240229' => [
                'input_per_million' => 15.00,
                'output_per_million' => 75.00,
            ],
        ];

        return $pricing[$model] ?? $pricing['claude-3-5-sonnet-20241022'];
    }
}
