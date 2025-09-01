<?php

declare(strict_types=1);

namespace App\Http\Responses\Api\Credit;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreditCostsResponse extends JsonResponse
{
    /**
     * @param array<string, float> $creditCosts
     * @param array<string> $supportedCurrencies
     */
    public function __construct(array $creditCosts, string $baseCurrency, array $supportedCurrencies)
    {
        $data = [
            'message' => 'Стоимость кредитов в валютах',
            'data' => [
                'credit_costs' => $creditCosts,
                'base_currency' => $baseCurrency,
                'supported_currencies' => $supportedCurrencies,
                'description' => 'Cost of 1 credit in different currencies',
                'updated_at' => now()->toISOString(),
            ],
        ];

        parent::__construct($data, Response::HTTP_OK);
    }
}
