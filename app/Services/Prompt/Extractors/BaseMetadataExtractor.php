<?php

declare(strict_types=1);

namespace App\Services\Prompt\Extractors;

abstract readonly class BaseMetadataExtractor
{
    abstract public function extract(array $data): array;

    abstract public function getSchemaType(): string;

    protected function extractCommonMetadata(array $data): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'data_size' => count($data),
            'has_warnings' => isset($data['warnings']) && !empty($data['warnings']),
            'warnings_count' => isset($data['warnings']) && is_array($data['warnings']) ? count($data['warnings']) : 0,
            'has_metadata' => isset($data['metadata']),
        ];
    }

    protected function extractQualityMetrics(array $data): array
    {
        $metrics = [];

        // Общие метрики качества
        if (isset($data['quality_metrics'])) {
            $metrics = array_merge($metrics, $data['quality_metrics']);
        }

        // Confidence score из разных мест
        $confidenceFields = ['confidence', 'analysis_confidence', 'overall_confidence'];

        foreach ($confidenceFields as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $metrics['confidence'] = (float) $data[$field];
                break;
            }
        }

        return $metrics;
    }

    protected function extractRiskMetrics(array $data): array
    {
        $risks = [];

        // Поиск риск-уровней в различных структурах
        if (isset($data['risk_level'])) {
            $risks['overall_risk'] = $data['risk_level'];
        }

        // Подсчет рисков по уровням
        $riskLevels = ['critical', 'high', 'medium', 'low'];
        $riskCounts = array_fill_keys($riskLevels, 0);

        $this->countRiskLevels($data, $riskCounts);

        return array_merge($risks, ['risk_distribution' => $riskCounts]);
    }

    private function countRiskLevels(array $data, array &$riskCounts): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'risk_level' && is_string($value) && isset($riskCounts[$value])) {
                ++$riskCounts[$value];
            } elseif ($key === 'severity' && is_string($value) && isset($riskCounts[$value])) {
                ++$riskCounts[$value];
            } elseif (is_array($value)) {
                $this->countRiskLevels($value, $riskCounts);
            }
        }
    }
}
