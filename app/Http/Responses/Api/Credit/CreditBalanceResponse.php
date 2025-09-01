<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreditBalanceResponse extends JsonResponse
{
    public function __construct(User $user, float $balance)
    {
        $data = [
            'message' => 'Баланс кредитов пользователя',
            'data' => [
                'balance' => $balance,
                'user_id' => $user->id,
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}
