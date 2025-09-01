<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExchangeRatesResponse extends JsonResponse
{
    /**
     * @param array<string, float> $rates
     * @param array<string> $supportedCurrencies
     */
    public function __construct(array $rates, string $baseCurrency, array $supportedCurrencies)
    {
        $data = [
            'message' => 'Курсы обмена валют',
            'data' => [
                'rates' => $rates,
                'base_currency' => $baseCurrency,
                'supported_currencies' => $supportedCurrencies,
                'updated_at' => now()->toISOString(),
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}
