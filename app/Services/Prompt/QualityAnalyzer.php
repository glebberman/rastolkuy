<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use JsonException;

final readonly class QualityAnalyzer
{
    public function analyze(string $response, ?array $schema = null): array
    {
        $metrics = [];

        $metrics['length_score'] = $this->analyzeLengthQuality($response);
        $metrics['structure_score'] = $this->analyzeStructureQuality($response);
        $metrics['language_score'] = $this->analyzeLanguageQuality($response);
        $metrics['completeness_score'] = $this->analyzeCompletenessQuality($response);

        if ($schema !== null) {
            $metrics['schema_compliance_score'] = $this->analyzeSchemaCompliance($response, $schema);
        }

        $metrics['readability_score'] = $this->analyzeReadability($response);
        $metrics['coherence_score'] = $this->analyzeCoherence($response);

        $metrics['overall_score'] = $this->calculateOverallScore($metrics);

        $metrics['analysis_metadata'] = [
            'analyzed_at' => now()->toISOString(),
            'response_length' => mb_strlen($response),
            'word_count' => str_word_count($response),
            'sentence_count' => $this->countSentences($response),
        ];

        return $metrics;
    }

    public function analyzeResponseQuality(string $response, string $expectedType = 'general'): array
    {
        $metrics = $this->analyze($response);

        $typeSpecificMetrics = match ($expectedType) {
            'translation' => $this->analyzeTranslationQuality($response),
            'contradiction' => $this->analyzeContradictionQuality($response),
            'ambiguity' => $this->analyzeAmbiguityQuality($response),
            default => [],
        };

        return array_merge($metrics, $typeSpecificMetrics);
    }

    private function analyzeLengthQuality(string $response): float
    {
        $length = mb_strlen($response);

        if ($length < 50) {
            return 0.2;
        }

        if ($length < 100) {
            return 0.5;
        }

        if ($length < 500) {
            return 0.8;
        }

        if ($length < 2000) {
            return 1.0;
        }

        return 0.9;
    }

    private function analyzeStructureQuality(string $response): float
    {
        $score = 0.0;

        if (preg_match('/^[А-ЯA-Z]/', $response)) {
            $score += 0.2;
        }

        if (preg_match('/[.!?]$/', $response)) {
            $score += 0.2;
        }

        if (preg_match('/\n\n|\n-|\n\d+\./', $response)) {
            $score += 0.3;
        }

        if (preg_match_all('/[.!?]/', $response) > 1) {
            $score += 0.2;
        }

        if (!preg_match('/\s{3,}/', $response)) {
            $score += 0.1;
        }

        return min($score, 1.0);
    }

    private function analyzeLanguageQuality(string $response): float
    {
        $score = 1.0;

        $russianChars = preg_match_all('/[а-яё]/ui', $response);
        $cleanedResponse = preg_replace('/\s+/', '', $response);
        $totalChars = mb_strlen($cleanedResponse ?? '');

        if ($totalChars > 0 && $russianChars / $totalChars < 0.5) {
            $score -= 0.3;
        }

        if (preg_match('/[а-я]{50,}/u', $response)) {
            $score -= 0.2;
        }

        if (preg_match('/(.)\1{4,}/', $response)) {
            $score -= 0.2;
        }

        if (preg_match('/^(.{1,20})\1+$/', $response)) {
            $score -= 0.4;
        }

        return max($score, 0.0);
    }

    private function analyzeCompletenessQuality(string $response): float
    {
        $incompleteSigns = [
            '/\.\.\.$/',
            '/\[продолжение\]/i',
            '/\[не завершено\]/i',
            '/^[^.!?]*$/',
        ];

        foreach ($incompleteSigns as $pattern) {
            if (preg_match($pattern, trim($response))) {
                return 0.3;
            }
        }

        if (mb_strlen($response) < 20) {
            return 0.4;
        }

        return 1.0;
    }

    private function analyzeSchemaCompliance(string $response, array $schema): float
    {
        if (empty($schema)) {
            return 1.0;
        }

        try {
            $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

            if ($decoded === null) {
                return 0.0;
            }

            return $this->validateAgainstSchema(is_array($decoded) ? $decoded : [], $schema);
        } catch (JsonException) {
            return 0.0;
        }
    }

    private function analyzeReadability(string $response): float
    {
        $sentences = $this->countSentences($response);
        $words = str_word_count($response);

        if ($sentences === 0) {
            return 0.0;
        }

        $avgWordsPerSentence = $words / $sentences;

        if ($avgWordsPerSentence <= 15) {
            return 1.0;
        }

        if ($avgWordsPerSentence <= 25) {
            return 0.8;
        }

        if ($avgWordsPerSentence <= 35) {
            return 0.6;
        }

        return 0.4;
    }

    private function analyzeCoherence(string $response): float
    {
        $score = 1.0;

        if (preg_match('/однако.*однако|но.*но|также.*также/ui', $response)) {
            $score -= 0.2;
        }

        $sentences = preg_split('/[.!?]+/', $response);
        $sentencesCount = is_array($sentences) ? count($sentences) : 0;

        if ($sentencesCount > 2) {
            $transitions = 0;
            $transitionWords = ['однако', 'тем не менее', 'кроме того', 'в то же время', 'следовательно'];

            foreach ($transitionWords as $word) {
                if (stripos($response, $word) !== false) {
                    ++$transitions;
                }
            }

            if ($transitions === 0 && is_array($sentences) && count($sentences) > 3) {
                $score -= 0.3;
            }
        }

        return max($score, 0.0);
    }

    private function analyzeTranslationQuality(string $response): array
    {
        return [
            'translation_clarity' => $this->measureTranslationClarity($response),
            'legal_terminology_preserved' => $this->checkLegalTerminologyPreservation($response),
            'simplification_quality' => $this->measureSimplificationQuality($response),
        ];
    }

    private function analyzeContradictionQuality(string $response): array
    {
        return [
            'contradiction_identification' => $this->measureContradictionIdentification($response),
            'evidence_quality' => $this->measureEvidenceQuality($response),
            'analysis_depth' => $this->measureAnalysisDepth($response),
        ];
    }

    private function analyzeAmbiguityQuality(string $response): array
    {
        return [
            'ambiguity_detection' => $this->measureAmbiguityDetection($response),
            'clarification_suggestions' => $this->measureClarificationSuggestions($response),
            'risk_assessment' => $this->measureRiskAssessment($response),
        ];
    }

    private function calculateOverallScore(array $metrics): float
    {
        $weights = [
            'length_score' => 0.10,
            'structure_score' => 0.15,
            'language_score' => 0.20,
            'completeness_score' => 0.20,
            'schema_compliance_score' => 0.15,
            'readability_score' => 0.10,
            'coherence_score' => 0.10,
        ];

        $totalScore = 0.0;
        $totalWeight = 0.0;

        foreach ($weights as $metric => $weight) {
            if (isset($metrics[$metric])) {
                $totalScore += $metrics[$metric] * $weight;
                $totalWeight += $weight;
            }
        }

        return $totalWeight > 0 ? round($totalScore / $totalWeight, 3) : 0.0;
    }

    private function countSentences(string $text): int
    {
        return max(1, preg_match_all('/[.!?]+/', $text));
    }

    private function validateAgainstSchema(array $data, array $schema): float
    {
        $required = $schema['required'] ?? [];
        $properties = $schema['properties'] ?? [];

        $score = 1.0;

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                $score -= 0.3;
            }
        }

        foreach ($properties as $field => $definition) {
            if (array_key_exists($field, $data)) {
                $type = $definition['type'] ?? 'string';
                $actualType = gettype($data[$field]);

                if (!$this->typesMatch($actualType, $type)) {
                    $score -= 0.2;
                }
            }
        }

        return max($score, 0.0);
    }

    private function typesMatch(string $actualType, string $expectedType): bool
    {
        $typeMapping = [
            'string' => 'string',
            'integer' => 'integer',
            'double' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
            'object' => 'object',
        ];

        return ($typeMapping[$actualType] ?? $actualType) === $expectedType;
    }

    private function measureTranslationClarity(string $response): float
    {
        $clarityMarkers = ['простыми словами', 'это означает', 'иными словами', 'проще говоря'];
        $score = 0.5;

        foreach ($clarityMarkers as $marker) {
            if (stripos($response, $marker) !== false) {
                $score += 0.1;
            }
        }

        return min($score, 1.0);
    }

    private function checkLegalTerminologyPreservation(string $response): float
    {
        $legalTerms = ['договор', 'обязательство', 'право', 'закон', 'статья', 'пункт'];
        $preservedTerms = 0;

        foreach ($legalTerms as $term) {
            if (stripos($response, $term) !== false) {
                ++$preservedTerms;
            }
        }

        return $preservedTerms > 0 ? min($preservedTerms / count($legalTerms), 1.0) : 0.5;
    }

    private function measureSimplificationQuality(string $response): float
    {
        $avgWordLength = 0;
        $words = str_word_count($response, 1);

        if (!empty($words)) {
            $totalLength = array_sum(array_map('mb_strlen', $words));
            $avgWordLength = $totalLength / count($words);
        }

        if ($avgWordLength <= 5) {
            return 1.0;
        }

        if ($avgWordLength <= 7) {
            return 0.8;
        }

        return 0.6;
    }

    private function measureContradictionIdentification(string $response): float
    {
        $contradictionMarkers = ['противоречие', 'несоответствие', 'конфликт', 'не согласуется'];
        $score = 0.3;

        foreach ($contradictionMarkers as $marker) {
            if (stripos($response, $marker) !== false) {
                $score += 0.2;
            }
        }

        return min($score, 1.0);
    }

    private function measureEvidenceQuality(string $response): float
    {
        $evidenceMarkers = ['в пункте', 'согласно статье', 'как указано в', 'ссылаясь на'];
        $foundEvidence = 0;

        foreach ($evidenceMarkers as $marker) {
            if (stripos($response, $marker) !== false) {
                ++$foundEvidence;
            }
        }

        return min($foundEvidence * 0.3, 1.0);
    }

    private function measureAnalysisDepth(string $response): float
    {
        $depthMarkers = ['анализ показывает', 'следует отметить', 'важно учитывать', 'в результате'];
        $score = 0.4;

        foreach ($depthMarkers as $marker) {
            if (stripos($response, $marker) !== false) {
                $score += 0.15;
            }
        }

        return min($score, 1.0);
    }

    private function measureAmbiguityDetection(string $response): float
    {
        $ambiguityMarkers = ['неясно', 'двусмысленно', 'может пониматься', 'неопределенность'];
        $score = 0.3;

        foreach ($ambiguityMarkers as $marker) {
            if (stripos($response, $marker) !== false) {
                $score += 0.2;
            }
        }

        return min($score, 1.0);
    }

    private function measureClarificationSuggestions(string $response): float
    {
        $suggestionMarkers = ['рекомендуется', 'следует уточнить', 'необходимо дополнить', 'предлагается'];
        $score = 0.2;

        foreach ($suggestionMarkers as $marker) {
            if (stripos($response, $marker) !== false) {
                $score += 0.2;
            }
        }

        return min($score, 1.0);
    }

    private function measureRiskAssessment(string $response): float
    {
        $riskMarkers = ['риск', 'опасность', 'может привести', 'потенциальные последствия'];
        $score = 0.3;

        foreach ($riskMarkers as $marker) {
            if (stripos($response, $marker) !== false) {
                $score += 0.2;
            }
        }

        return min($score, 1.0);
    }
}
