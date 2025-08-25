<?php

declare(strict_types=1);

namespace App\Services\Prompt\Extractors;

final readonly class AnalysisMetadataExtractor extends BaseMetadataExtractor
{
    public function extract(array $data): array
    {
        $metadata = $this->extractCommonMetadata($data);

        // Общие метрики для анализа
        $metadata['quality_metrics'] = $this->extractQualityMetrics($data);
        $metadata['risk_metrics'] = $this->extractRiskMetrics($data);
        $metadata['analysis_type'] = $data['analysis_type'] ?? 'unknown';
        $metadata['confidence'] = $this->extractConfidence($data);

        // Специфичные метрики в зависимости от типа анализа
        $analysisType = $data['analysis_type'] ?? null;

        switch ($analysisType) {
            case 'contradiction':
                $metadata['contradiction_metrics'] = $this->extractContradictionMetrics($data);
                break;

            case 'ambiguity':
                $metadata['ambiguity_metrics'] = $this->extractAmbiguityMetrics($data);
                break;

            default:
                $metadata['general_metrics'] = $this->extractGeneralAnalysisMetrics($data);
                break;
        }

        return $metadata;
    }

    public function getSchemaType(): string
    {
        return 'analysis';
    }

    private function extractConfidence(array $data): ?float
    {
        $confidenceFields = [
            'confidence',
            'analysis_confidence',
            'overall_confidence',
            'methodology.confidence_level',
            'quality_indicators.analysis_confidence',
        ];

        foreach ($confidenceFields as $field) {
            $value = $this->getNestedValue($data, $field);

            if ($value !== null && is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function extractContradictionMetrics(array $data): array
    {
        $metrics = [];

        if (isset($data['contradictions_found']) && is_array($data['contradictions_found'])) {
            $contradictions = $data['contradictions_found'];
            $metrics['total_contradictions'] = count($contradictions);

            // Распределение по типам
            $typeDistribution = [];
            $severityDistribution = [];

            foreach ($contradictions as $contradiction) {
                $type = $contradiction['type'] ?? 'unknown';
                $severity = $contradiction['severity'] ?? 'unknown';

                $typeDistribution[$type] = ($typeDistribution[$type] ?? 0) + 1;
                $severityDistribution[$severity] = ($severityDistribution[$severity] ?? 0) + 1;
            }

            $metrics['type_distribution'] = $typeDistribution;
            $metrics['severity_distribution'] = $severityDistribution;
        }

        if (isset($data['analysis_summary'])) {
            $summary = $data['analysis_summary'];
            $metrics['consistency_score'] = $summary['overall_consistency_score'] ?? null;
            $metrics['total_from_summary'] = $summary['total_contradictions'] ?? null;
        }

        return $metrics;
    }

    private function extractAmbiguityMetrics(array $data): array
    {
        $metrics = [];

        if (isset($data['ambiguities_found']) && is_array($data['ambiguities_found'])) {
            $ambiguities = $data['ambiguities_found'];
            $metrics['total_ambiguities'] = count($ambiguities);

            // Распределение по типам и рискам
            $typeDistribution = [];
            $riskDistribution = [];

            foreach ($ambiguities as $ambiguity) {
                $type = $ambiguity['type'] ?? 'unknown';
                $risk = $ambiguity['risk_level'] ?? 'unknown';

                $typeDistribution[$type] = ($typeDistribution[$type] ?? 0) + 1;
                $riskDistribution[$risk] = ($riskDistribution[$risk] ?? 0) + 1;
            }

            $metrics['type_distribution'] = $typeDistribution;
            $metrics['risk_distribution'] = $riskDistribution;
        }

        if (isset($data['clarity_assessment'])) {
            $assessment = $data['clarity_assessment'];
            $metrics['clarity_score'] = $assessment['overall_clarity_score'] ?? null;
            $metrics['readability_metrics'] = $assessment['readability_metrics'] ?? [];
        }

        return $metrics;
    }

    private function extractGeneralAnalysisMetrics(array $data): array
    {
        $metrics = [];

        if (isset($data['result'])) {
            $result = $data['result'];
            $metrics['has_summary'] = !empty($result['summary'] ?? '');
            $metrics['has_details'] = !empty($result['details'] ?? []);
            $metrics['key_findings_count'] = isset($result['key_findings']) && is_array($result['key_findings'])
                ? count($result['key_findings'])
                : 0;
        }

        if (isset($data['recommendations']) && is_array($data['recommendations'])) {
            $recommendations = $data['recommendations'];
            $metrics['recommendations_count'] = count($recommendations);

            $priorityDistribution = [];

            foreach ($recommendations as $recommendation) {
                $priority = $recommendation['priority'] ?? 'unknown';
                $priorityDistribution[$priority] = ($priorityDistribution[$priority] ?? 0) + 1;
            }
            $metrics['priority_distribution'] = $priorityDistribution;
        }

        return $metrics;
    }

    private function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }
}
