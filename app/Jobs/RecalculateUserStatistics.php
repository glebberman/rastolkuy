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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecalculateUserStatistics implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    public function __construct(
        public readonly int $userId,
        public readonly bool $forceRefresh = false,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(CreditService $creditService): void
    {
        try {
            $user = User::findOrFail($this->userId);

            // Clear existing cache if force refresh
            if ($this->forceRefresh) {
                Cache::forget("user_credit_stats_{$this->userId}");
            }

            // Recalculate and cache statistics
            $statistics = $creditService->getUserStatistics($user);

            Cache::put(
                "user_credit_stats_{$this->userId}",
                $statistics,
                now()->addMinutes(30),
            );

            Log::info('User credit statistics recalculated successfully', [
                'user_id' => $this->userId,
                'statistics' => $statistics,
                'job_id' => $this->job?->getJobId(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to recalculate user statistics', [
                'user_id' => $this->userId,
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
        Log::error('User statistics recalculation job failed', [
            'user_id' => $this->userId,
            'error' => $exception?->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
