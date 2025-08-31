<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserCredit>
 */
class UserCreditFactory extends Factory
{
    protected $model = UserCredit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => $this->faker->randomFloat(2, 0, 1000),
        ];
    }

    /**
     * Create a user credit with zero balance.
     */
    public function withZeroBalance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'balance' => 0.00,
        ]);
    }

    /**
     * Create a user credit with specific balance.
     */
    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes): array => [
            'balance' => $balance,
        ]);
    }

    /**
     * Create a user credit with high balance.
     */
    public function withHighBalance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'balance' => $this->faker->randomFloat(2, 500, 2000),
        ]);
    }

    /**
     * Create a user credit with low balance.
     */
    public function withLowBalance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'balance' => $this->faker->randomFloat(2, 0, 50),
        ]);
    }
}