<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Models\DocumentExport;
use Symfony\Component\HttpFoundation\Response;

final class DocumentDownloadResponse extends Response
{
    public function __construct(DocumentExport $export, string $content)
    {
        parent::__construct(
            content: $content,
            status: self::HTTP_OK,
            headers: [
                'Content-Type' => $export->getMimeType(),
                'Content-Disposition' => "attachment; filename=\"{$export->filename}\"",
                'Content-Length' => (string) $export->file_size,
            ]
        );
    }
}