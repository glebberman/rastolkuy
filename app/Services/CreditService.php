<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\CreditAdded;
use App\Events\CreditDebited;
use App\Events\CreditRefunded;
use App\Events\InsufficientBalance;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class CreditService
{
    /**
     * Получить текущий баланс кредитов пользователя.
     */
    public function getBalance(User $user): float
    {
        $userCredit = $this->getOrCreateUserCredit($user);

        return $userCredit->balance;
    }

    /**
     * Проверить, достаточно ли кредитов для операции.
     */
    public function hasSufficientBalance(User $user, float $amount): bool
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        $balance = $this->getBalance($user);

        return $balance >= $amount;
    }

    /**
     * Пополнить баланс кредитов пользователя.
     */
    public function addCredits(
        User $user,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $metadata = [],
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        // @phpstan-ignore-next-line
        $maxBalance = (float) (Config::get('credits.maximum_balance') ?? 100000);

        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId, $metadata, $maxBalance): CreditTransaction {
            $userCredit = $this->getOrCreateUserCredit($user);
            $balanceBefore = $userCredit->balance;

            if ($balanceBefore + $amount > $maxBalance) {
                throw new InvalidArgumentException("Adding {$amount} credits would exceed maximum balance of {$maxBalance}");
            }

            $userCredit->addBalance($amount);
            $balanceAfter = $userCredit->fresh()?->balance ?? $userCredit->balance;

            $transaction = CreditTransaction::create([
                'user_id' => $user->id,
                'type' => CreditTransaction::TYPE_TOPUP,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'metadata' => $metadata,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            // Dispatch event
            CreditAdded::dispatch($user, $transaction, $balanceBefore, $balanceAfter);

            return $transaction;
        });
    }

    /**
     * Списать кредиты с баланса пользователя.
     */
    public function debitCredits(
        User $user,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $metadata = [],
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        $allowNegative = Config::get('credits.policies.allow_negative_balance', false);

        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId, $metadata, $allowNegative): CreditTransaction {
            $userCredit = $this->getOrCreateUserCredit($user);
            $balanceBefore = $userCredit->balance;

            if (!$allowNegative && $balanceBefore < $amount) {
                // Dispatch insufficient balance event
                InsufficientBalance::dispatch($user, $amount, $balanceBefore, 'debit_credits');

                throw new InvalidArgumentException("Insufficient balance. Required: {$amount}, Available: {$balanceBefore}");
            }

            $userCredit->subtractBalance($amount);
            $balanceAfter = $userCredit->fresh()?->balance ?? $userCredit->balance;

            $transaction = CreditTransaction::create([
                'user_id' => $user->id,
                'type' => CreditTransaction::TYPE_DEBIT,
                'amount' => -$amount, // Negative amount for debit
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'metadata' => $metadata,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            // Dispatch event
            CreditDebited::dispatch($user, $transaction, $balanceBefore, $balanceAfter, $amount);

            return $transaction;
        });
    }

    /**
     * Вернуть кредиты пользователю.
     */
    public function refundCredits(
        User $user,
        float $amount,
        string $description,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $metadata = [],
    ): CreditTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        if (!Config::get('credits.policies.refund_processing_enabled', true)) {
            throw new RuntimeException('Credit refunds are disabled');
        }

        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId, $metadata): CreditTransaction {
            $userCredit = $this->getOrCreateUserCredit($user);
            $balanceBefore = $userCredit->balance;

            $userCredit->addBalance($amount);
            $balanceAfter = $userCredit->fresh()?->balance ?? $userCredit->balance;

            $transaction = CreditTransaction::create([
                'user_id' => $user->id,
                'type' => CreditTransaction::TYPE_REFUND,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'metadata' => $metadata,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            // Dispatch event
            CreditRefunded::dispatch($user, $transaction, $balanceBefore, $balanceAfter, $description);

            return $transaction;
        });
    }

    /**
     * Конвертировать USD сумму в кредиты.
     */
    public function convertUsdToCredits(float $usdAmount): float
    {
        $rate = Config::get('credits.usd_to_credits_rate', 100);

        return $usdAmount * $rate;
    }

    /**
     * Конвертировать кредиты в USD сумму.
     */
    public function convertCreditsToUsd(float $credits): float
    {
        $rate = Config::get('credits.usd_to_credits_rate', 100);

        return $credits / $rate;
    }

    /**
     * Получить историю транзакций пользователя.
     */
    public function getTransactionHistory(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return CreditTransaction::forUser($user)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Получить статистику кредитов пользователя с кешированием.
     */
    public function getUserStatistics(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = "user_credit_stats_{$user->id}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        /** @var array */
        return Cache::remember($cacheKey, 1800, function () use ($user): array {
            $balance = $this->getBalance($user);

            return [
                'balance' => $balance,
                'total_topups' => (float) CreditTransaction::where('user_id', $user->id)->where('type', CreditTransaction::TYPE_TOPUP)->sum('amount'),
                'total_debits' => abs((float) CreditTransaction::where('user_id', $user->id)->where('type', CreditTransaction::TYPE_DEBIT)->sum('amount')),
                'total_refunds' => (float) CreditTransaction::where('user_id', $user->id)->where('type', CreditTransaction::TYPE_REFUND)->sum('amount'),
                'transaction_count' => CreditTransaction::where('user_id', $user->id)->count(),
                'last_transaction_at' => CreditTransaction::where('user_id', $user->id)->latest('created_at')->first()?->created_at,
                'cached_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Создать начальный баланс для нового пользователя.
     */
    public function createInitialBalance(User $user): UserCredit
    {
        // @phpstan-ignore-next-line
        $initialBalance = (float) (Config::get('credits.initial_balance') ?? 100);

        try {
            $userCredit = UserCredit::create([
                'user_id' => $user->id,
                'balance' => $initialBalance,
            ]);

            if ($initialBalance > 0) {
                CreditTransaction::create([
                    'user_id' => $user->id,
                    'type' => CreditTransaction::TYPE_TOPUP,
                    'amount' => $initialBalance,
                    'balance_before' => 0,
                    'balance_after' => $initialBalance,
                    'description' => 'Welcome bonus credits',
                    'metadata' => ['source' => 'registration_bonus'],
                ]);

                Log::info('Initial balance created for new user', [
                    'user_id' => $user->id,
                    'initial_balance' => $initialBalance,
                ]);
            }

            return $userCredit;
        } catch (QueryException $e) {
            // Handle duplicate key violation (user already has credits)
            return $this->getOrCreateUserCredit($user);
        }
    }

    /**
     * Запланировать асинхронный возврат кредитов.
     */
    public function scheduleRefund(
        User $user,
        float $amount,
        string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $metadata = [],
    ): void {
        \App\Jobs\ProcessCreditRefund::dispatch(
            $user->id,
            $amount,
            $reason,
            $referenceType,
            $referenceId,
            $metadata,
        );
    }

    /**
     * Очистить кеш статистики пользователя.
     */
    public function clearUserCache(User $user): void
    {
        $cacheKeys = [
            "user_credit_stats_{$user->id}",
            "user_balance_{$user->id}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear tagged cache if using Redis/Memcached
        Cache::tags(["user_credits_{$user->id}"])->flush();
    }

    /**
     * Получить или создать запись кредитов пользователя.
     */
    private function getOrCreateUserCredit(User $user): UserCredit
    {
        return UserCredit::firstOrCreate(
            ['user_id' => $user->id],
            // @phpstan-ignore-next-line
            ['balance' => (float) (Config::get('credits.initial_balance') ?? 100)],
        );
    }
}
