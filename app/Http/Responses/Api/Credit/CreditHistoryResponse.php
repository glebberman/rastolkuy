<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use App\Http\Resources\CreditTransactionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class CreditHistoryResponse extends JsonResponse
{
    public function __construct(LengthAwarePaginator $transactions)
    {
        $data = [
            'message' => 'История транзакций кредитов',
            'data' => CreditTransactionResource::collection($transactions->items()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}
