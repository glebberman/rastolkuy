<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\EstimateDocumentDto;
use App\DTOs\UploadDocumentDto;
use App\Http\Requests\ProcessDocumentRequest;
use App\Jobs\AnalyzeDocumentStructureJob;
use App\Jobs\ProcessDocumentJob;
use App\Models\DocumentProcessing;
use App\Models\User;
use App\Services\LLM\CostCalculator;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Structure\DTOs\DocumentSection;
use App\Services\Structure\StructureAnalyzer;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

readonly class DocumentProcessingService
{
    // Константы для лимитов и конфигурации
    private const string STRUCTURE_ANALYSIS_KEY = 'structure_analysis';
    private const string SECTIONS_KEY = 'sections';
    private const int MAX_SECTIONS_COUNT = 1000;
    private const int MAX_CONTENT_LENGTH = 10 * 1024 * 1024; // 10MB для разметки

    public function __construct(
        private CostCalculator $costCalculator,
        private CreditService $creditService,
        private ExtractorManager $extractorManager,
        private StructureAnalyzer $structureAnalyzer,
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
            'status' => DocumentProcessing::STATUS_UPLOADED,
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
     * Загрузить только файл без запуска обработки.
     */
    public function uploadDocument(UploadDocumentDto $dto, User $user): DocumentProcessing
    {
        $uuid = Str::uuid()->toString();

        // Генерируем уникальное имя файла
        $originalName = $dto->file->getClientOriginalName();
        $extension = $dto->file->getClientOriginalExtension();
        $filename = $uuid . '.' . $extension;

        // Сохраняем файл
        $filePath = $dto->file->storeAs('documents', $filename, 'local');

        if (!$filePath) {
            throw new RuntimeException('Failed to store uploaded file');
        }

        // Создаем запись в базе данных со статусом uploaded
        $documentProcessing = DocumentProcessing::create([
            'user_id' => $user->id,
            'uuid' => $uuid,
            'original_filename' => $originalName,
            'file_path' => $filePath,
            'file_type' => $dto->file->getClientMimeType(),
            'file_size' => $dto->file->getSize(),
            'task_type' => $dto->taskType,
            'options' => $dto->options,
            'anchor_at_start' => $dto->anchorAtStart,
            'status' => DocumentProcessing::STATUS_UPLOADED,
        ]);

        Log::info('Document uploaded', [
            'uuid' => $uuid,
            'filename' => $originalName,
            'task_type' => $dto->taskType,
            'file_size' => $dto->file->getSize(),
            'status' => DocumentProcessing::STATUS_UPLOADED,
        ]);

        return $documentProcessing;
    }

    /**
     * Получить предварительную оценку стоимости обработки документа (асинхронно).
     */
    public function estimateDocumentCost(DocumentProcessing $documentProcessing, EstimateDocumentDto $dto): DocumentProcessing
    {
        if (!$documentProcessing->isUploaded()) {
            throw new InvalidArgumentException('Document must be in uploaded status for estimation');
        }

        Log::info('Starting async document structure analysis and cost estimation', [
            'uuid' => $documentProcessing->uuid,
            'file_path' => $documentProcessing->file_path,
            'file_size' => $documentProcessing->file_size,
            'model' => $dto->model,
        ]);

        // Устанавливаем статус "analyzing"
        $documentProcessing->markAsAnalyzing();

        // Запускаем асинхронный анализ структуры
        AnalyzeDocumentStructureJob::dispatch($documentProcessing->id, $dto->model)
            ->delay(now()->addSeconds(1));

        Log::info('Document structure analysis job dispatched', [
            'uuid' => $documentProcessing->uuid,
            'model' => $dto->model ?? $this->getDefaultModel(),
        ]);

        $refreshed = $documentProcessing->fresh();

        if ($refreshed === null) {
            throw new RuntimeException('Document processing was deleted during estimation start');
        }

        return $refreshed;
    }

    /**
     * Получить документ с разметкой якорями (без обработки LLM).
     *
     * @param DocumentProcessing $documentProcessing Документ в статусе estimated/completed
     *
     * @throws InvalidArgumentException При неверном статусе документа
     * @throws RuntimeException При ошибке генерации разметки
     *
     * @return array{
     *   original_content: string,
     *   content_with_anchors: string,
     *   sections_count: int,
     *   anchors: array<array{id: string, title: string, anchor: string, level: int, confidence: float}>,
     *   structure_analysis: ?array
     * }
     */
    public function getDocumentWithMarkup(DocumentProcessing $documentProcessing): array
    {
        $this->validateDocumentStatus($documentProcessing);
        $this->validateDocumentSize($documentProcessing);

        Log::info('Generating document markup', [
            'uuid' => $documentProcessing->uuid,
            'status' => $documentProcessing->status,
        ]);

        try {
            $sections = $this->getOrAnalyzeSections($documentProcessing);
            $originalContent = $this->extractContent($documentProcessing);
            $contentWithAnchors = $this->addAnchorsToDocument($originalContent, $sections, $documentProcessing->anchor_at_start);

            return $this->buildMarkupResponse($originalContent, $contentWithAnchors, $sections);
        } catch (Exception $e) {
            Log::error('Failed to generate document markup', [
                'uuid' => $documentProcessing->uuid,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Failed to generate document markup: ' . $e->getMessage());
        }
    }

    /**
     * Запустить обработку оцененного документа.
     */
    public function processEstimatedDocument(DocumentProcessing $documentProcessing): DocumentProcessing
    {
        if (!$documentProcessing->isEstimated()) {
            throw new InvalidArgumentException('Document must be in estimated status for processing');
        }

        $user = $documentProcessing->user;

        if ($user === null) {
            throw new InvalidArgumentException('Document processing must have an associated user');
        }

        $metadata = $documentProcessing->processing_metadata;

        if (!is_array($metadata) || !isset($metadata['estimation']) || !is_array($metadata['estimation']) || !isset($metadata['estimation']['credits_needed'])) {
            throw new InvalidArgumentException('Document estimation data is missing or invalid');
        }

        $estimation = $metadata['estimation'];
        $creditsNeededValue = $estimation['credits_needed'];

        if (!is_numeric($creditsNeededValue)) {
            throw new InvalidArgumentException('Credits needed value must be numeric');
        }

        $creditsNeeded = (float) $creditsNeededValue;

        // Проверяем достаточность баланса
        if (!$this->creditService->hasSufficientBalance($user, $creditsNeeded)) {
            throw new InvalidArgumentException('Insufficient balance to process document');
        }

        // Используем транзакцию для атомарного списания кредитов и обновления статуса
        return DB::transaction(function () use ($documentProcessing, $user, $creditsNeeded) {
            // Списываем кредиты
            $this->creditService->debitCredits(
                $user,
                $creditsNeeded,
                "Document processing: {$documentProcessing->original_filename}",
                'document_processing',
                $documentProcessing->uuid,
            );

            // Обновляем статус на pending и запускаем обработку
            $documentProcessing->update(['status' => DocumentProcessing::STATUS_PENDING]);

            // Запускаем асинхронную обработку
            ProcessDocumentJob::dispatch($documentProcessing->id)
                ->onQueue('document-processing')
                ->delay(now()->addSeconds(2));

            Log::info('Document processing started', [
                'uuid' => $documentProcessing->uuid,
                'credits_debited' => $creditsNeeded,
                'filename' => $documentProcessing->original_filename,
            ]);

            $refreshed = $documentProcessing->fresh();

            if ($refreshed === null) {
                throw new RuntimeException('Document processing was deleted during processing start');
            }

            return $refreshed;
        });
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
                'uploaded' => DocumentProcessing::where('status', DocumentProcessing::STATUS_UPLOADED)->count(),
                'analyzing' => DocumentProcessing::where('status', DocumentProcessing::STATUS_ANALYZING)->count(),
                'estimated' => DocumentProcessing::where('status', DocumentProcessing::STATUS_ESTIMATED)->count(),
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
     * Получить более точную оценку стоимости с учетом структуры документа.
     */
    public function estimateProcessingCostWithStructure(int $fileSizeBytes, int $sectionsCount, ?string $model = null): array
    {
        $estimatedInputTokens = $this->costCalculator->estimateTokensFromFileSize($fileSizeBytes);

        // Коррекция на основе количества секций из конфигурации
        $sectionCostMultiplier = config('document.cost_estimation.section_cost_multiplier', 0.1);
        assert(is_numeric($sectionCostMultiplier));
        $sectionCostMultiplier = (float) $sectionCostMultiplier;

        $maxSectionMultiplier = config('document.cost_estimation.max_section_multiplier', 3.0);
        assert(is_numeric($maxSectionMultiplier));
        $maxSectionMultiplier = (float) $maxSectionMultiplier;

        // Больше секций = больше контекста для LLM = больше output токенов
        $sectionMultiplier = max(1.0, 1.0 + ($sectionsCount * $sectionCostMultiplier));
        $sectionMultiplier = min($sectionMultiplier, $maxSectionMultiplier); // Ограничиваем максимум

        $estimatedOutputTokens = (int) ($estimatedInputTokens * 1.5 * $sectionMultiplier);

        $estimatedCost = $this->costCalculator->calculateCost($estimatedInputTokens, $estimatedOutputTokens, $model);

        return [
            'estimated_input_tokens' => $estimatedInputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
            'estimated_total_tokens' => $estimatedInputTokens + $estimatedOutputTokens,
            'estimated_cost_usd' => $estimatedCost,
            'model_used' => $model ?? $this->getDefaultModel(),
            'pricing_info' => $this->costCalculator->getPricingInfo($model ?? $this->getDefaultModel()),
            'sections_count' => $sectionsCount,
            'section_multiplier' => $sectionMultiplier,
        ];
    }

    /**
     * Добавляет якоря к документу на основе секций.
     *
     * Якоря размещаются в конце каждой секции, определяя границы логически:
     * - В конце секции перед началом следующей секции
     * - В конце документа для последней секции
     * - В начале секции, если addAnchorAtStart = true
     */
    private function addAnchorsToDocument(string $content, array $sections, bool $addAnchorAtStart = false): string
    {
        if (empty($sections)) {
            return $content;
        }

        // Сортируем секции по названиям (по порядку в документе)
        usort($sections, function ($a, $b) {
            if (!($a instanceof DocumentSection)
                || !($b instanceof DocumentSection)) {
                return 0;
            }

            // Извлекаем номера из заголовков (например, "1. SUBJECT" -> 1)
            $aNumber = $this->extractSectionNumber($a->title);
            $bNumber = $this->extractSectionNumber($b->title);

            return $aNumber <=> $bNumber;
        });

        $lines = explode("\n", $content);
        $result = [];

        if ($addAnchorAtStart) {
            // Якоря в начале секций
            foreach ($lines as $line) {
                $result[] = $line;

                // Проверяем, является ли эта строка заголовком секции
                foreach ($sections as $section) {
                    if (!($section instanceof DocumentSection)) {
                        continue;
                    }

                    if (trim($line) === trim($section->title)) {
                        $result[] = $section->anchor;
                        break;
                    }
                }
            }
        } else {
            // Якоря в конце секций (по умолчанию)
            $sectionTitles = array_map(
                static fn ($section) => ($section instanceof DocumentSection) ? trim($section->title) : '',
                $sections,
            );

            $currentSectionIndex = -1;

            foreach ($lines as $lineIndex => $line) {
                // Проверяем, начинается ли новая секция ПЕРЕД добавлением строки
                $trimmedLine = trim($line);
                $nextSectionIndex = array_search($trimmedLine, $sectionTitles, true);

                if ($nextSectionIndex !== false && $nextSectionIndex > $currentSectionIndex) {
                    // Если мы нашли новую секцию и у нас уже была предыдущая,
                    // добавляем якорь для предыдущей секции ПЕРЕД заголовком новой
                    if ($currentSectionIndex >= 0) {
                        $prevSection = $sections[$currentSectionIndex];

                        if ($prevSection instanceof DocumentSection) {
                            $result[] = '';
                            $result[] = $prevSection->anchor;
                            $result[] = '';
                        }
                    }
                    $currentSectionIndex = $nextSectionIndex;
                }

                $result[] = $line;
            }

            // Добавляем якорь для последней секции в конец документа
            if ($currentSectionIndex >= 0 && isset($sections[$currentSectionIndex])) {
                $lastSection = $sections[$currentSectionIndex];

                if ($lastSection instanceof DocumentSection) {
                    $result[] = '';
                    $result[] = $lastSection->anchor;
                }
            }
        }

        return implode("\n", $result);
    }

    /**
     * Извлекает номер секции из заголовка.
     */
    private function extractSectionNumber(string $title): int
    {
        if (preg_match('/^(\d+)/', trim($title), $matches)) {
            return (int) $matches[1];
        }

        return 0;
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

    /**
     * Проверяет статус документа для генерации разметки.
     *
     * @throws InvalidArgumentException При неверном статусе
     */
    private function validateDocumentStatus(DocumentProcessing $documentProcessing): void
    {
        if (!$documentProcessing->isEstimated() && !$documentProcessing->isCompleted()) {
            throw new InvalidArgumentException(
                'Document must be in estimated or completed status for markup generation. Current status: ' . $documentProcessing->status,
            );
        }
    }

    /**
     * Проверяет размер документа для генерации разметки.
     *
     * @throws InvalidArgumentException При превышении лимита размера
     */
    private function validateDocumentSize(DocumentProcessing $documentProcessing): void
    {
        $maxFileSizeMb = config('document.structure_analysis.max_file_size_mb', 50);
        assert(is_numeric($maxFileSizeMb));
        $maxFileSizeMb = (int) $maxFileSizeMb;

        $fileSizeMb = $documentProcessing->file_size / (1024 * 1024);

        if ($fileSizeMb > $maxFileSizeMb) {
            throw new InvalidArgumentException(
                "Document is too large for markup generation. Size: {$fileSizeMb}MB, Max: {$maxFileSizeMb}MB",
            );
        }
    }

    /**
     * Получает секции документа из метаданных или выполняет новый анализ.
     *
     * @throws RuntimeException При ошибке анализа структуры
     *
     * @return array<DocumentSection>
     */
    private function getOrAnalyzeSections(DocumentProcessing $documentProcessing): array
    {
        $metadata = $documentProcessing->processing_metadata;

        // Пытаемся получить секции из сохраненных метаданных
        if (is_array($metadata)
            && isset($metadata[self::STRUCTURE_ANALYSIS_KEY])
            && is_array($metadata[self::STRUCTURE_ANALYSIS_KEY])
            && isset($metadata[self::STRUCTURE_ANALYSIS_KEY][self::SECTIONS_KEY])
            && is_array($metadata[self::STRUCTURE_ANALYSIS_KEY][self::SECTIONS_KEY])) {
            $sectionsData = $metadata[self::STRUCTURE_ANALYSIS_KEY][self::SECTIONS_KEY];
            $sections = [];

            foreach ($sectionsData as $sectionData) {
                if (!is_array($sectionData)) {
                    continue;
                }

                $sections[] = new DocumentSection(
                    id: $sectionData['id'] ?? '',
                    title: $sectionData['title'] ?? '',
                    content: '', // Content not stored in metadata
                    level: $sectionData['level'] ?? 1,
                    startPosition: $sectionData['start_position'] ?? 0,
                    endPosition: $sectionData['end_position'] ?? 0,
                    anchor: $sectionData['anchor'] ?? '',
                    elements: [], // Elements not stored in metadata
                    subsections: [],
                    confidence: $sectionData['confidence'] ?? 0.0,
                );
            }

            if (count($sections) > self::MAX_SECTIONS_COUNT) {
                throw new InvalidArgumentException(
                    'Too many sections in document: ' . count($sections) . '. Max: ' . self::MAX_SECTIONS_COUNT,
                );
            }

            return $sections;
        }

        // Если секций нет в метаданных, выполняем новый анализ
        Log::info('Sections not found in metadata, performing new structure analysis', [
            'uuid' => $documentProcessing->uuid,
        ]);

        $extractedDocument = $this->extractorManager->extract(
            Storage::disk('local')->path($documentProcessing->file_path),
        );

        return $this->structureAnalyzer->analyze($extractedDocument)->sections;
    }

    /**
     * Извлекает оригинальный контент документа.
     *
     * @throws Exception|RuntimeException При ошибке извлечения контента
     */
    private function extractContent(DocumentProcessing $documentProcessing): string
    {
        $extractedDocument = $this->extractorManager->extract(
            Storage::disk('local')->path($documentProcessing->file_path),
        );

        $content = $extractedDocument->getPlainText();

        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            throw new InvalidArgumentException(
                'Document content too large for markup: ' . strlen($content) . ' bytes. Max: ' . self::MAX_CONTENT_LENGTH,
            );
        }

        return $content;
    }

    /**
     * Формирует ответ с разметкой документа.
     *
     * @param array<DocumentSection> $sections
     *
     * @return array{original_content: string, content_with_anchors: string, sections_count: int, anchors: array, structure_analysis: ?array}
     */
    private function buildMarkupResponse(string $originalContent, string $contentWithAnchors, array $sections): array
    {
        $anchors = array_map(fn (DocumentSection $section) => [
            'id' => $section->id,
            'title' => $section->title,
            'anchor' => $section->anchor,
            'level' => $section->level,
            'confidence' => $section->confidence,
        ], $sections);

        return [
            'original_content' => $originalContent,
            'content_with_anchors' => $contentWithAnchors,
            'sections_count' => count($sections),
            'anchors' => $anchors,
            'structure_analysis' => null, // Можно добавить дополнительную аналитику при необходимости
        ];
    }
}
