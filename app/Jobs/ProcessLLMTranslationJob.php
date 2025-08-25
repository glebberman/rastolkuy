<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\LLMService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job for processing LLM translation requests asynchronously.
 */
final class ProcessLLMTranslationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public int $timeout = 300; // 5 minutes

    public int $backoff = 30; // 30 seconds between retries

    /**
     * @param string $jobId Unique job identifier
     * @param string $sectionContent Content to translate
     * @param string $documentType Type of document
     * @param array<string, mixed> $context Translation context
     * @param array<string, mixed> $options Translation options
     * @param string|null $callbackUrl Optional callback URL for results
     */
    public function __construct(
        public readonly string $jobId,
        public readonly string $sectionContent,
        public readonly string $documentType,
        public readonly array $context,
        public readonly array $options,
        public readonly ?string $callbackUrl = null,
    ) {
        $queueName = config('llm.queue.queue_name', 'llm-processing');

        if (is_string($queueName)) {
            $this->onQueue($queueName);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(LLMService $llmService): void
    {
        $startTime = microtime(true);

        Log::info('Starting LLM translation job', [
            'job_id' => $this->jobId,
            'document_type' => $this->documentType,
            'content_length' => mb_strlen($this->sectionContent),
            'attempt' => $this->attempts(),
        ]);

        try {
            $response = $llmService->translateSection(
                sectionContent: $this->sectionContent,
                documentType: $this->documentType,
                context: $this->context,
                options: $this->options,
            );

            $executionTime = (microtime(true) - $startTime) * 1000;

            $result = [
                'job_id' => $this->jobId,
                'status' => 'completed',
                'result' => $response->toArray(),
                'execution_time_ms' => $executionTime,
                'completed_at' => now()->toISOString(),
            ];

            // Store result for retrieval
            $this->storeResult($result);

            // Send callback if configured
            if ($this->callbackUrl) {
                $this->sendCallback($result);
            }

            Log::info('LLM translation job completed', [
                'job_id' => $this->jobId,
                'execution_time_ms' => $executionTime,
                'cost_usd' => $response->costUsd,
                'model' => $response->model,
            ]);
        } catch (LLMException $e) {
            $this->handleTranslationError($e, $startTime);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        $result = [
            'job_id' => $this->jobId,
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
            'attempts' => $this->attempts(),
            'failed_at' => now()->toISOString(),
        ];

        // Store failure result
        $this->storeResult($result);

        // Send failure callback if configured
        if ($this->callbackUrl) {
            $this->sendCallback($result);
        }

        Log::error('LLM translation job failed permanently', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'document_type' => $this->documentType,
        ]);
    }

    /**
     * Calculate the backoff delay for retries.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 30s, 60s, 120s
        return [30, 60, 120];
    }

    /**
     * Create a job instance for section translation.
     *
     * @param string $sectionContent Content to translate
     * @param string $documentType Type of document
     * @param array<string, mixed> $context Translation context
     * @param array<string, mixed> $options Translation options
     * @param string|null $callbackUrl Optional callback URL
     */
    public static function forSectionTranslation(
        string $sectionContent,
        string $documentType = 'legal_document',
        array $context = [],
        array $options = [],
        ?string $callbackUrl = null,
    ): self {
        $jobId = 'llm_' . uniqid() . '_' . now()->timestamp;

        return new self(
            jobId: $jobId,
            sectionContent: $sectionContent,
            documentType: $documentType,
            context: $context,
            options: $options,
            callbackUrl: $callbackUrl,
        );
    }

    /**
     * Get job result by job ID.
     *
     * @param string $jobId Job identifier
     *
     * @return array<string, mixed>|null
     */
    public static function getResult(string $jobId): ?array
    {
        $cacheKey = "llm_job_result:{$jobId}";

        $result = cache()->get($cacheKey);

        return is_array($result) ? $result : null;
    }

    /**
     * Handle translation error and determine if retry is needed.
     *
     * @param LLMException $exception The exception that occurred
     * @param float $startTime Job start time
     */
    private function handleTranslationError(LLMException $exception, float $startTime): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::error('LLM translation job error', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
            'attempt' => $this->attempts(),
            'execution_time_ms' => $executionTime,
            'context' => $exception->getContext(),
        ]);

        // For certain errors, we might want to fail immediately without retry
        $nonRetryableErrors = [
            'LLMConnectionException' => 'Invalid API key or connection issues',
        ];

        $exceptionClass = get_class($exception);

        if (array_key_exists($exceptionClass, $nonRetryableErrors)) {
            Log::error('LLM job failing immediately due to non-retryable error', [
                'job_id' => $this->jobId,
                'error_type' => $exceptionClass,
                'reason' => $nonRetryableErrors[$exceptionClass],
            ]);

            $this->fail($exception);

            return;
        }

        // For retryable errors, let the queue system handle retry logic
        throw $exception;
    }

    /**
     * Store job result for later retrieval.
     *
     * @param array<string, mixed> $result Job result data
     */
    private function storeResult(array $result): void
    {
        $cacheKey = "llm_job_result:{$this->jobId}";

        // Store result for 24 hours
        cache()->put($cacheKey, $result, now()->addHours(24));

        Log::debug('Job result stored', [
            'job_id' => $this->jobId,
            'status' => $result['status'],
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * Send callback to configured URL.
     *
     * @param array<string, mixed> $result Result data to send
     */
    private function sendCallback(array $result): void
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);

            if ($this->callbackUrl === null) {
                return;
            }

            $response = $client->post($this->callbackUrl, [
                'json' => $result,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'LLMService/1.0',
                ],
            ]);

            Log::info('Callback sent successfully', [
                'job_id' => $this->jobId,
                'callback_url' => $this->callbackUrl,
                'response_status' => $response->getStatusCode(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to send callback', [
                'job_id' => $this->jobId,
                'callback_url' => $this->callbackUrl,
                'error' => $e->getMessage(),
            ]);

            // Don't fail the job just because callback failed
        }
    }
}
