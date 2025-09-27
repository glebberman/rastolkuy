<?php

declare(strict_types=1);

namespace App\Http\Responses\Export;

use App\Models\DocumentExport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Ответ для скачивания экспортированного файла.
 */
final class DownloadResponse extends Response
{
    public function __construct(DocumentExport $export, string $content)
    {
        $mimeType = $this->getMimeType($export->format);
        $filename = $export->filename;

        $headers = [
            'Content-Type' => $mimeType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Export-ID' => (string) $export->id,
            'X-Export-Format' => $export->format,
            'X-Export-Created' => $export->created_at ? $export->created_at->toISOString() : '',
        ];

        parent::__construct($content, self::HTTP_OK, $headers);
    }

    /**
     * Получает MIME-тип для формата экспорта.
     */
    private function getMimeType(string $format): string
    {
        return match ($format) {
            DocumentExport::FORMAT_HTML => 'text/html; charset=utf-8',
            DocumentExport::FORMAT_DOCX => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            DocumentExport::FORMAT_PDF => 'application/pdf',
            default => 'application/octet-stream'
        };
    }
}