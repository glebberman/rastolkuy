<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\EstimateDocumentDto;
use App\DTOs\UploadDocumentDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PreviewPromptRequest;
use App\Http\Requests\CancelDocumentRequest;
use App\Http\Requests\DeleteDocumentRequest;
use App\Http\Requests\DocumentResultRequest;
use App\Http\Requests\EstimateDocumentRequest;
use App\Http\Requests\ProcessDocumentEstimatedRequest;
use App\Http\Requests\ProcessDocumentRequest;
use App\Http\Requests\ShowDocumentRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\DocumentCancelledResource;
use App\Http\Resources\DocumentEstimatedResource;
use App\Http\Resources\DocumentListResource;
use App\Http\Resources\DocumentProcessedResource;
use App\Http\Resources\DocumentResultResource;
use App\Http\Resources\DocumentStatsResource;
use App\Http\Resources\DocumentStatusResource;
use App\Http\Resources\DocumentStoredResource;
use App\Http\Resources\DocumentUploadedResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentProcessingService;
use App\Services\FileStorageService;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Prompt\DTOs\PromptRenderRequest;
use App\Services\Prompt\PromptManager;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class DocumentProcessingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly DocumentProcessingService $documentProcessingService,
        private readonly AuditService $auditService,
        private readonly PromptManager $promptManager,
        private readonly ExtractorManager $extractorManager,
        private readonly FileStorageService $fileStorageService,
    ) {
    }

    /**
     * Загрузить только файл без запуска обработки.
     */
    public function upload(UploadDocumentRequest $request): JsonResponse|JsonResource
    {
        $this->authorize('create', \App\Models\DocumentProcessing::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $dto = UploadDocumentDto::fromRequest($request);
            $documentProcessing = $this->documentProcessingService->uploadDocument($dto, $user);

            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'upload');

            return (new DocumentUploadedResource($documentProcessing))
                ->response()
                ->setStatusCode(ResponseAlias::HTTP_CREATED);
        } catch (RuntimeException $e) {
            Log::error('Failed to store uploaded file', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to store uploaded file',
                'message' => 'Не удалось сохранить загруженный файл',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        } catch (Exception $e) {
            Log::error('Document upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Document upload failed',
                'message' => 'Не удалось загрузить документ',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Получить предварительную оценку стоимости обработки.
     */
    public function estimate(EstimateDocumentRequest $request): JsonResponse|JsonResource
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $documentProcessing);

        try {
            $dto = EstimateDocumentDto::fromRequest($request);
            $documentProcessing = $this->documentProcessingService->estimateDocumentCost($documentProcessing, $dto);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'estimate');

            return new DocumentEstimatedResource($documentProcessing);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid document status',
                'message' => $e->getMessage(),
            ], ResponseAlias::HTTP_CONFLICT);
        } catch (Exception $e) {
            Log::error('Document cost estimation failed', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Estimation failed',
                'message' => 'Не удалось рассчитать стоимость обработки',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Запустить обработку оцененного документа.
     */
    public function process(ProcessDocumentEstimatedRequest $request): JsonResponse|JsonResource
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $documentProcessing);

        try {
            $documentProcessing = $this->documentProcessingService->processEstimatedDocument($documentProcessing);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'process_start');

            return new DocumentProcessedResource($documentProcessing);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Cannot process document',
                'message' => $e->getMessage(),
            ], ResponseAlias::HTTP_CONFLICT);
        } catch (Exception $e) {
            Log::error('Document processing failed to start', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Processing failed to start',
                'message' => 'Не удалось запустить обработку документа',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Загрузить документ и инициировать его обработку (старый метод для обратной совместимости).
     */
    public function store(ProcessDocumentRequest $request): JsonResponse|JsonResource
    {
        $this->authorize('create', \App\Models\DocumentProcessing::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $documentProcessing = $this->documentProcessingService->uploadAndProcess($request, $user);

            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'upload');

            return (new DocumentStoredResource($documentProcessing))
                ->response()
                ->setStatusCode(ResponseAlias::HTTP_CREATED);
        } catch (RuntimeException $e) {
            Log::error('Failed to store uploaded file for processing', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to store uploaded file',
                'message' => 'Не удалось сохранить загруженный файл',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        } catch (Exception $e) {
            Log::error('Document upload and processing failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Document upload failed',
                'message' => 'Не удалось загрузить документ для обработки',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Получить статус обработки документа.
     */
    public function show(ShowDocumentRequest $request): JsonResponse|JsonResource
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $documentProcessing);

        /** @var User $user */
        $user = request()->user();
        $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'view');

        return new DocumentStatusResource($documentProcessing);
    }

    /**
     * Получить результат обработки документа.
     */
    public function result(DocumentResultRequest $request): JsonResponse|JsonResource
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $documentProcessing);

        if (!$documentProcessing->isCompleted()) {
            return response()->json([
                'error' => 'Processing not completed',
                'message' => 'Обработка документа еще не завершена',
                'status' => $documentProcessing->status,
                'progress' => $documentProcessing->getProgressPercentage(),
            ], ResponseAlias::HTTP_ACCEPTED);
        }

        return new DocumentResultResource($documentProcessing);
    }

    /**
     * Получить документ с разметкой якорями (без LLM обработки).
     */
    public function markup(ShowDocumentRequest $request): JsonResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $documentProcessing);

        try {
            $markup = $this->documentProcessingService->getDocumentWithMarkup($documentProcessing);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'markup_view');

            return response()->json([
                'data' => [
                    'document_id' => $documentProcessing->uuid,
                    'status' => $documentProcessing->status,
                    'original_filename' => $documentProcessing->original_filename,
                    'file_type' => $documentProcessing->file_type,
                    'file_size' => $documentProcessing->file_size,
                    'sections_count' => $markup['sections_count'],
                    'original_content' => $markup['original_content'],
                    'content_with_anchors' => $markup['content_with_anchors'],
                    'anchors' => $markup['anchors'],
                    'structure_analysis' => $markup['structure_analysis'],
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid document status',
                'message' => $e->getMessage(),
            ], ResponseAlias::HTTP_CONFLICT);
        } catch (Exception $e) {
            Log::error('Failed to generate document markup', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Markup generation failed',
                'message' => 'Не удалось сгенерировать разметку документа',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Получить список всех обработок (для админ панели).
     */
    public function index(Request $request): JsonResponse|JsonResource
    {
        $this->authorize('viewAny', \App\Models\DocumentProcessing::class);

        $filters = [
            'status' => $request->get('status'),
            'task_type' => $request->get('task_type'),
        ];

        $perPageRaw = $request->input('per_page', 20);
        $perPage = is_numeric($perPageRaw) ? (int) $perPageRaw : 20;

        $documentProcessings = $this->documentProcessingService->getFilteredList($filters, $perPage);

        return new DocumentListResource($documentProcessings);
    }

    /**
     * Отменить обработку документа (если она еще не началась).
     */
    public function cancel(CancelDocumentRequest $request): JsonResponse|JsonResource
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('cancel', $documentProcessing);

        try {
            $this->documentProcessingService->cancelProcessing($documentProcessing);

            return new DocumentCancelledResource($documentProcessing);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Cannot cancel',
                'message' => $e->getMessage(),
                'status' => $documentProcessing->status,
            ], ResponseAlias::HTTP_CONFLICT);
        }
    }

    /**
     * Удалить запись об обработке документа.
     */
    public function destroy(DeleteDocumentRequest $request): JsonResponse|JsonResource
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('delete', $documentProcessing);

        $this->documentProcessingService->deleteProcessing($documentProcessing);

        return response()->json([
            'message' => 'Запись об обработке документа удалена',
        ]);
    }

    /**
     * Получить список документов текущего пользователя.
     */
    public function userIndex(Request $request): JsonResponse|JsonResource
    {
        /** @var User $user */
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 50)); // Limit between 1 and 50

        $documents = $user->documentProcessings()
            ->latest('created_at')
            ->paginate($perPage);

        return new DocumentListResource($documents);
    }

    /**
     * Получить предварительный просмотр промпта без отправки в LLM (для тестирования).
     */
    public function previewPrompt(PreviewPromptRequest $request): JsonResponse
    {
        /** @var string $uuid */
        $uuid = $request->route('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->authorize('view', $documentProcessing);

        try {
            // Получаем параметры из запроса с дефолтными значениями
            $systemNameRaw = $request->validated('system_name');
            $systemName = is_string($systemNameRaw) ? $systemNameRaw : 'document_translation';

            $templateNameRaw = $request->validated('template_name');
            $templateName = is_string($templateNameRaw) ? $templateNameRaw : 'translate_legal_document';

            $taskTypeRaw = $request->validated('task_type');
            $taskType = is_string($taskTypeRaw) ? $taskTypeRaw : 'translation';

            $optionsRaw = $request->validated('options');
            $options = is_array($optionsRaw) ? $optionsRaw : [];

            // Извлекаем содержимое документа
            $extractedDocument = $this->extractorManager->extract(
                $this->fileStorageService->path($documentProcessing->file_path)
            );

            // Подготавливаем переменные для промпта
            $variables = [
                'document_text' => $extractedDocument->getPlainText(),
                'document_filename' => $documentProcessing->original_filename,
                'file_type' => $documentProcessing->file_type,
                'task_type' => $taskType,
                'format_instructions' => 'Ответ должен быть в формате JSON с якорями',
                'language_style' => 'простой и понятный язык',
            ];

            // Добавляем структурные данные если есть
            if ($documentProcessing->processing_metadata &&
                isset($documentProcessing->processing_metadata['structure_analysis'])) {
                $structureData = $documentProcessing->processing_metadata['structure_analysis'];
                if (is_array($structureData) && isset($structureData['sections'])) {
                    $variables['document_sections'] = $structureData['sections'];
                }
            }

            // Создаем запрос на рендеринг промпта
            $renderRequest = new PromptRenderRequest(
                systemName: $systemName,
                templateName: $templateName,
                variables: $variables,
                options: $options
            );

            // Генерируем промпт без отправки в LLM
            $renderedPrompt = $this->promptManager->renderTemplate($renderRequest);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'prompt_preview');

            return response()->json([
                'data' => [
                    'document_id' => $documentProcessing->uuid,
                    'document_filename' => $documentProcessing->original_filename,
                    'system_name' => $systemName,
                    'template_name' => $templateName,
                    'task_type' => $taskType,
                    'variables_used' => array_keys($variables),
                    'rendered_prompt' => $renderedPrompt,
                    'prompt_length' => mb_strlen($renderedPrompt),
                    'word_count' => str_word_count($renderedPrompt),
                    'character_count' => mb_strlen($renderedPrompt),
                    'estimated_tokens' => (int) (mb_strlen($renderedPrompt) / 4), // Приблизительная оценка
                    'options' => $options,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid document status',
                'message' => $e->getMessage(),
            ], ResponseAlias::HTTP_CONFLICT);
        } catch (Exception $e) {
            Log::error('Failed to preview prompt', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Prompt preview failed',
                'message' => 'Не удалось сгенерировать предварительный просмотр промпта: ' . $e->getMessage(),
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Получить статистику по обработкам
     */
    public function stats(): JsonResponse|JsonResource
    {
        $this->authorize('stats', \App\Models\DocumentProcessing::class);

        $stats = $this->documentProcessingService->getStatistics();

        return new DocumentStatsResource($stats);
    }
}
