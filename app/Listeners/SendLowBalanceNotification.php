<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CreditDebited;
use App\Events\InsufficientBalance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendLowBalanceNotification implements ShouldQueue
{
    /**
     * Handle credit debited events to check for low balance.
     */
    public function handleCreditDebited(CreditDebited $event): void
    {
        // @phpstan-ignore-next-line
        $lowBalanceThreshold = (float) (Config::get('credits.low_balance_threshold') ?? 10);

        if ($event->newBalance <= $lowBalanceThreshold && $event->newBalance > 0) {
            Log::info('Low balance threshold reached', [
                'user_id' => $event->user->id,
                'balance' => $event->newBalance,
                'threshold' => $lowBalanceThreshold,
            ]);

            // Here you could send actual notifications
            // Notification::send($event->user, new LowBalanceNotification($event->newBalance));
        }
    }

    /**
     * Handle insufficient balance events.
     */
    public function handleInsufficientBalance(InsufficientBalance $event): void
    {
        Log::warning('User attempted operation with insufficient balance', [
            'user_id' => $event->user->id,
            'operation' => $event->operation,
            'required' => $event->requiredAmount,
            'available' => $event->availableBalance,
        ]);

        // Here you could send notifications about failed operations
        // Notification::send($event->user, new InsufficientBalanceNotification($event));
    }
}
