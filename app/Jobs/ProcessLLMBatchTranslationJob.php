<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\LLMService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job for processing batch LLM translations asynchronously.
 */
final class ProcessLLMBatchTranslationJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes for batch operations

    /**
     * @param string $batchId Unique batch identifier
     * @param array<string> $sections Sections to translate
     * @param string $documentType Type of document
     * @param array<string, mixed> $context Translation context
     * @param array<string, mixed> $options Translation options
     * @param string|null $callbackUrl Optional callback URL for results
     */
    public function __construct(
        public readonly string $batchId,
        public readonly array $sections,
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
        if ($this->batch()?->cancelled()) {
            Log::info('Batch job cancelled', ['batch_id' => $this->batchId]);

            return;
        }

        $startTime = microtime(true);

        Log::info('Starting LLM batch translation job', [
            'batch_id' => $this->batchId,
            'document_type' => $this->documentType,
            'sections_count' => count($this->sections),
            'total_content_length' => array_sum(array_map('mb_strlen', $this->sections)),
        ]);

        try {
            $responses = $llmService->translateBatch(
                sections: $this->sections,
                documentType: $this->documentType,
                context: $this->context,
                options: $this->options,
            );

            $executionTime = (microtime(true) - $startTime) * 1000;
            $totalCost = $responses->sum('costUsd');

            $result = [
                'batch_id' => $this->batchId,
                'status' => 'completed',
                'results' => $responses->map->toArray()->toArray(),
                'summary' => [
                    'total_sections' => count($this->sections),
                    'successful_translations' => $responses->count(),
                    'failed_translations' => count($this->sections) - $responses->count(),
                    'total_cost_usd' => $totalCost,
                    'execution_time_ms' => $executionTime,
                ],
                'completed_at' => now()->toISOString(),
            ];

            // Store result
            $this->storeResult($result);

            // Send callback if configured
            if ($this->callbackUrl) {
                $this->sendCallback($result);
            }

            Log::info('LLM batch translation job completed', [
                'batch_id' => $this->batchId,
                'sections_count' => count($this->sections),
                'successful_translations' => $responses->count(),
                'execution_time_ms' => $executionTime,
                'total_cost_usd' => $totalCost,
            ]);
        } catch (LLMException $e) {
            $this->handleBatchError($e, $startTime);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Throwable $exception): void
    {
        $result = [
            'batch_id' => $this->batchId,
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'error_type' => get_class($exception),
            'sections_count' => count($this->sections),
            'failed_at' => now()->toISOString(),
        ];

        $this->storeResult($result);

        if ($this->callbackUrl) {
            $this->sendCallback($result);
        }

        Log::error('LLM batch translation job failed', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'sections_count' => count($this->sections),
        ]);
    }

    /**
     * Dispatch a batch translation job.
     *
     * @param array<string> $sections Sections to translate
     * @param string $documentType Type of document
     * @param array<string, mixed> $context Translation context
     * @param array<string, mixed> $options Translation options
     * @param string|null $callbackUrl Optional callback URL
     *
     * @return string Batch ID
     */
    public static function dispatchBatch(
        array $sections,
        string $documentType = 'legal_document',
        array $context = [],
        array $options = [],
        ?string $callbackUrl = null,
    ): string {
        $batchId = 'batch_' . uniqid() . '_' . now()->timestamp;
        $batchSize = config('llm.queue.batch_size', 10);
        if (!is_int($batchSize) || $batchSize < 1) {
            $batchSize = 10;
        }

        // Split sections into chunks for processing
        $chunks = array_chunk($sections, $batchSize);
        $jobs = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkId = "{$batchId}_chunk_{$chunkIndex}";

            $jobs[] = new self(
                batchId: $chunkId,
                sections: $chunk,
                documentType: $documentType,
                context: $context,
                options: $options,
                callbackUrl: $callbackUrl,
            );
        }

        // Dispatch as Laravel batch
        $batch = Bus::batch($jobs)
            ->name("LLM Batch Translation: {$batchId}")
            ->allowFailures()
            ->finally(function () use ($batchId): void {
                Log::info('LLM batch processing completed', ['batch_id' => $batchId]);
            })
            ->dispatch();

        Log::info('LLM batch translation dispatched', [
            'batch_id' => $batchId,
            'laravel_batch_id' => $batch->id,
            'total_sections' => count($sections),
            'chunks_count' => count($chunks),
            'batch_size' => $batchSize,
        ]);

        return $batchId;
    }

    /**
     * Get batch result by batch ID.
     *
     * @param string $batchId Batch identifier
     *
     * @return array<string, mixed>|null
     */
    public static function getBatchResult(string $batchId): ?array
    {
        $cacheKey = "llm_batch_result:{$batchId}";
        
        $result = cache()->get($cacheKey);
        
        return is_array($result) ? $result : null;
    }

    /**
     * Get batch progress and status.
     *
     * @param string $laravelBatchId Laravel batch ID
     *
     * @return array<string, mixed>
     */
    public static function getBatchProgress(string $laravelBatchId): array
    {
        $batch = Bus::findBatch($laravelBatchId);

        if (!$batch) {
            return [
                'status' => 'not_found',
                'message' => 'Batch not found',
            ];
        }

        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'total_jobs' => $batch->totalJobs,
            'pending_jobs' => $batch->pendingJobs,
            'processed_jobs' => $batch->processedJobs(),
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
            'failed_jobs' => $batch->failedJobs,
            'created_at' => $batch->createdAt,
            'finished_at' => $batch->finishedAt,
        ];
    }

    /**
     * Handle batch translation error.
     *
     * @param LLMException $exception The exception that occurred
     * @param float $startTime Job start time
     */
    private function handleBatchError(LLMException $exception, float $startTime): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000;

        Log::error('LLM batch translation error', [
            'batch_id' => $this->batchId,
            'error' => $exception->getMessage(),
            'execution_time_ms' => $executionTime,
            'sections_count' => count($this->sections),
            'context' => $exception->getContext(),
        ]);

        throw $exception;
    }

    /**
     * Store batch result.
     *
     * @param array<string, mixed> $result Result data
     */
    private function storeResult(array $result): void
    {
        $cacheKey = "llm_batch_result:{$this->batchId}";

        // Store result for 48 hours (longer than single jobs due to larger data)
        cache()->put($cacheKey, $result, now()->addHours(48));

        Log::debug('Batch result stored', [
            'batch_id' => $this->batchId,
            'status' => $result['status'],
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * Send callback notification.
     *
     * @param array<string, mixed> $result Result data
     */
    private function sendCallback(array $result): void
    {
        try {
            if ($this->callbackUrl === null) {
                return;
            }
            
            $client = new \GuzzleHttp\Client(['timeout' => 30]);

            $response = $client->post($this->callbackUrl, [
                'json' => $result,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'LLMService/1.0',
                ],
            ]);

            Log::info('Batch callback sent successfully', [
                'batch_id' => $this->batchId,
                'callback_url' => $this->callbackUrl,
                'response_status' => $response->getStatusCode(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to send batch callback', [
                'batch_id' => $this->batchId,
                'callback_url' => $this->callbackUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
