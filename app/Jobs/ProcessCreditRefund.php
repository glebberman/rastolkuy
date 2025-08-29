<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\CreditService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCreditRefund implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    public function __construct(
        public readonly int $userId,
        public readonly float $amount,
        public readonly string $reason,
        public readonly ?string $referenceType = null,
        public readonly ?string $referenceId = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(CreditService $creditService): void
    {
        try {
            $user = User::findOrFail($this->userId);

            $transaction = $creditService->refundCredits(
                $user,
                $this->amount,
                $this->reason,
                $this->referenceType,
                $this->referenceId,
                array_merge($this->metadata, [
                    'processed_by' => 'async_job',
                    'job_id' => $this->job?->getJobId(),
                ]),
            );

            Log::info('Async credit refund processed successfully', [
                'user_id' => $this->userId,
                'amount' => $this->amount,
                'transaction_id' => $transaction->id,
                'job_id' => $this->job?->getJobId(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to process credit refund', [
                'user_id' => $this->userId,
                'amount' => $this->amount,
                'error' => $e->getMessage(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('Credit refund job failed permanently', [
            'user_id' => $this->userId,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'error' => $exception?->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
