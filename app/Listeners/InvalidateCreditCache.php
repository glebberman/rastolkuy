<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CreditAdded;
use App\Events\CreditDebited;
use App\Events\CreditRefunded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class InvalidateCreditCache implements ShouldQueue
{
    /**
     * Handle credit balance change events.
     */
    public function handle(CreditAdded|CreditDebited|CreditRefunded $event): void
    {
        // Invalidate user statistics cache
        Cache::forget("user_credit_stats_{$event->user->id}");

        // Invalidate user balance cache if it exists
        Cache::forget("user_balance_{$event->user->id}");

        // Invalidate credit history cache for this user
        Cache::tags(["user_credits_{$event->user->id}"])->flush();
    }
}
