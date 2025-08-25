<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessDocumentRequest;
use App\Http\Resources\DocumentProcessingResource;
use App\Services\DocumentProcessingService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class DocumentProcessingController extends Controller
{
    public function __construct(
        private readonly DocumentProcessingService $documentProcessingService
    ) {}

    /**
     * Загрузить документ и инициировать его обработку
     */
    public function store(ProcessDocumentRequest $request): JsonResponse
    {
        try {
            $documentProcessing = $this->documentProcessingService->uploadAndProcess($request);

            return response()->json([
                'message' => 'Документ загружен и поставлен в очередь на обработку',
                'data' => new DocumentProcessingResource($documentProcessing),
            ], ResponseAlias::HTTP_CREATED);

        } catch (RuntimeException $e) {
            return response()->json([
                'error' => 'Failed to store uploaded file',
                'message' => 'Не удалось сохранить загруженный файл',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Document upload failed',
                'message' => 'Не удалось загрузить документ для обработки',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Получить статус обработки документа
     */
    public function show(string $uuid): JsonResponse
    {
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'Статус обработки документа',
            'data' => new DocumentProcessingResource($documentProcessing),
        ]);
    }

    /**
     * Получить результат обработки документа
     */
    public function result(string $uuid): JsonResponse
    {
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        if (!$documentProcessing->isCompleted()) {
            return response()->json([
                'error' => 'Processing not completed',
                'message' => 'Обработка документа еще не завершена',
                'status' => $documentProcessing->status,
                'progress' => $documentProcessing->getProgressPercentage(),
            ], ResponseAlias::HTTP_ACCEPTED);
        }

        return response()->json([
            'message' => 'Результат обработки документа',
            'data' => [
                'id' => $documentProcessing->uuid,
                'filename' => $documentProcessing->original_filename,
                'task_type' => $documentProcessing->task_type,
                'result' => $documentProcessing->result,
                'processing_time_seconds' => $documentProcessing->processing_time_seconds,
                'cost_usd' => $documentProcessing->cost_usd,
                'metadata' => $documentProcessing->processing_metadata,
                'completed_at' => $documentProcessing->completed_at?->toJSON(),
            ],
        ]);
    }

    /**
     * Получить список всех обработок (для админ панели)
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->get('status'),
            'task_type' => $request->get('task_type'),
        ];

        $perPageRaw = $request->input('per_page', 20);
        $perPage = is_numeric($perPageRaw) ? (int)$perPageRaw : 20;

        $documentProcessings = $this->documentProcessingService->getFilteredList($filters, $perPage);

        return response()->json([
            'message' => 'Список обработок документов',
            'data' => DocumentProcessingResource::collection($documentProcessings->items()),
            'meta' => [
                'current_page' => $documentProcessings->currentPage(),
                'last_page' => $documentProcessings->lastPage(),
                'per_page' => $documentProcessings->perPage(),
                'total' => $documentProcessings->total(),
                'from' => $documentProcessings->firstItem(),
                'to' => $documentProcessings->lastItem(),
            ],
        ]);
    }

    /**
     * Отменить обработку документа (если она еще не началась)
     */
    public function cancel(string $uuid): JsonResponse
    {
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        try {
            $this->documentProcessingService->cancelProcessing($documentProcessing);

            return response()->json([
                'message' => 'Обработка документа отменена',
                'data' => new DocumentProcessingResource($documentProcessing),
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Cannot cancel',
                'message' => $e->getMessage(),
                'status' => $documentProcessing->status,
            ], ResponseAlias::HTTP_CONFLICT);
        }
    }

    /**
     * Удалить запись об обработке документа
     */
    public function destroy(string $uuid): JsonResponse
    {
        $documentProcessing = $this->documentProcessingService->getByUuid($uuid);

        if (!$documentProcessing) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Документ с указанным идентификатором не найден',
            ], ResponseAlias::HTTP_NOT_FOUND);
        }

        $this->documentProcessingService->deleteProcessing($documentProcessing);

        return response()->json([
            'message' => 'Запись об обработке документа удалена',
        ]);
    }

    /**
     * Получить статистику по обработкам
     */
    public function stats(): JsonResponse
    {
        $stats = $this->documentProcessingService->getStatistics();

        return response()->json([
            'message' => 'Статистика обработки документов',
            'data' => $stats,
            'generated_at' => now()->toJSON(),
        ]);
    }
}