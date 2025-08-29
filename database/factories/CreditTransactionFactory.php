<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    protected $model = CreditTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [CreditTransaction::TYPE_TOPUP, CreditTransaction::TYPE_DEBIT, CreditTransaction::TYPE_REFUND];
        $type = $this->faker->randomElement($types);

        $balanceBefore = $this->faker->randomFloat(2, 0, 1000);
        $amount = $this->faker->randomFloat(2, 1, 100);

        // Adjust amount sign based on type
        if ($type === CreditTransaction::TYPE_DEBIT) {
            $amount = -$amount;
            $balanceAfter = $balanceBefore + $amount; // amount is negative
        } else {
            $balanceAfter = $balanceBefore + $amount;
        }

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $this->faker->sentence(),
            'metadata' => [
                'source' => $this->faker->randomElement(['api', 'admin', 'system']),
                'ip_address' => $this->faker->ipv4(),
            ],
            'reference_id' => $this->faker->optional()->uuid(),
            'reference_type' => $this->faker->optional()->randomElement(['document_processing', 'payment', 'refund']),
        ];
    }

    /**
     * Create a topup transaction.
     */
    public function topup(?float $amount = null): static
    {
        return $this->state(function (array $attributes) use ($amount): array {
            $actualAmount = $amount ?? $this->faker->randomFloat(2, 1, 100);

            return [
                'type' => CreditTransaction::TYPE_TOPUP,
                'amount' => $actualAmount,
                'balance_after' => $attributes['balance_before'] + $actualAmount,
                'description' => 'Credit topup',
            ];
        });
    }

    /**
     * Create a debit transaction.
     */
    public function debit(?float $amount = null): static
    {
        return $this->state(function (array $attributes) use ($amount): array {
            $actualAmount = $amount ?? $this->faker->randomFloat(2, 1, 50);

            return [
                'type' => CreditTransaction::TYPE_DEBIT,
                'amount' => -$actualAmount,
                'balance_after' => $attributes['balance_before'] - $actualAmount,
                'description' => 'Credit debit',
            ];
        });
    }

    /**
     * Create a refund transaction.
     */
    public function refund(?float $amount = null): static
    {
        return $this->state(function (array $attributes) use ($amount): array {
            $actualAmount = $amount ?? $this->faker->randomFloat(2, 1, 100);

            return [
                'type' => CreditTransaction::TYPE_REFUND,
                'amount' => $actualAmount,
                'balance_after' => $attributes['balance_before'] + $actualAmount,
                'description' => 'Credit refund',
            ];
        });
    }
}
