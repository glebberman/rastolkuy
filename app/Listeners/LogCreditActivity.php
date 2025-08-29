<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CreditAdded;
use App\Events\CreditDebited;
use App\Events\CreditRefunded;
use App\Events\InsufficientBalance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogCreditActivity implements ShouldQueue
{
    /**
     * Handle credit added events.
     */
    public function handleCreditAdded(CreditAdded $event): void
    {
        Log::info('Credit added to user account', [
            'user_id' => $event->user->id,
            'user_email' => $event->user->email,
            'transaction_id' => $event->transaction->id,
            'amount' => $event->transaction->amount,
            'previous_balance' => $event->previousBalance,
            'new_balance' => $event->newBalance,
            'description' => $event->transaction->description,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle credit debited events.
     */
    public function handleCreditDebited(CreditDebited $event): void
    {
        Log::info('Credit debited from user account', [
            'user_id' => $event->user->id,
            'user_email' => $event->user->email,
            'transaction_id' => $event->transaction->id,
            'amount' => $event->amount,
            'previous_balance' => $event->previousBalance,
            'new_balance' => $event->newBalance,
            'description' => $event->transaction->description,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle credit refunded events.
     */
    public function handleCreditRefunded(CreditRefunded $event): void
    {
        Log::info('Credit refunded to user account', [
            'user_id' => $event->user->id,
            'user_email' => $event->user->email,
            'transaction_id' => $event->transaction->id,
            'amount' => $event->transaction->amount,
            'previous_balance' => $event->previousBalance,
            'new_balance' => $event->newBalance,
            'reason' => $event->reason,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle insufficient balance events.
     */
    public function handleInsufficientBalance(InsufficientBalance $event): void
    {
        Log::warning('Insufficient balance detected', [
            'user_id' => $event->user->id,
            'user_email' => $event->user->email,
            'required_amount' => $event->requiredAmount,
            'available_balance' => $event->availableBalance,
            'deficit' => $event->requiredAmount - $event->availableBalance,
            'operation' => $event->operation,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
