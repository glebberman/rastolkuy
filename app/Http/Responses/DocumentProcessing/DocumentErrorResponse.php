<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Ответ об ошибке при работе с документами.
 */
final class DocumentErrorResponse extends JsonResponse
{
    public function __construct(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        ?Throwable $exception = null,
        ?array $context = null
    ) {
        $data = [
            'success' => false,
            'message' => $message,
            'error' => [
                'type' => $this->getErrorType($exception),
                'code' => $this->getErrorCode($status, $exception),
                'details' => $context,
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid(),
            ],
        ];

        // В debug режиме добавляем детали исключения
        if (config('app.debug') && $exception !== null) {
            $data['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        parent::__construct($data, $status);
    }

    /**
     * Создает ответ для документа не найден.
     */
    public static function documentNotFound(): self
    {
        return new self(
            message: 'Документ не найден или у вас нет доступа к нему',
            status: Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Создает ответ для неправильного статуса документа.
     */
    public static function invalidDocumentStatus(string $currentStatus, string $requiredStatus): self
    {
        return new self(
            message: "Документ должен быть в статусе '{$requiredStatus}' для выполнения этой операции",
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            context: [
                'current_status' => $currentStatus,
                'required_status' => $requiredStatus,
            ]
        );
    }

    /**
     * Создает ответ для ошибки загрузки файла.
     */
    public static function fileUploadError(string $reason): self
    {
        return new self(
            message: "Ошибка загрузки файла: {$reason}",
            status: Response::HTTP_BAD_REQUEST,
            context: ['upload_error' => $reason]
        );
    }

    /**
     * Создает ответ для ошибки обработки.
     */
    public static function processingError(string $reason): self
    {
        return new self(
            message: "Ошибка обработки документа: {$reason}",
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            context: ['processing_error' => $reason]
        );
    }

    /**
     * Создает ответ для недостаточного баланса.
     */
    public static function insufficientBalance(float $required, float $available): self
    {
        return new self(
            message: 'Недостаточно средств для обработки документа',
            status: Response::HTTP_PAYMENT_REQUIRED,
            context: [
                'required_balance' => $required,
                'available_balance' => $available,
                'deficit' => $required - $available,
            ]
        );
    }

    /**
     * Определяет тип ошибки по исключению.
     */
    private function getErrorType(?Throwable $exception): string
    {
        if ($exception === null) {
            return 'validation_error';
        }

        return match (get_class($exception)) {
            'InvalidArgumentException' => 'validation_error',
            'RuntimeException' => 'runtime_error',
            'Exception' => 'general_error',
            default => 'unknown_error'
        };
    }

    /**
     * Определяет код ошибки.
     */
    private function getErrorCode(int $status, ?Throwable $exception): string
    {
        if ($exception !== null) {
            return 'DOCUMENT_' . strtoupper(class_basename($exception));
        }

        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'DOCUMENT_INVALID_REQUEST',
            Response::HTTP_NOT_FOUND => 'DOCUMENT_NOT_FOUND',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'DOCUMENT_PROCESSING_ERROR',
            Response::HTTP_PAYMENT_REQUIRED => 'DOCUMENT_INSUFFICIENT_BALANCE',
            Response::HTTP_TOO_MANY_REQUESTS => 'DOCUMENT_RATE_LIMIT',
            default => 'DOCUMENT_ERROR'
        };
    }
}