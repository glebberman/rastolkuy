<?php

declare(strict_types=1);

namespace App\Http\Responses\Export;

use App\Models\DocumentExport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ответ на запрос экспорта документа.
 */
final class ExportResponse extends JsonResponse
{
    public function __construct(DocumentExport $export, int $status = Response::HTTP_CREATED)
    {
        $data = [
            'success' => true,
            'message' => 'Документ успешно экспортирован',
            'data' => [
                'id' => $export->id,
                'format' => $export->format,
                'filename' => $export->filename,
                'file_size' => $export->file_size,
                'file_size_human' => $this->formatBytes($export->file_size),
                'download_token' => $export->download_token,
                'download_url' => route('api.export.download', ['token' => $export->download_token]),
                'expires_at' => $export->expires_at->toISOString(),
                'expires_in_hours' => $export->expires_at->diffInHours(now()),
                'created_at' => $export->created_at?->toISOString(),
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?: uniqid(),
            ],
        ];

        parent::__construct($data, $status);
    }

    /**
     * Форматирует размер файла в читаемый вид.
     */
    private function formatBytes(int $size): string
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