<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\CreditAdded;
use App\Events\CreditDebited;
use App\Events\CreditRefunded;
use App\Events\InsufficientBalance;
use App\Listeners\InvalidateCreditCache;
use App\Listeners\LogCreditActivity;
use App\Listeners\SendLowBalanceNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, string>>
     */
    protected $listen = [
        // Credit Added Event
        CreditAdded::class => [
            LogCreditActivity::class . '@handleCreditAdded',
            InvalidateCreditCache::class,
        ],

        // Credit Debited Event
        CreditDebited::class => [
            LogCreditActivity::class . '@handleCreditDebited',
            InvalidateCreditCache::class,
            SendLowBalanceNotification::class . '@handleCreditDebited',
        ],

        // Credit Refunded Event
        CreditRefunded::class => [
            LogCreditActivity::class . '@handleCreditRefunded',
            InvalidateCreditCache::class,
        ],

        // Insufficient Balance Event
        InsufficientBalance::class => [
            LogCreditActivity::class . '@handleInsufficientBalance',
            SendLowBalanceNotification::class . '@handleInsufficientBalance',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
