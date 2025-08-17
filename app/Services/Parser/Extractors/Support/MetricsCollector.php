<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors\Support;

class MetricsCollector
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $metrics = [];

    public function record(string $operation, float $duration, int $dataSize = 0, array $additionalData = []): void
    {
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = [
                'count' => 0,
                'total_duration' => 0.0,
                'total_data_size' => 0,
                'min_duration' => PHP_FLOAT_MAX,
                'max_duration' => 0.0,
                'operations' => [],
            ];
        }

        $metric = &$this->metrics[$operation];
        
        ++$metric['count'];
        $metric['total_duration'] += $duration;
        $metric['total_data_size'] += $dataSize;
        $metric['min_duration'] = min($metric['min_duration'], $duration);
        $metric['max_duration'] = max($metric['max_duration'], $duration);

        /** @var array<string, mixed> $operationsArray */
        $operationsArray = $metric['operations'];
        $operationsArray[] = [
            'timestamp' => microtime(true),
            'duration' => $duration,
            'data_size' => $dataSize,
            'additional' => $additionalData,
        ];
        $metric['operations'] = $operationsArray;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getMetrics(): array
    {
        $result = [];

        foreach ($this->metrics as $operation => $data) {
            $result[$operation] = [
                'count' => $data['count'],
                'total_duration' => $data['total_duration'],
                'average_duration' => $data['count'] > 0 ? $data['total_duration'] / $data['count'] : 0,
                'min_duration' => $data['min_duration'] === PHP_FLOAT_MAX ? 0 : $data['min_duration'],
                'max_duration' => $data['max_duration'],
                'total_data_size' => $data['total_data_size'],
                'average_data_size' => $data['count'] > 0 ? $data['total_data_size'] / $data['count'] : 0,
                'throughput' => $data['total_duration'] > 0 ? $data['total_data_size'] / $data['total_duration'] : 0,
            ];
        }

        return $result;
    }

    public function getOperationMetrics(string $operation): ?array
    {
        return $this->metrics[$operation] ?? null;
    }

    public function reset(): void
    {
        $this->metrics = [];
    }

    public function getTotalDuration(): float
    {
        return array_sum(array_column($this->metrics, 'total_duration'));
    }

    public function getTotalOperations(): int
    {
        return array_sum(array_column($this->metrics, 'count'));
    }
}
