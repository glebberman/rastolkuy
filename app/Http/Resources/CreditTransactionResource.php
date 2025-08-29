<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CreditTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditTransaction
 */
class CreditTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CreditTransaction $transaction */
        $transaction = $this->resource;

        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'type_description' => $transaction->getTypeDescription(),
            'amount' => $transaction->amount,
            'absolute_amount' => $transaction->getAbsoluteAmount(),
            'balance_before' => $transaction->balance_before,
            'balance_after' => $transaction->balance_after,
            'description' => $transaction->description,
            'metadata' => $transaction->metadata,
            'reference_id' => $transaction->reference_id,
            'reference_type' => $transaction->reference_type,
            'timestamps' => [
                'created_at' => $transaction->created_at?->toISOString(),
                'updated_at' => $transaction->updated_at?->toISOString(),
            ],
            // Дополнительная информация для отладки в dev окружении
            'debug_info' => $this->when(app()->environment('local'), [
                'user_id' => $transaction->user_id,
                'database_id' => $transaction->id,
                'is_topup' => $transaction->isTopup(),
                'is_debit' => $transaction->isDebit(),
                'is_refund' => $transaction->isRefund(),
            ]),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => 'v1',
                'processed_at' => now()->toISOString(),
            ],
        ];
    }
}
