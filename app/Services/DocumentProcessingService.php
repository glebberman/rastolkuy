<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\ProcessDocumentRequest;
use App\Jobs\ProcessDocumentJob;
use App\Models\DocumentProcessing;
use App\Models\User;
use App\Services\LLM\CostCalculator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class DocumentProcessingService
{
    public function __construct(
        private readonly CostCalculator $costCalculator,
    ) {
    }

    /**
     * Загрузить документ и инициировать его обработку.
     */
    public function uploadAndProcess(ProcessDocumentRequest $request, User $user): DocumentProcessing
    {
        $file = $request->file('file');
        $uuid = Str::uuid()->toString();

        // Генерируем уникальное имя файла
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = $uuid . '.' . $extension;

        // Сохраняем файл
        $filePath = $file->storeAs('documents', $filename, 'local');

        if (!$filePath) {
            throw new RuntimeException('Failed to store uploaded file');
        }

        // Создаем запись в базе данных
        $documentProcessing = DocumentProcessing::create([
            'user_id' => $user->id,
            'uuid' => $uuid,
            'original_filename' => $originalName,
            'file_path' => $filePath,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'task_type' => $request->validated('task_type'),
            'options' => $request->validated('options', []),
            'anchor_at_start' => $request->validated('anchor_at_start', false),
            'status' => DocumentProcessing::STATUS_PENDING,
        ]);

        // Запускаем асинхронную обработку
        ProcessDocumentJob::dispatch($documentProcessing->id)
            ->onQueue('document-processing')
            ->delay(now()->addSeconds(2)); // Небольшая задержка для записи в БД

        Log::info('Document uploaded and queued for processing', [
            'uuid' => $uuid,
            'filename' => $originalName,
            'task_type' => $request->validated('task_type'),
            'file_size' => $file->getSize(),
        ]);

        return $documentProcessing;
    }

    /**
     * Получить документ по UUID.
     */
    public function getByUuid(string $uuid): ?DocumentProcessing
    {
        return DocumentProcessing::where('uuid', $uuid)->first();
    }

    /**
     * Получить список документов с фильтрацией и пагинацией.
     */
    public function getFilteredList(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = DocumentProcessing::query();

        // Фильтрация по статусу
        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        // Фильтрация по типу задачи
        if (isset($filters['task_type']) && $filters['task_type']) {
            $query->where('task_type', $filters['task_type']);
        }

        // Сортировка по дате создания (новые первые)
        $query->orderBy('created_at', 'desc');

        // Ограничиваем максимальное количество на страницу
        $perPage = min($perPage, 100);

        return $query->paginate($perPage);
    }

    /**
     * Отменить обработку документа.
     */
    public function cancelProcessing(DocumentProcessing $documentProcessing): void
    {
        if (!$documentProcessing->isPending()) {
            throw new InvalidArgumentException(
                'Нельзя отменить обработку в текущем статусе: ' . $documentProcessing->getStatusDescription(),
            );
        }

        // Отмечаем как отмененную (используем статус failed с соответствующим сообщением)
        $documentProcessing->markAsFailed('Cancelled by user', [
            'cancelled_at' => now()->toISOString(),
            'reason' => 'user_cancellation',
        ]);

        // Удаляем файл если он больше не нужен
        $this->deleteFileIfExists($documentProcessing->file_path);

        Log::info('Document processing cancelled', [
            'uuid' => $documentProcessing->uuid,
            'filename' => $documentProcessing->original_filename,
        ]);
    }

    /**
     * Удалить запись об обработке документа.
     */
    public function deleteProcessing(DocumentProcessing $documentProcessing): void
    {
        // Удаляем файл если он существует
        $this->deleteFileIfExists($documentProcessing->file_path);

        // Мягкое удаление записи
        $documentProcessing->delete();

        Log::info('Document processing deleted', [
            'uuid' => $documentProcessing->uuid,
            'filename' => $documentProcessing->original_filename,
        ]);
    }

    /**
     * Получить статистику по обработкам
     */
    public function getStatistics(): array
    {
        return [
            'total_processings' => DocumentProcessing::count(),
            'by_status' => [
                'pending' => DocumentProcessing::where('status', DocumentProcessing::STATUS_PENDING)->count(),
                'processing' => DocumentProcessing::where('status', DocumentProcessing::STATUS_PROCESSING)->count(),
                'completed' => DocumentProcessing::where('status', DocumentProcessing::STATUS_COMPLETED)->count(),
                'failed' => DocumentProcessing::where('status', DocumentProcessing::STATUS_FAILED)->count(),
            ],
            'by_task_type' => [
                'translation' => DocumentProcessing::where('task_type', DocumentProcessing::TASK_TRANSLATION)->count(),
                'contradiction' => DocumentProcessing::where('task_type', DocumentProcessing::TASK_CONTRADICTION)->count(),
                'ambiguity' => DocumentProcessing::where('task_type', DocumentProcessing::TASK_AMBIGUITY)->count(),
            ],
            'recent_stats' => [
                'last_24h' => DocumentProcessing::where('created_at', '>=', now()->subDay())->count(),
                'last_week' => DocumentProcessing::where('created_at', '>=', now()->subWeek())->count(),
                'last_month' => DocumentProcessing::where('created_at', '>=', now()->subMonth())->count(),
            ],
            'cost_stats' => [
                'total_cost_usd' => DocumentProcessing::completed()->sum('cost_usd'),
                'average_cost_usd' => DocumentProcessing::completed()->avg('cost_usd'),
                'total_processing_time_hours' => DocumentProcessing::completed()->sum('processing_time_seconds') / 3600,
            ],
        ];
    }

    /**
     * Получить предварительную оценку стоимости обработки.
     */
    public function estimateProcessingCost(int $fileSizeBytes, ?string $model = null): array
    {
        $estimatedInputTokens = $this->costCalculator->estimateTokensFromFileSize($fileSizeBytes);
        // Для оценки output токенов используем коэффициент 1.5x от input (эмпирический)
        $estimatedOutputTokens = (int) ($estimatedInputTokens * 1.5);
        $estimatedCost = $this->costCalculator->calculateCost($estimatedInputTokens, $estimatedOutputTokens, $model);

        return [
            'estimated_input_tokens' => $estimatedInputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
            'estimated_total_tokens' => $estimatedInputTokens + $estimatedOutputTokens,
            'estimated_cost_usd' => $estimatedCost,
            'model_used' => $model ?? $this->getDefaultModel(),
            'pricing_info' => $this->costCalculator->getPricingInfo($model ?? $this->getDefaultModel()),
        ];
    }

    /**
     * Получить модель по умолчанию.
     */
    private function getDefaultModel(): string
    {
        $defaultModel = config('llm.default_model', 'claude-3-5-sonnet-20241022');

        return is_string($defaultModel) ? $defaultModel : 'claude-3-5-sonnet-20241022';
    }

    /**
     * Удалить файл если он существует
     */
    private function deleteFileIfExists(?string $filePath): void
    {
        if ($filePath && Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }
    }
}
