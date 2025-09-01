<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use App\Http\Resources\CreditTransactionResource;
use App\Models\CreditTransaction;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreditTopupResponse extends JsonResponse
{
    public function __construct(CreditTransaction $transaction)
    {
        $data = [
            'message' => 'Кредиты успешно добавлены',
            'data' => new CreditTransactionResource($transaction),
        ];

        parent::__construct($data, Response::HTTP_CREATED);
    }
}
