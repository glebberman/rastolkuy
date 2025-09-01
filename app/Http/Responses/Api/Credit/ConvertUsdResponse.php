<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ConvertUsdResponse extends JsonResponse
{
    public function __construct(float $usdAmount, float $credits)
    {
        $data = [
            'message' => 'Конвертация USD в кредиты',
            'data' => [
                'usd_amount' => $usdAmount,
                'credits' => $credits,
                'rate' => config('credits.usd_to_credits_rate', 100),
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}
