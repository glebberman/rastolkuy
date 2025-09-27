<?php

declare(strict_types=1);

namespace App\Http\Responses\DocumentProcessing;

use App\Http\Resources\DocumentListResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Ответ на запрос списка документов.
 */
final class DocumentListResponse extends BaseDocumentResponse
{
    public function __construct(LengthAwarePaginator $documents, bool $isAdmin = false)
    {
        $resource = new DocumentListResource($documents);

        parent::__construct(
            resource: $resource,
            message: $isAdmin ? 'Список всех документов получен' : 'Список документов пользователя получен',
            meta: [
                'pagination' => [
                    'current_page' => $documents->currentPage(),
                    'last_page' => $documents->lastPage(),
                    'per_page' => $documents->perPage(),
                    'total' => $documents->total(),
                    'has_more_pages' => $documents->hasMorePages(),
                ],
                'is_admin_view' => $isAdmin,
            ]
        );
    }
}