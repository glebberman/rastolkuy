<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditAdded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly CreditTransaction $transaction,
        public readonly float $previousBalance,
        public readonly float $newBalance,
    ) {
    }
}
