<?php

declare(strict_types=1);

namespace App\Http\Responses\Export;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Ответ об ошибке экспорта документа.
 */
final class ExportErrorResponse extends JsonResponse
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
     * Создает ответ для недопустимого формата.
     */
    public static function invalidFormat(string $format): self
    {
        return new self(
            message: "Неподдерживаемый формат экспорта: {$format}",
            status: Response::HTTP_BAD_REQUEST,
            context: ['provided_format' => $format, 'supported_formats' => ['html', 'docx', 'pdf']]
        );
    }

    /**
     * Создает ответ для превышения лимита.
     */
    public static function rateLimitExceeded(int $limit, string $period): self
    {
        return new self(
            message: "Превышен лимит экспортов: {$limit} за {$period}",
            status: Response::HTTP_TOO_MANY_REQUESTS,
            context: ['limit' => $limit, 'period' => $period]
        );
    }

    /**
     * Создает ответ для превышения размера файла.
     */
    public static function fileSizeExceeded(int $actualSize, int $maxSize): self
    {
        return new self(
            message: 'Размер файла превышает допустимый лимит',
            status: 413, // HTTP_PAYLOAD_TOO_LARGE
            context: [
                'actual_size' => $actualSize,
                'max_size' => $maxSize,
                'actual_size_human' => self::formatBytes($actualSize),
                'max_size_human' => self::formatBytes($maxSize),
            ]
        );
    }

    /**
     * Создает ответ для документа не готового к экспорту.
     */
    public static function documentNotReady(): self
    {
        return new self(
            message: 'Документ еще не готов для экспорта. Дождитесь завершения обработки.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    /**
     * Создает ответ для не найденного документа.
     */
    public static function documentNotFound(): self
    {
        return new self(
            message: 'Документ не найден или у вас нет доступа к нему',
            status: Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Создает ответ для не найденного экспорта.
     */
    public static function exportNotFound(): self
    {
        return new self(
            message: 'Экспорт не найден или срок его действия истек',
            status: Response::HTTP_NOT_FOUND
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
            return 'EXPORT_' . strtoupper(class_basename($exception));
        }

        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'EXPORT_INVALID_REQUEST',
            Response::HTTP_NOT_FOUND => 'EXPORT_NOT_FOUND',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'EXPORT_NOT_READY',
            Response::HTTP_TOO_MANY_REQUESTS => 'EXPORT_RATE_LIMIT',
            413 => 'EXPORT_SIZE_LIMIT', // HTTP_PAYLOAD_TOO_LARGE
            default => 'EXPORT_ERROR'
        };
    }

    /**
     * Форматирует размер файла в читаемый вид.
     */
    private static function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $floatSize = (float) $size;

        while ($floatSize >= 1024 && $unitIndex < count($units) - 1) {
            $floatSize /= 1024;
            ++$unitIndex;
        }

        return round($floatSize, 2) . ' ' . $units[$unitIndex];
    }
}