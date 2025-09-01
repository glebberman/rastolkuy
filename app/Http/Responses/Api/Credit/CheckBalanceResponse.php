<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckBalanceResponse extends JsonResponse
{
    public function __construct(
        float $currentBalance,
        float $requiredAmount,
        bool $hasSufficient,
        float $deficit,
    ) {
        $data = [
            'message' => 'Проверка баланса кредитов',
            'data' => [
                'current_balance' => $currentBalance,
                'required_amount' => $requiredAmount,
                'has_sufficient_balance' => $hasSufficient,
                'deficit' => $deficit,
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}
