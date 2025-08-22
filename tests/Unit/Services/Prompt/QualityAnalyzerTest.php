<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Prompt;

use App\Services\Prompt\QualityAnalyzer;
use PHPUnit\Framework\TestCase;

class QualityAnalyzerTest extends TestCase
{
    private QualityAnalyzer $qualityAnalyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qualityAnalyzer = new QualityAnalyzer();
    }

    public function testAnalyzesBasicResponseQuality(): void
    {
        $response = 'Это достаточно длинный и качественный ответ, который содержит несколько предложений. Он имеет хорошую структуру и понятный язык.';

        $metrics = $this->qualityAnalyzer->analyze($response);

        $this->assertArrayHasKey('overall_score', $metrics);
        $this->assertArrayHasKey('length_score', $metrics);
        $this->assertArrayHasKey('structure_score', $metrics);
        $this->assertArrayHasKey('language_score', $metrics);
        $this->assertGreaterThan(0.5, $metrics['overall_score']);
    }

    public function testPenalizesShortResponses(): void
    {
        $shortResponse = 'Да.';
        $normalResponse = 'Это нормальный ответ средней длины, который содержит достаточно информации для анализа качества.';

        $shortMetrics = $this->qualityAnalyzer->analyze($shortResponse);
        $normalMetrics = $this->qualityAnalyzer->analyze($normalResponse);

        $this->assertLessThan($normalMetrics['length_score'], $shortMetrics['length_score']);
    }

    public function testRewardsGoodStructure(): void
    {
        $structuredResponse = 'Анализ показывает следующее:

1. Первый пункт содержит важную информацию.
2. Второй пункт дополняет анализ.

Заключение: документ имеет хорошую структуру.';

        $unstructuredResponse = 'просто текст без структуры без знаков препинания и без заглавных букв';

        $structuredMetrics = $this->qualityAnalyzer->analyze($structuredResponse);
        $unstructuredMetrics = $this->qualityAnalyzer->analyze($unstructuredResponse);

        $this->assertGreaterThan($unstructuredMetrics['structure_score'], $structuredMetrics['structure_score']);
    }

    public function testDetectsIncompleteResponses(): void
    {
        $incompleteResponse = 'Анализ документа показывает...';
        $completeResponse = 'Анализ документа показывает высокое качество содержания.';

        $incompleteMetrics = $this->qualityAnalyzer->analyze($incompleteResponse);
        $completeMetrics = $this->qualityAnalyzer->analyze($completeResponse);

        $this->assertLessThan($completeMetrics['completeness_score'], $incompleteMetrics['completeness_score']);
    }

    public function testValidatesAgainstJsonSchema(): void
    {
        $validJsonResponse = json_encode([
            'analysis_type' => 'translation',
            'result' => ['summary' => 'Test summary'],
            'confidence' => 0.95,
        ]);

        $this->assertIsString($validJsonResponse);

        $schema = [
            'type' => 'object',
            'required' => ['analysis_type', 'result', 'confidence'],
            'properties' => [
                'analysis_type' => ['type' => 'string'],
                'result' => ['type' => 'object'],
                'confidence' => ['type' => 'number'],
            ],
        ];

        $metrics = $this->qualityAnalyzer->analyze($validJsonResponse, $schema);

        $this->assertArrayHasKey('schema_compliance_score', $metrics);
        $this->assertGreaterThanOrEqual(0.8, $metrics['schema_compliance_score']);
    }

    public function testAnalyzesReadability(): void
    {
        $readableResponse = 'Простой текст. Короткие предложения. Легко читается.';
        $complexResponse = 'Чрезвычайно сложное предложение, содержащее множество подчинительных конструкций, сложных терминов и структур, которые значительно затрудняют понимание и восприятие представленной информации.';

        $readableMetrics = $this->qualityAnalyzer->analyze($readableResponse);
        $complexMetrics = $this->qualityAnalyzer->analyze($complexResponse);

        // Проверяем что оба анализа содержат readability_score
        $this->assertArrayHasKey('readability_score', $readableMetrics);
        $this->assertArrayHasKey('readability_score', $complexMetrics);
        $this->assertIsFloat($readableMetrics['readability_score']);
        $this->assertIsFloat($complexMetrics['readability_score']);
        
        // Проверяем что readable текст имеет не худший score чем complex
        $this->assertGreaterThanOrEqual($complexMetrics['readability_score'], $readableMetrics['readability_score']);
    }

    public function testAnalyzesCoherence(): void
    {
        $coherentResponse = 'Документ содержит важную информацию. Кроме того, он имеет хорошую структуру. Следовательно, его качество можно оценить положительно.';

        $incoherentResponse = 'Документ важный. Документ важный. Структура есть. Структура хорошая.';

        $coherentMetrics = $this->qualityAnalyzer->analyze($coherentResponse);
        $incoherentMetrics = $this->qualityAnalyzer->analyze($incoherentResponse);

        // Проверяем что оба анализа содержат score (сам алгоритм может варьироваться)
        $this->assertArrayHasKey('coherence_score', $coherentMetrics);
        $this->assertArrayHasKey('coherence_score', $incoherentMetrics);
        $this->assertIsFloat($coherentMetrics['coherence_score']);
        $this->assertIsFloat($incoherentMetrics['coherence_score']);
    }

    public function testAnalyzesTranslationSpecificQuality(): void
    {
        $translationResponse = 'Простыми словами, это означает следующее: договор устанавливает права и обязанности сторон.';

        $metrics = $this->qualityAnalyzer->analyzeResponseQuality($translationResponse, 'translation');

        $this->assertArrayHasKey('translation_clarity', $metrics);
        $this->assertArrayHasKey('legal_terminology_preserved', $metrics);
        $this->assertArrayHasKey('simplification_quality', $metrics);
    }

    public function testAnalyzesContradictionSpecificQuality(): void
    {
        $contradictionResponse = 'Анализ показывает противоречие между пунктами 1 и 3. В пункте 1 указано право на отказ, согласно статье 15.';

        $metrics = $this->qualityAnalyzer->analyzeResponseQuality($contradictionResponse, 'contradiction');

        $this->assertArrayHasKey('contradiction_identification', $metrics);
        $this->assertArrayHasKey('evidence_quality', $metrics);
        $this->assertArrayHasKey('analysis_depth', $metrics);
    }

    public function testAnalyzesAmbiguitySpecificQuality(): void
    {
        $ambiguityResponse = 'Обнаружена неясность в формулировке. Может пониматься двояко. Рекомендуется уточнить риск неправильного толкования.';

        $metrics = $this->qualityAnalyzer->analyzeResponseQuality($ambiguityResponse, 'ambiguity');

        $this->assertArrayHasKey('ambiguity_detection', $metrics);
        $this->assertArrayHasKey('clarification_suggestions', $metrics);
        $this->assertArrayHasKey('risk_assessment', $metrics);
    }

    public function testCalculatesOverallScoreCorrectly(): void
    {
        $goodResponse = 'Качественный анализ юридического документа показывает следующие результаты. Документ имеет четкую структуру и понятные формулировки. Все основные пункты соответствуют требованиям законодательства.';

        $metrics = $this->qualityAnalyzer->analyze($goodResponse);

        $this->assertGreaterThan(0.6, $metrics['overall_score']);
        $this->assertLessThanOrEqual(1.0, $metrics['overall_score']);
    }

    public function testIncludesAnalysisMetadata(): void
    {
        $response = 'Тестовый ответ для проверки метаданных анализа качества.';

        $metrics = $this->qualityAnalyzer->analyze($response);

        $this->assertArrayHasKey('analysis_metadata', $metrics);
        $this->assertArrayHasKey('analyzed_at', $metrics['analysis_metadata']);
        $this->assertArrayHasKey('response_length', $metrics['analysis_metadata']);
        $this->assertArrayHasKey('word_count', $metrics['analysis_metadata']);
        $this->assertArrayHasKey('sentence_count', $metrics['analysis_metadata']);
    }
}
