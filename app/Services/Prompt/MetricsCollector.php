<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use App\Models\PromptExecution;
use App\Models\PromptFeedback;
use App\Models\PromptSystem;
use DateTime;

final readonly class MetricsCollector
{
    public function collectSystemMetrics(string $systemName, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $system = PromptSystem::where('name', $systemName)->firstOrFail();

        $dateFrom = $dateFrom ? new DateTime($dateFrom) : new DateTime('-30 days');
        $dateTo = $dateTo ? new DateTime($dateTo) : new DateTime();

        $executions = $system->executions()
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        return [
            'system_info' => [
                'name' => $system->name,
                'type' => $system->type,
                'version' => $system->version,
            ],
            'period' => [
                'from' => $dateFrom->format('Y-m-d'),
                'to' => $dateTo->format('Y-m-d'),
            ],
            'execution_metrics' => $this->calculateExecutionMetrics($executions),
            'quality_metrics' => $this->calculateQualityMetrics($executions),
            'performance_metrics' => $this->calculatePerformanceMetrics($executions),
            'feedback_metrics' => $this->calculateFeedbackMetrics($executions),
            'trend_analysis' => $this->calculateTrends($executions),
        ];
    }

    public function collectGlobalMetrics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ? new DateTime($dateFrom) : new DateTime('-30 days');
        $dateTo = $dateTo ? new DateTime($dateTo) : new DateTime();

        $executions = PromptExecution::whereBetween('created_at', [$dateFrom, $dateTo])->get();

        $systemMetrics = [];

        foreach (PromptSystem::all() as $system) {
            $systemExecutions = $executions->where('prompt_system_id', $system->id);
            $systemMetrics[$system->name] = [
                'executions_count' => $systemExecutions->count(),
                'success_rate' => $this->calculateSuccessRate($systemExecutions),
                'avg_quality' => $this->calculateAverageQuality($systemExecutions),
            ];
        }

        return [
            'period' => [
                'from' => $dateFrom->format('Y-m-d'),
                'to' => $dateTo->format('Y-m-d'),
            ],
            'overview' => [
                'total_executions' => $executions->count(),
                'total_systems' => PromptSystem::count(),
                'active_systems' => PromptSystem::where('is_active', true)->count(),
                'total_feedback_entries' => PromptFeedback::count(),
            ],
            'execution_metrics' => $this->calculateExecutionMetrics($executions),
            'quality_metrics' => $this->calculateQualityMetrics($executions),
            'performance_metrics' => $this->calculatePerformanceMetrics($executions),
            'feedback_metrics' => $this->calculateFeedbackMetrics($executions),
            'system_breakdown' => $systemMetrics,
        ];
    }

    public function getQualityReport(int $executionId): array
    {
        $execution = PromptExecution::with(['promptSystem', 'promptTemplate'])
            ->findOrFail($executionId);

        $feedback = PromptFeedback::where('prompt_execution_id', $executionId)->get();

        return [
            'execution_info' => [
                'id' => $execution->id,
                'execution_id' => $execution->execution_id,
                'system' => $execution->promptSystem->name,
                'template' => $execution->promptTemplate?->name,
                'status' => $execution->status,
                'created_at' => $execution->created_at,
            ],
            'quality_scores' => $execution->quality_metrics ?? [],
            'user_feedback' => $feedback->map(function ($fb) {
                return [
                    'type' => $fb->feedback_type,
                    'rating' => $fb->rating,
                    'comment' => $fb->comment,
                    'details' => $fb->details,
                    'created_at' => $fb->created_at,
                ];
            })->toArray(),
            'recommendations' => $this->generateQualityRecommendations($execution, $feedback),
        ];
    }

    public function recordFeedback(int $executionId, array $feedbackData): PromptFeedback
    {
        return PromptFeedback::create([
            'prompt_execution_id' => $executionId,
            'feedback_type' => $feedbackData['type'],
            'rating' => $feedbackData['rating'] ?? null,
            'comment' => $feedbackData['comment'] ?? null,
            'details' => $feedbackData['details'] ?? null,
            'user_type' => $feedbackData['user_type'] ?? 'anonymous',
            'user_id' => $feedbackData['user_id'] ?? null,
            'metadata' => $feedbackData['metadata'] ?? null,
        ]);
    }

    public function generatePerformanceReport(): array
    {
        $slowExecutions = PromptExecution::where('execution_time_ms', '>', 30000)
            ->where('status', 'success')
            ->with(['promptSystem'])
            ->orderBy('execution_time_ms', 'desc')
            ->limit(10)
            ->get();

        $expensiveExecutions = PromptExecution::where('cost_usd', '>', 0.1)
            ->where('status', 'success')
            ->with(['promptSystem'])
            ->orderBy('cost_usd', 'desc')
            ->limit(10)
            ->get();

        $failedExecutions = PromptExecution::where('status', 'failed')
            ->with(['promptSystem'])
            ->latest()
            ->limit(20)
            ->get();

        return [
            'performance_issues' => [
                'slow_executions' => $slowExecutions->map(function ($exec) {
                    return [
                        'execution_id' => $exec->execution_id,
                        'system' => $exec->promptSystem->name,
                        'execution_time_ms' => $exec->execution_time_ms,
                        'created_at' => $exec->created_at,
                    ];
                }),
                'expensive_executions' => $expensiveExecutions->map(function ($exec) {
                    return [
                        'execution_id' => $exec->execution_id,
                        'system' => $exec->promptSystem->name,
                        'cost_usd' => $exec->cost_usd,
                        'tokens_used' => $exec->tokens_used,
                        'created_at' => $exec->created_at,
                    ];
                }),
                'failed_executions' => $failedExecutions->map(function ($exec) {
                    return [
                        'execution_id' => $exec->execution_id,
                        'system' => $exec->promptSystem->name,
                        'error' => $exec->error_message,
                        'created_at' => $exec->created_at,
                    ];
                }),
            ],
            'recommendations' => $this->generatePerformanceRecommendations(),
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptExecution> $executions
     */
    private function calculateExecutionMetrics($executions): array
    {
        $total = $executions->count();
        $successful = $executions->where('status', 'success')->count();
        $failed = $executions->where('status', 'failed')->count();
        $pending = $executions->where('status', 'pending')->count();

        return [
            'total_executions' => $total,
            'successful_executions' => $successful,
            'failed_executions' => $failed,
            'pending_executions' => $pending,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptExecution> $executions
     */
    private function calculateQualityMetrics($executions): array
    {
        $successfulExecutions = $executions->where('status', 'success');

        if ($successfulExecutions->isEmpty()) {
            return [
                'average_quality_score' => 0,
                'quality_distribution' => [],
                'high_quality_rate' => 0,
            ];
        }

        $qualityScores = [];
        $distribution = ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0];

        foreach ($successfulExecutions as $execution) {
            $score = $execution->quality_metrics['overall_score'] ?? null;

            if ($score !== null) {
                $qualityScores[] = $score;

                if ($score >= 0.9) {
                    ++$distribution['excellent'];
                } elseif ($score >= 0.7) {
                    ++$distribution['good'];
                } elseif ($score >= 0.5) {
                    ++$distribution['fair'];
                } else {
                    ++$distribution['poor'];
                }
            }
        }

        $averageQuality = !empty($qualityScores) ? array_sum($qualityScores) / count($qualityScores) : 0;
        $highQualityCount = array_filter($qualityScores, fn ($score) => $score >= 0.8);

        return [
            'average_quality_score' => round($averageQuality, 3),
            'quality_distribution' => $distribution,
            'high_quality_rate' => !empty($qualityScores) ? round((count($highQualityCount) / count($qualityScores)) * 100, 2) : 0,
            'quality_scores_count' => count($qualityScores),
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptExecution> $executions
     */
    private function calculatePerformanceMetrics($executions): array
    {
        $successfulExecutions = $executions->where('status', 'success')
            ->whereNotNull('execution_time_ms');

        if ($successfulExecutions->isEmpty()) {
            return [
                'average_execution_time_ms' => 0,
                'total_cost_usd' => 0,
                'total_tokens_used' => 0,
                'average_cost_per_execution' => 0,
            ];
        }

        $executionTimes = $successfulExecutions->pluck('execution_time_ms')->filter();
        $costs = $successfulExecutions->whereNotNull('cost_usd')->pluck('cost_usd');
        $tokens = $successfulExecutions->whereNotNull('tokens_used')->pluck('tokens_used');

        return [
            'average_execution_time_ms' => $executionTimes->isNotEmpty() ? round($executionTimes->avg() ?? 0, 2) : 0,
            'median_execution_time_ms' => $executionTimes->isNotEmpty() ? $executionTimes->median() : 0,
            'total_cost_usd' => round(is_numeric($costs->sum()) ? (float) $costs->sum() : 0.0, 6),
            'total_tokens_used' => $tokens->sum(),
            'average_cost_per_execution' => $costs->isNotEmpty() ? round($costs->avg() ?? 0, 6) : 0,
            'average_tokens_per_execution' => $tokens->isNotEmpty() ? round($tokens->avg() ?? 0) : 0,
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptExecution> $executions
     */
    private function calculateFeedbackMetrics($executions): array
    {
        $executionIds = $executions->pluck('id');
        $feedback = PromptFeedback::whereIn('prompt_execution_id', $executionIds)->get();

        if ($feedback->isEmpty()) {
            return [
                'total_feedback_entries' => 0,
                'average_user_rating' => 0,
                'feedback_by_type' => [],
            ];
        }

        $ratings = $feedback->whereNotNull('rating')->pluck('rating');
        $feedbackByType = $feedback->groupBy('feedback_type')->map->count();

        return [
            'total_feedback_entries' => $feedback->count(),
            'average_user_rating' => $ratings->isNotEmpty() ? round($ratings->avg() ?? 0, 2) : 0,
            'feedback_by_type' => $feedbackByType->toArray(),
            'feedback_coverage' => $executions->count() > 0 ? round(($feedback->count() / $executions->count()) * 100, 2) : 0,
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptExecution> $executions
     */
    private function calculateTrends($executions): array
    {
        $executionsByDay = $executions->groupBy(function ($execution) {
            return $execution->created_at?->format('Y-m-d') ?? 'unknown';
        })->map->count();

        $successRateByDay = [];

        foreach ($executionsByDay as $date => $count) {
            $dayExecutions = $executions->filter(function ($execution) use ($date) {
                return $execution->created_at?->format('Y-m-d') === $date;
            });

            $successful = $dayExecutions->where('status', 'success')->count();
            $successRateByDay[$date] = $count > 0 ? round(($successful / $count) * 100, 2) : 0;
        }

        return [
            'executions_by_day' => $executionsByDay->toArray(),
            'success_rate_by_day' => $successRateByDay,
            'trend_direction' => $this->calculateTrendDirection($successRateByDay),
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptExecution> $executions
     */
    private function calculateSuccessRate($executions): float
    {
        if ($executions->isEmpty()) {
            return 0.0;
        }

        $successful = $executions->where('status', 'success')->count();

        return round(($successful / $executions->count()) * 100, 2);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptExecution> $executions
     */
    private function calculateAverageQuality($executions): float
    {
        $qualityScores = [];

        foreach ($executions as $execution) {
            $score = $execution->quality_metrics['overall_score'] ?? null;

            if ($score !== null) {
                $qualityScores[] = $score;
            }
        }

        return !empty($qualityScores) ? round(array_sum($qualityScores) / count($qualityScores), 3) : 0.0;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PromptFeedback> $feedback
     */
    private function generateQualityRecommendations(PromptExecution $execution, $feedback): array
    {
        $recommendations = [];

        $qualityScore = $execution->quality_metrics['overall_score'] ?? null;

        if ($qualityScore !== null && $qualityScore < 0.7) {
            $recommendations[] = 'Качество ответа ниже ожидаемого. Рассмотрите улучшение промпта или настройку параметров.';
        }

        $avgRating = $feedback->whereNotNull('rating')->avg('rating');

        if ($avgRating !== null && $avgRating < 3.0) {
            $recommendations[] = 'Низкая оценка пользователей. Проанализируйте отзывы для улучшения системы.';
        }

        if ($execution->execution_time_ms > 20000) {
            $recommendations[] = 'Время выполнения превышает 20 секунд. Оптимизируйте промпт или используйте более быструю модель.';
        }

        return $recommendations;
    }

    private function generatePerformanceRecommendations(): array
    {
        return [
            'Оптимизируйте медленные промпты, сокращая их длину или упрощая структуру',
            'Рассмотрите использование менее дорогих моделей для простых задач',
            'Анализируйте причины неудачных выполнений и улучшайте обработку ошибок',
            'Внедрите кеширование для часто используемых запросов',
        ];
    }

    private function calculateTrendDirection(array $data): string
    {
        if (count($data) < 2) {
            return 'insufficient_data';
        }

        $values = array_values($data);
        $firstHalf = array_slice($values, 0, (int) ceil(count($values) / 2));
        $secondHalf = array_slice($values, (int) floor(count($values) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $difference = $secondAvg - $firstAvg;

        if (abs($difference) < 5) {
            return 'stable';
        } elseif ($difference > 0) {
            return 'improving';
        } else {
            return 'declining';
        }
    }
}
