<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentProcessing;
use App\Services\DocumentProcessor;
use App\Services\FileStorageService;
use App\Services\LLM\CostCalculator;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600; // 10 минут максимум на обработку

    public int $maxExceptions = 3; // Максимум 3 попытки

    public function __construct(
        private int $documentProcessingId,
    ) {
        // Устанавливаем очередь в зависимости от важности
        $this->onQueue('document-processing');
    }

    public function handle(DocumentProcessor $processor, CostCalculator $costCalculator, FileStorageService $fileStorageService): void
    {
        $documentProcessing = DocumentProcessing::find($this->documentProcessingId);

        if (!$documentProcessing) {
            Log::error('Document processing record not found', [
                'document_processing_id' => $this->documentProcessingId,
            ]);

            return;
        }

        if (!$documentProcessing->isPending()) {
            Log::warning('Document processing is not in pending status', [
                'uuid' => $documentProcessing->uuid,
                'status' => $documentProcessing->status,
            ]);

            return;
        }

        // Проверяем существование файла
        if (!$fileStorageService->exists($documentProcessing->file_path)) {
            $documentProcessing->markAsFailed('File not found', [
                'file_path' => $documentProcessing->file_path,
            ]);

            return;
        }

        try {
            Log::info('Starting document processing job', [
                'uuid' => $documentProcessing->uuid,
                'task_type' => $documentProcessing->task_type,
                'file_size' => $documentProcessing->file_size,
            ]);

            // Отмечаем начало обработки
            $documentProcessing->markAsProcessing();

            // Получаем полный путь к файлу
            $fullFilePath = $fileStorageService->path($documentProcessing->file_path);

            // Обрабатываем документ
            $result = $processor->processFile(
                file: $fullFilePath,
                taskType: $documentProcessing->task_type,
                options: $documentProcessing->options ?? [],
                addAnchorAtStart: $documentProcessing->anchor_at_start,
            );

            // Извлекаем метаданные (примерная стоимость и статистика)
            $metadata = $this->extractProcessingMetadata($result, $documentProcessing, $costCalculator);

            // Отмечаем успешное завершение
            $documentProcessing->markAsCompleted(
                result: $result,
                metadata: $metadata,
                costUsd: $metadata['estimated_cost_usd'] ?? null,
            );

            Log::info('Document processing completed successfully', [
                'uuid' => $documentProcessing->uuid,
                'processing_time' => $documentProcessing->processing_time_seconds,
                'result_length' => mb_strlen($result),
            ]);
        } catch (Exception $e) {
            Log::error('Document processing failed', [
                'uuid' => $documentProcessing->uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $documentProcessing->markAsFailed(
                error: $e->getMessage(),
                errorDetails: [
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ],
            );

            // Пробрасываем исключение для retry механизма
            throw $e;
        }
    }

    /**
     * Обработка провала задачи.
     */
    public function failed(Exception $exception): void
    {
        $documentProcessing = DocumentProcessing::find($this->documentProcessingId);

        if ($documentProcessing && !$documentProcessing->isFailed()) {
            $documentProcessing->markAsFailed(
                error: 'Job failed after maximum retries: ' . $exception->getMessage(),
                errorDetails: [
                    'exception_class' => get_class($exception),
                    'attempts' => $this->attempts(),
                    'failed_at' => now()->toISOString(),
                ],
            );
        }

        Log::error('ProcessDocumentJob failed permanently', [
            'document_processing_id' => $this->documentProcessingId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Извлекает метаданные обработки для сохранения статистики.
     */
    private function extractProcessingMetadata(string $result, DocumentProcessing $documentProcessing, CostCalculator $costCalculator): array
    {
        $wordCount = str_word_count($result);
        $anchorCount = substr_count($result, '<!-- SECTION_ANCHOR_');
        $translationCount = substr_count($result, '**[Переведено]:**');
        $contradictionCount = substr_count($result, '**[Найдено противоречие]:**');
        $ambiguityCount = substr_count($result, '**[Найдена неоднозначность]:**');

        // Примерная оценка стоимости на основе размера документа
        $estimatedInputTokens = $costCalculator->estimateTokensFromFileSize($documentProcessing->file_size);
        $estimatedOutputTokens = $costCalculator->estimateTokens($result);
        $modelUsed = null;

        if (isset($documentProcessing->options['model']) && is_string($documentProcessing->options['model'])) {
            $modelUsed = $documentProcessing->options['model'];
        }
        $estimatedCostUsd = $costCalculator->calculateCost($estimatedInputTokens, $estimatedOutputTokens, $modelUsed);

        return [
            'result_stats' => [
                'character_count' => mb_strlen($result),
                'word_count' => $wordCount,
                'anchor_count' => $anchorCount,
                'translation_count' => $translationCount,
                'contradiction_count' => $contradictionCount,
                'ambiguity_count' => $ambiguityCount,
            ],
            'token_usage' => [
                'estimated_input_tokens' => $estimatedInputTokens,
                'estimated_output_tokens' => $estimatedOutputTokens,
                'estimated_total_tokens' => $estimatedInputTokens + $estimatedOutputTokens,
            ],
            'estimated_cost_usd' => $estimatedCostUsd,
            'processing_info' => [
                'job_attempts' => $this->attempts(),
                'queue_name' => $this->queue ?? 'default',
                'processed_at' => now()->toISOString(),
            ],
        ];
    }
}
