<?php

declare(strict_types=1);

namespace App\Services\LLM;

use App\Services\LLM\DTOs\LLMResponse;
use App\Services\LLM\Exceptions\LLMException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Collects and manages usage metrics for LLM operations.
 */
final readonly class UsageMetrics
{
    public function __construct(
        private string $provider = 'claude',
    ) {
    }

    /**
     * Record a successful translation operation.
     *
     * @param LLMResponse $response The LLM response
     * @param string $documentType Type of document translated
     * @param string $operationType Type of operation (section, batch)
     */
    public function recordTranslation(LLMResponse $response, string $documentType, string $operationType): void
    {
        $metrics = [
            'provider' => $this->provider,
            'operation_type' => $operationType,
            'document_type' => $documentType,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'total_tokens' => $response->getTotalTokens(),
            'execution_time_ms' => $response->executionTimeMs,
            'cost_usd' => $response->costUsd,
            'success' => true,
            'timestamp' => now()->toISOString(),
        ];

        $this->storeMetrics($metrics);
        $this->updateDailyAggregates($metrics);

        Log::debug('Translation metrics recorded', $metrics);
    }

    /**
     * Record a failed operation.
     *
     * @param LLMException $exception The exception that occurred
     * @param string $documentType Type of document
     * @param string $operationType Type of operation
     */
    public function recordFailure(LLMException $exception, string $documentType, string $operationType): void
    {
        $metrics = [
            'provider' => $this->provider,
            'operation_type' => $operationType,
            'document_type' => $documentType,
            'model' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
            'execution_time_ms' => 0,
            'cost_usd' => 0,
            'success' => false,
            'error_type' => get_class($exception),
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'timestamp' => now()->toISOString(),
        ];

        $this->storeMetrics($metrics);
        $this->updateDailyAggregates($metrics);

        Log::debug('Failure metrics recorded', $metrics);
    }

    /**
     * Get usage statistics for the specified time period.
     *
     * @param int $days Number of days to look back
     *
     * @return array<string, mixed>
     */
    public function getStats(int $days = 7): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays($days);

        $dailyStats = $this->getDailyAggregates($startDate, $endDate);
        $recentFailures = $this->getRecentFailures(100);

        // Calculate totals
        $totalRequests = array_sum(array_column($dailyStats, 'total_requests'));
        $totalSuccessful = array_sum(array_column($dailyStats, 'successful_requests'));
        $totalFailed = array_sum(array_column($dailyStats, 'failed_requests'));
        $totalTokens = array_sum(array_column($dailyStats, 'total_tokens'));
        $totalCost = array_sum(array_column($dailyStats, 'total_cost_usd'));
        $avgExecutionTime = $totalSuccessful > 0
            ? array_sum(array_column($dailyStats, 'avg_execution_time_ms')) / count(array_filter(array_column($dailyStats, 'avg_execution_time_ms')))
            : 0;

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'days' => $days,
            ],
            'totals' => [
                'requests' => $totalRequests,
                'successful_requests' => $totalSuccessful,
                'failed_requests' => $totalFailed,
                'success_rate' => $totalRequests > 0 ? round(($totalSuccessful / $totalRequests) * 100, 2) : 0,
                'total_tokens' => $totalTokens,
                'total_cost_usd' => round($totalCost, 6),
                'avg_execution_time_ms' => round($avgExecutionTime, 2),
                'avg_cost_per_request' => $totalSuccessful > 0 ? round($totalCost / $totalSuccessful, 6) : 0,
                'avg_tokens_per_request' => $totalSuccessful > 0 ? round($totalTokens / $totalSuccessful, 0) : 0,
            ],
            'daily_breakdown' => $dailyStats,
            'recent_failures' => $recentFailures,
            'provider' => $this->provider,
        ];
    }

    /**
     * Get real-time usage for current hour/day.
     *
     * @return array<string, mixed>
     */
    public function getCurrentUsage(): array
    {
        $currentHour = now()->format('Y-m-d-H');
        $currentDay = now()->format('Y-m-d');

        return [
            'current_hour' => $this->getHourlyMetrics($currentHour),
            'current_day' => $this->getDailyMetrics($currentDay),
            'provider' => $this->provider,
        ];
    }

    /**
     * Store individual operation metrics.
     *
     * @param array<string, mixed> $metrics Metrics to store
     */
    private function storeMetrics(array $metrics): void
    {
        // Store in cache for recent access (keep for 24 hours)
        $cacheKey = 'llm_metrics:recent:' . now()->format('Y-m-d-H');
        $recentMetrics = Cache::get($cacheKey, []);

        if (!is_array($recentMetrics)) {
            $recentMetrics = [];
        }
        $recentMetrics[] = $metrics;

        // Keep only last 1000 entries per hour to prevent memory issues
        if (count($recentMetrics) > 1000) {
            $recentMetrics = array_slice($recentMetrics, -1000);
        }

        Cache::put($cacheKey, $recentMetrics, now()->addHours(25)); // Extra hour buffer

        // TODO: In production, you might also want to store in a database
        // DB::table('llm_usage_metrics')->insert($metrics);
    }

    /**
     * Update daily aggregates for faster querying.
     *
     * @param array<string, mixed> $metrics Metrics to aggregate
     */
    private function updateDailyAggregates(array $metrics): void
    {
        $dateKey = now()->format('Y-m-d');
        $cacheKey = "llm_daily_aggregates:{$this->provider}:{$dateKey}";

        $aggregate = Cache::get($cacheKey, [
            'date' => $dateKey,
            'provider' => $this->provider,
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_tokens' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_cost_usd' => 0,
            'total_execution_time_ms' => 0,
            'avg_execution_time_ms' => 0,
            'document_types' => [],
            'operation_types' => [],
            'models' => [],
        ]);

        if (!is_array($aggregate)) {
            $aggregate = [
                'date' => $dateKey,
                'provider' => $this->provider,
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'total_tokens' => 0,
                'total_input_tokens' => 0,
                'total_output_tokens' => 0,
                'total_cost_usd' => 0,
                'total_execution_time_ms' => 0,
                'avg_execution_time_ms' => 0,
                'document_types' => [],
                'operation_types' => [],
                'models' => [],
            ];
        }

        // Update counters
        $aggregate['total_requests'] = (int) ($aggregate['total_requests'] ?? 0) + 1;

        if ($metrics['success']) {
            $aggregate['successful_requests'] = (int) ($aggregate['successful_requests'] ?? 0) + 1;
            $totalTokens = $metrics['total_tokens'] ?? 0;
            $inputTokens = $metrics['input_tokens'] ?? 0;
            $outputTokens = $metrics['output_tokens'] ?? 0;
            $costUsd = $metrics['cost_usd'] ?? 0;
            $executionTime = $metrics['execution_time_ms'] ?? 0;

            $aggregate['total_tokens'] = (int) ($aggregate['total_tokens'] ?? 0) + (is_numeric($totalTokens) ? (int) $totalTokens : 0);
            $aggregate['total_input_tokens'] = (int) ($aggregate['total_input_tokens'] ?? 0) + (is_numeric($inputTokens) ? (int) $inputTokens : 0);
            $aggregate['total_output_tokens'] = (int) ($aggregate['total_output_tokens'] ?? 0) + (is_numeric($outputTokens) ? (int) $outputTokens : 0);
            $aggregate['total_cost_usd'] = (float) ($aggregate['total_cost_usd'] ?? 0) + (is_numeric($costUsd) ? (float) $costUsd : 0.0);
            $aggregate['total_execution_time_ms'] = (float) ($aggregate['total_execution_time_ms'] ?? 0) + (is_numeric($executionTime) ? (float) $executionTime : 0.0);

            // Calculate new average execution time
            if ($aggregate['successful_requests'] > 0) {
                $aggregate['avg_execution_time_ms'] = $aggregate['total_execution_time_ms'] / $aggregate['successful_requests'];
            }

            // Track models used
            $models = is_array($aggregate['models']) ? $aggregate['models'] : [];

            if ($metrics['model'] && !in_array($metrics['model'], $models, true)) {
                $models[] = $metrics['model'];
                $aggregate['models'] = $models;
            }
        } else {
            $aggregate['failed_requests'] = (int) ($aggregate['failed_requests'] ?? 0) + 1;
        }

        // Track document types
        $documentTypes = is_array($aggregate['document_types']) ? $aggregate['document_types'] : [];

        if ($metrics['document_type'] && !in_array($metrics['document_type'], $documentTypes, true)) {
            $documentTypes[] = $metrics['document_type'];
            $aggregate['document_types'] = $documentTypes;
        }

        // Track operation types
        $operationTypes = is_array($aggregate['operation_types']) ? $aggregate['operation_types'] : [];

        if ($metrics['operation_type'] && !in_array($metrics['operation_type'], $operationTypes, true)) {
            $operationTypes[] = $metrics['operation_type'];
            $aggregate['operation_types'] = $operationTypes;
        }

        // Store updated aggregate (expire at end of next day)
        $expiresAt = now()->addDay()->endOfDay();
        Cache::put($cacheKey, $aggregate, $expiresAt);
    }

    /**
     * Get daily aggregates for a date range.
     *
     * @param \Illuminate\Support\Carbon $startDate Start date
     * @param \Illuminate\Support\Carbon $endDate End date
     *
     * @return array<array<string, mixed>>
     */
    private function getDailyAggregates(\Illuminate\Support\Carbon $startDate, \Illuminate\Support\Carbon $endDate): array
    {
        $aggregates = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $cacheKey = "llm_daily_aggregates:{$this->provider}:{$dateKey}";

            $aggregate = Cache::get($cacheKey, [
                'date' => $dateKey,
                'provider' => $this->provider,
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'total_tokens' => 0,
                'total_cost_usd' => 0,
                'avg_execution_time_ms' => 0,
                'document_types' => [],
                'operation_types' => [],
                'models' => [],
            ]);

            if (is_array($aggregate)) {
                $aggregates[] = $aggregate;
            }
            $current->addDay();
        }

        return $aggregates;
    }

    /**
     * Get recent failures for debugging.
     *
     * @param int $limit Maximum number of failures to return
     *
     * @return array<array<string, mixed>>
     */
    private function getRecentFailures(int $limit = 50): array
    {
        $failures = [];
        $hoursToCheck = 24; // Check last 24 hours

        for ($i = 0; $i < $hoursToCheck; ++$i) {
            $hourKey = now()->subHours($i)->format('Y-m-d-H');
            $cacheKey = 'llm_metrics:recent:' . $hourKey;
            $hourlyMetrics = Cache::get($cacheKey, []);

            if (!is_array($hourlyMetrics)) {
                $hourlyMetrics = [];
            }

            foreach ($hourlyMetrics as $metric) {
                if (is_array($metric) && !($metric['success'] ?? true) && count($failures) < $limit) {
                    $failures[] = [
                        'timestamp' => $metric['timestamp'] ?? '',
                        'document_type' => $metric['document_type'] ?? 'unknown',
                        'operation_type' => $metric['operation_type'] ?? 'unknown',
                        'error_type' => $metric['error_type'] ?? 'unknown',
                        'error_message' => $metric['error_message'] ?? 'unknown error',
                        'error_code' => $metric['error_code'] ?? 0,
                    ];
                }
            }

            if (count($failures) >= $limit) {
                break;
            }
        }

        return array_reverse($failures); // Most recent first
    }

    /**
     * Get hourly metrics for a specific hour.
     *
     * @param string $hour Hour in Y-m-d-H format
     *
     * @return array<string, mixed>
     */
    private function getHourlyMetrics(string $hour): array
    {
        $cacheKey = 'llm_metrics:recent:' . $hour;
        $hourlyMetrics = Cache::get($cacheKey, []);

        if (!is_array($hourlyMetrics)) {
            $hourlyMetrics = [];
        }

        $stats = [
            'hour' => $hour,
            'total_requests' => count($hourlyMetrics),
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_tokens' => 0,
            'total_cost_usd' => 0,
        ];

        foreach ($hourlyMetrics as $metric) {
            if (is_array($metric) && ($metric['success'] ?? false)) {
                ++$stats['successful_requests'];
                $stats['total_tokens'] += (int) ($metric['total_tokens'] ?? 0);
                $stats['total_cost_usd'] += (float) ($metric['cost_usd'] ?? 0);
            } else {
                ++$stats['failed_requests'];
            }
        }

        return $stats;
    }

    /**
     * Get daily metrics summary for a specific day.
     *
     * @param string $date Date in Y-m-d format
     *
     * @return array<string, mixed>
     */
    private function getDailyMetrics(string $date): array
    {
        $cacheKey = "llm_daily_aggregates:{$this->provider}:{$date}";

        $metrics = Cache::get($cacheKey, [
            'date' => $date,
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_tokens' => 0,
            'total_cost_usd' => 0,
            'avg_execution_time_ms' => 0,
        ]);

        return is_array($metrics) ? $metrics : [];
    }
}
