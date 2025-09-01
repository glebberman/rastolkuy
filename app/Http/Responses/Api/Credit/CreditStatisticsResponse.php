<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreditStatisticsResponse extends JsonResponse
{
    /**
     * @param array<string, mixed> $statistics
     */
    public function __construct(array $statistics)
    {
        $data = [
            'message' => 'Статистика кредитов пользователя',
            'data' => $statistics,
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}
