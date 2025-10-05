<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Export\DownloadExportRequest;
use App\Http\Requests\Export\ExportDocumentRequest;
use App\Http\Requests\Export\GetFormatsRequest;
use App\Http\Responses\Export\DownloadResponse;
use App\Http\Responses\Export\ExportErrorResponse;
use App\Http\Responses\Export\ExportResponse;
use App\Http\Responses\Export\FormatsResponse;
use App\Models\DocumentProcessing;
use App\Services\Export\DocumentExportService;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Контроллер для экспорта документов.
 */
final class DocumentExportController extends Controller
{
    public function __construct(
        private readonly DocumentExportService $exportService
    ) {}

    /**
     * Получает список доступных форматов экспорта.
     */
    public function formats(GetFormatsRequest $request): FormatsResponse
    {
        return new FormatsResponse();
    }

    /**
     * Экспортирует документ в указанный формат.
     */
    public function export(ExportDocumentRequest $request): ExportResponse|ExportErrorResponse
    {
        try {
            \Log::info('Export request received', [
                'raw_document_id' => $request->input('document_id'),
                'converted_document_id' => $request->getDocumentId(),
                'format' => $request->getExportFormat(),
            ]);

            // Получаем документ и проверяем права доступа
            $document = DocumentProcessing::findOrFail($request->getDocumentId());

            \Log::info('Document found for export', [
                'id' => $document->id,
                'uuid' => $document->uuid,
                'filename' => $document->original_filename,
                'completed_at' => $document->completed_at?->toISOString(),
            ]);

            // Проверяем, что пользователь имеет доступ к документу
            if ($document->user_id !== $request->user()?->id) {
                return ExportErrorResponse::documentNotFound();
            }

            // Выполняем экспорт
            $export = $this->exportService->export(
                document: $document,
                format: $request->getExportFormat(),
                options: $request->getOptions()
            );

            \Log::info('Export created', [
                'export_id' => $export->id,
                'download_token' => $export->download_token,
                'document_id' => $document->id,
            ]);

            return new ExportResponse($export);

        } catch (InvalidArgumentException $e) {
            return $this->handleValidationError($e, $request);
        } catch (RuntimeException $e) {
            return $this->handleRuntimeError($e);
        } catch (Throwable $e) {
            return $this->handleGenericError($e);
        }
    }

    /**
     * Скачивает экспортированный файл по токену.
     */
    public function download(DownloadExportRequest $request): DownloadResponse|ExportErrorResponse
    {
        try {
            $export = $this->exportService->getExportByToken($request->getToken());

            if ($export === null) {
                return ExportErrorResponse::exportNotFound();
            }

            $content = $this->exportService->getExportContent($export);

            return new DownloadResponse($export, $content);

        } catch (RuntimeException $e) {
            return ExportErrorResponse::exportNotFound();
        } catch (Throwable $e) {
            return $this->handleGenericError($e);
        }
    }

    /**
     * Обрабатывает ошибки валидации.
     */
    private function handleValidationError(InvalidArgumentException $e, ExportDocumentRequest $request): ExportErrorResponse
    {
        $message = $e->getMessage();

        // Проверяем специфичные случаи
        if (str_contains($message, 'Unsupported format')) {
            return ExportErrorResponse::invalidFormat($request->getExportFormat());
        }

        if (str_contains($message, 'not completed')) {
            return ExportErrorResponse::documentNotReady();
        }

        if (str_contains($message, 'limit') && str_contains($message, 'hour')) {
            preg_match('/limit \\((\\d+)\\)/', $message, $matches);
            $limit = (int) ($matches[1] ?? 0);
            return ExportErrorResponse::rateLimitExceeded($limit, 'час');
        }

        if (str_contains($message, 'limit') && str_contains($message, 'day')) {
            preg_match('/limit \\((\\d+)\\)/', $message, $matches);
            $limit = (int) ($matches[1] ?? 0);
            return ExportErrorResponse::rateLimitExceeded($limit, 'день');
        }

        if (str_contains($message, 'size') && str_contains($message, 'exceeds')) {
            // Пытаемся извлечь размеры из сообщения
            return new ExportErrorResponse($message, 413, $e); // HTTP_PAYLOAD_TOO_LARGE
        }

        return new ExportErrorResponse($message, Response::HTTP_BAD_REQUEST, $e);
    }

    /**
     * Обрабатывает ошибки времени выполнения.
     */
    private function handleRuntimeError(RuntimeException $e): ExportErrorResponse
    {
        $message = $e->getMessage();

        if (str_contains($message, 'expired')) {
            return ExportErrorResponse::exportNotFound();
        }

        if (str_contains($message, 'not found')) {
            return ExportErrorResponse::exportNotFound();
        }

        return new ExportErrorResponse(
            'Произошла ошибка при экспорте документа',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $e
        );
    }

    /**
     * Обрабатывает общие ошибки.
     */
    private function handleGenericError(Throwable $e): ExportErrorResponse
    {
        return new ExportErrorResponse(
            'Произошла неожиданная ошибка при экспорте документа',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $e
        );
    }
}