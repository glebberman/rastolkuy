<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\EstimateDocumentDto;
use App\DTOs\UploadDocumentDto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PreviewPromptRequest;
use App\Http\Requests\CancelDocumentRequest;
use App\Http\Requests\DeleteDocumentRequest;
use App\Http\Requests\DocumentProcessing\GetAdminDocumentListRequest;
use App\Http\Requests\DocumentProcessing\GetDocumentListRequest;
use App\Http\Requests\DocumentProcessing\GetStatsRequest;
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
use App\Http\Responses\DocumentProcessing\DocumentCancelledResponse;
use App\Http\Responses\DocumentProcessing\DocumentDeletedResponse;
use App\Http\Responses\DocumentProcessing\DocumentDownloadResponse;
use App\Http\Responses\DocumentProcessing\DocumentErrorResponse;
use App\Http\Responses\DocumentProcessing\DocumentEstimatedResponse;
use App\Http\Responses\DocumentProcessing\DocumentExportResponse;
use App\Http\Responses\DocumentProcessing\DocumentListResponse;
use App\Http\Responses\DocumentProcessing\DocumentMarkupResponse;
use App\Http\Responses\DocumentProcessing\DocumentProcessedResponse;
use App\Http\Responses\DocumentProcessing\DocumentResultResponse;
use App\Http\Responses\DocumentProcessing\DocumentStatsResponse;
use App\Http\Responses\DocumentProcessing\DocumentStatusResponse;
use App\Http\Responses\DocumentProcessing\DocumentStoredResponse;
use App\Http\Responses\DocumentProcessing\DocumentUploadedResponse;
use App\Http\Responses\DocumentProcessing\PromptPreviewResponse;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentProcessingService;
use App\Services\Export\DocumentExportService;
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
        private readonly DocumentExportService $documentExportService,
    ) {
    }

    /**
     * Загрузить только файл без запуска обработки.
     */
    public function upload(UploadDocumentRequest $request): DocumentUploadedResponse|DocumentErrorResponse
    {
        $this->authorize('create', \App\Models\DocumentProcessing::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $dto = UploadDocumentDto::fromRequest($request);
            $documentProcessing = $this->documentProcessingService->uploadDocument($dto, $user);

            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'upload');

            return new DocumentUploadedResponse($documentProcessing);
        } catch (RuntimeException $e) {
            Log::error('Failed to store uploaded file', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DocumentErrorResponse::fileUploadError('Не удалось сохранить загруженный файл');
        } catch (Exception $e) {
            Log::error('Document upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DocumentErrorResponse::fileUploadError('Не удалось загрузить документ');
        }
    }

    /**
     * Получить предварительную оценку стоимости обработки.
     */
    public function estimate(EstimateDocumentRequest $request): DocumentEstimatedResponse|DocumentErrorResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
        }

        $this->authorize('view', $documentProcessing);

        try {
            $dto = EstimateDocumentDto::fromRequest($request);
            $documentProcessing = $this->documentProcessingService->estimateDocumentCost($documentProcessing, $dto);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'estimate');

            return new DocumentEstimatedResponse($documentProcessing);
        } catch (InvalidArgumentException $e) {
            return new DocumentErrorResponse($e->getMessage(), 409, $e);
        } catch (Exception $e) {
            Log::error('Document cost estimation failed', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new DocumentErrorResponse('Не удалось рассчитать стоимость обработки', 500, $e);
        }
    }

    /**
     * Запустить обработку оцененного документа.
     */
    public function process(ProcessDocumentEstimatedRequest $request): DocumentProcessedResponse|DocumentErrorResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
        }

        $this->authorize('view', $documentProcessing);

        try {
            $documentProcessing = $this->documentProcessingService->processEstimatedDocument($documentProcessing);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'process_start');

            return new DocumentProcessedResponse($documentProcessing);
        } catch (InvalidArgumentException $e) {
            return new DocumentErrorResponse($e->getMessage(), 409, $e);
        } catch (Exception $e) {
            Log::error('Document processing failed to start', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new DocumentErrorResponse('Не удалось запустить обработку документа', ResponseAlias::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }

    /**
     * Загрузить документ и инициировать его обработку (старый метод для обратной совместимости).
     */
    public function store(ProcessDocumentRequest $request): DocumentStatusResponse|DocumentResultResponse|DocumentProcessedResponse|DocumentStoredResponse|DocumentCancelledResponse|DocumentErrorResponse|DocumentMarkupResponse|PromptPreviewResponse|DocumentDeletedResponse
    {
        $this->authorize('create', \App\Models\DocumentProcessing::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $documentProcessing = $this->documentProcessingService->uploadAndProcess($request, $user);

            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'upload');

            return new DocumentStoredResponse($documentProcessing);
        } catch (RuntimeException $e) {
            Log::error('Failed to store uploaded file for processing', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DocumentErrorResponse::fileUploadError('Не удалось сохранить загруженный файл');
        } catch (Exception $e) {
            Log::error('Document upload and processing failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DocumentErrorResponse::fileUploadError('Не удалось загрузить документ для обработки');
        }
    }

    /**
     * Получить статус обработки документа.
     */
    public function show(ShowDocumentRequest $request): DocumentStatusResponse|DocumentResultResponse|DocumentProcessedResponse|DocumentStoredResponse|DocumentCancelledResponse|DocumentErrorResponse|DocumentMarkupResponse|PromptPreviewResponse|DocumentDeletedResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
        }

        $this->authorize('view', $documentProcessing);

        /** @var User $user */
        $user = request()->user();
        $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'view');

        return new DocumentStatusResponse($documentProcessing);
    }

    /**
     * Получить результат обработки документа.
     */
    public function result(DocumentResultRequest $request): DocumentStatusResponse|DocumentResultResponse|DocumentProcessedResponse|DocumentStoredResponse|DocumentCancelledResponse|DocumentErrorResponse|DocumentMarkupResponse|PromptPreviewResponse|DocumentDeletedResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
        }

        $this->authorize('view', $documentProcessing);

        if (!$documentProcessing->isCompleted()) {
            return new DocumentErrorResponse(
                'Обработка документа еще не завершена',
                ResponseAlias::HTTP_ACCEPTED,
                null,
                [
                    'status' => $documentProcessing->status,
                    'progress' => $documentProcessing->getProgressPercentage(),
                ]
            );
        }

        return new DocumentResultResponse($documentProcessing);
    }

    /**
     * Получить документ с разметкой якорями (без LLM обработки).
     */
    public function markup(ShowDocumentRequest $request): DocumentMarkupResponse|DocumentErrorResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
        }

        $this->authorize('view', $documentProcessing);

        try {
            $markup = $this->documentProcessingService->getDocumentWithMarkup($documentProcessing);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'markup_view');

            return new DocumentMarkupResponse($markup);
        } catch (InvalidArgumentException $e) {
            return new DocumentErrorResponse($e->getMessage(), 409, $e);
        } catch (Exception $e) {
            Log::error('Failed to generate document markup', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new DocumentErrorResponse('Не удалось сгенерировать разметку документа', ResponseAlias::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }

    /**
     * Получить список всех обработок (для админ панели).
     */
    public function index(GetAdminDocumentListRequest $request): DocumentListResponse
    {
        $this->authorize('viewAny', \App\Models\DocumentProcessing::class);

        $filters = [
            'status' => $request->getStatus(),
            'task_type' => $request->getTaskType(),
            'user_id' => $request->getUserId(),
            'search' => $request->getSearch(),
            'date_from' => $request->getDateFrom(),
            'date_to' => $request->getDateTo(),
        ];

        $perPage = $request->getPerPage();

        $documentProcessings = $this->documentProcessingService->getFilteredList($filters, $perPage);

        return new DocumentListResponse($documentProcessings, isAdmin: true);
    }

    /**
     * Отменить обработку документа (если она еще не началась).
     */
    public function cancel(CancelDocumentRequest $request): DocumentStatusResponse|DocumentResultResponse|DocumentProcessedResponse|DocumentStoredResponse|DocumentCancelledResponse|DocumentErrorResponse|DocumentMarkupResponse|PromptPreviewResponse|DocumentDeletedResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
        }

        $this->authorize('cancel', $documentProcessing);

        try {
            $this->documentProcessingService->cancelProcessing($documentProcessing);

            return new DocumentCancelledResponse($documentProcessing);
        } catch (InvalidArgumentException $e) {
            return new DocumentErrorResponse(
                $e->getMessage(),
                ResponseAlias::HTTP_CONFLICT,
                $e,
                ['status' => $documentProcessing->status]
            );
        }
    }

    /**
     * Удалить запись об обработке документа.
     */
    public function destroy(DeleteDocumentRequest $request): DocumentStatusResponse|DocumentResultResponse|DocumentProcessedResponse|DocumentStoredResponse|DocumentCancelledResponse|DocumentErrorResponse|DocumentMarkupResponse|PromptPreviewResponse|DocumentDeletedResponse
    {
        /** @var string $uuid */
        $uuid = $request->validated('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
        }

        $this->authorize('delete', $documentProcessing);

        $this->documentProcessingService->deleteProcessing($documentProcessing);

        return new DocumentDeletedResponse($documentProcessing->uuid);
    }

    /**
     * Получить список документов текущего пользователя.
     */
    public function userIndex(GetDocumentListRequest $request): DocumentListResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $user->documentProcessings();

        // Применяем фильтры
        if ($status = $request->getStatus()) {
            $query->where('status', $status);
        }

        if ($taskType = $request->getTaskType()) {
            $query->where('task_type', $taskType);
        }

        if ($search = $request->getSearch()) {
            $query->where('original_filename', 'ILIKE', "%{$search}%");
        }

        // Применяем сортировку
        $query->orderBy($request->getSortBy(), $request->getSortDirection());

        $documents = $query->paginate($request->getPerPage());

        return new DocumentListResponse($documents, isAdmin: false);
    }

    /**
     * Получить предварительный просмотр промпта без отправки в LLM (для тестирования).
     */
    public function previewPrompt(PreviewPromptRequest $request): PromptPreviewResponse|DocumentErrorResponse
    {
        /** @var string $uuid */
        $uuid = $request->route('uuid');
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return DocumentErrorResponse::documentNotFound();
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

            // Получаем содержимое документа с якорями (как в markup эндпоинте)
            try {
                $markup = $this->documentProcessingService->getDocumentWithMarkup($documentProcessing);
                $documentWithAnchors = $markup['content_with_anchors'];
                $anchors = $markup['anchors'];
            } catch (Exception $markupException) {
                // Fallback: если markup не работает, используем обычное извлечение
                $extractedDocument = $this->extractorManager->extract(
                    $this->fileStorageService->path($documentProcessing->file_path),
                );
                $documentWithAnchors = $extractedDocument->getPlainText();
                $anchors = [];

                Log::warning('Failed to get document with markup, using plain text fallback', [
                    'document_uuid' => $uuid,
                    'markup_error' => $markupException->getMessage(),
                ]);
            }

            // Подготавливаем переменные для промпта
            $variables = [
                'document_with_anchors' => $documentWithAnchors,
                'document_filename' => $documentProcessing->original_filename,
                'file_type' => $documentProcessing->file_type,
                'task_type' => $taskType,
                'format_instructions' => 'Ответ должен быть в формате JSON: [{"anchor": "идентификатор_якоря", "translation": "Перевод-разъяснение простым языком предшествующего подраздела"}]',
                'language_style' => 'простой и понятный язык',
                'available_anchors' => implode(', ', array_column($anchors, 'id')),
            ];

            // Создаем запрос на рендеринг промпта
            $renderRequest = new PromptRenderRequest(
                systemName: $systemName,
                templateName: $templateName,
                variables: $variables,
                options: $options,
            );

            // Генерируем промпт без отправки в LLM
            $renderedPrompt = $this->promptManager->renderTemplate($renderRequest);

            /** @var User $user */
            $user = $request->user();
            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'prompt_preview');

            $promptData = [
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
                'estimated_tokens' => (int) (mb_strlen($renderedPrompt) / 4),
                'options' => $options,
            ];

            return new PromptPreviewResponse($promptData);
        } catch (InvalidArgumentException $e) {
            return new DocumentErrorResponse($e->getMessage(), 409, $e);
        } catch (Exception $e) {
            Log::error('Failed to preview prompt', [
                'document_uuid' => $uuid,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new DocumentErrorResponse(
                'Не удалось сгенерировать предварительный просмотр промпта: ' . $e->getMessage(),
                ResponseAlias::HTTP_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    /**
     * Получить статистику по обработкам
     */
    public function stats(GetStatsRequest $request): DocumentStatsResponse
    {
        $this->authorize('stats', \App\Models\DocumentProcessing::class);

        $period = $request->getPeriod();
        $dateFrom = $request->getDateFrom();
        $dateTo = $request->getDateTo();

        $stats = $this->documentProcessingService->getStatistics();

        return new DocumentStatsResponse($stats, $period);
    }

    /**
     * Экспортировать обработанный документ в указанный формат.
     */
    public function export(Request $request, string $uuid): DocumentExportResponse|DocumentErrorResponse
    {
        $request->validate([
            'format' => 'required|in:html,docx,pdf',
            'include_original' => 'boolean',
            'include_anchors' => 'boolean',
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

            if ($documentProcessing === null) {
                return DocumentErrorResponse::documentNotFound();
            }

            $this->authorize('export', $documentProcessing);

            if (!$documentProcessing->isCompleted()) {
                return DocumentErrorResponse::invalidDocumentStatus(
                    $documentProcessing->status,
                    'completed'
                );
            }

            $format = $request->string('format')->toString();
            $options = [
                'include_original' => $request->boolean('include_original', true),
                'include_anchors' => $request->boolean('include_anchors', false),
            ];

            $export = $this->documentExportService->export($documentProcessing, $format, $options);

            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'export');

            return new DocumentExportResponse($export);
        } catch (InvalidArgumentException $e) {
            return new DocumentErrorResponse($e->getMessage(), ResponseAlias::HTTP_BAD_REQUEST, $e);
        } catch (Exception $e) {
            Log::error('Document export failed', [
                'document_uuid' => $uuid,
                'user_id' => $user->id,
                'format' => $request->string('format'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new DocumentErrorResponse('Не удалось экспортировать документ', ResponseAlias::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }

    /**
     * Скачать экспортированный документ по токену.
     */
    public function download(Request $request, string $uuid, string $token): DocumentDownloadResponse|DocumentErrorResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

            if ($documentProcessing === null) {
                return DocumentErrorResponse::documentNotFound();
            }

            $this->authorize('view', $documentProcessing);

            $export = $this->documentExportService->getExportByToken($token);

            if ($export === null) {
                return new DocumentErrorResponse('Экспорт не найден или истек', ResponseAlias::HTTP_NOT_FOUND);
            }

            if ($export->document_processing_id !== $documentProcessing->id) {
                return new DocumentErrorResponse('Нет доступа к этому экспорту', ResponseAlias::HTTP_FORBIDDEN);
            }

            $content = $this->documentExportService->getExportContent($export);

            $this->auditService->logDocumentAccess($user, $documentProcessing->uuid, 'download');

            return new DocumentDownloadResponse($export, $content);
        } catch (Exception $e) {
            Log::error('Document download failed', [
                'document_uuid' => $uuid,
                'token' => $token,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return new DocumentErrorResponse('Не удалось скачать файл', ResponseAlias::HTTP_INTERNAL_SERVER_ERROR, $e);
        }
    }
}
