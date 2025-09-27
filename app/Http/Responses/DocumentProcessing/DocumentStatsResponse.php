<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentStatsResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ответ на запрос статистики по документам.
 */
final class DocumentStatsResponse extends BaseDocumentResponse
{
    /**
     * @param array<string, mixed> $stats
     */
    public function __construct(array $stats, string $period = 'month')
    {
        $resource = new DocumentStatsResource($stats);

        parent::__construct(
            resource: $resource,
            message: 'Статистика по документам получена',
            status: Response::HTTP_OK,
            meta: [
                'period' => $period,
                'generated_at' => now()->toISOString(),
                'data_points' => count($stats),
            ]
        );
    }
}