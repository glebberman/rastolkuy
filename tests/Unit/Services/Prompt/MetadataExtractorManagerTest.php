<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Prompt;

use App\Services\Prompt\MetadataExtractorManager;
use PHPUnit\Framework\TestCase;

class MetadataExtractorManagerTest extends TestCase
{
    private MetadataExtractorManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new MetadataExtractorManager();
    }

    public function testCanExtractTranslationMetadata(): void
    {
        $translationData = [
            'simplified_text' => 'Simple text',
            'translation_quality' => [
                'clarity_score' => 0.9,
                'completeness_score' => 0.8,
                'readability_level' => 'beginner',
            ],
            'legal_terms_preserved' => [
                [
                    'term' => 'договор',
                    'explanation' => 'соглашение',
                    'context' => 'в рамках данного договора',
                ],
                [
                    'term' => 'обязательство',
                    'explanation' => 'что должен делать',
                ],
            ],
            'section_translations' => [
                [
                    'anchor' => 'section_1',
                    'section_title' => 'Title 1',
                    'translated_content' => 'Content 1',
                    'summary' => 'Summary 1',
                ],
                [
                    'anchor' => 'section_2',
                    'section_title' => 'Title 2',
                    'translated_content' => 'Content 2',
                ],
            ],
            'key_concepts' => [
                [
                    'concept' => 'Ответственность',
                    'description' => 'Кто за что отвечает',
                    'importance' => 'high',
                ],
                [
                    'concept' => 'Сроки',
                    'description' => 'Когда что должно быть сделано',
                    'importance' => 'medium',
                ],
            ],
            'metadata' => [
                'original_length' => 1000,
                'simplified_length' => 500,
                'complexity_reduction' => 0.7,
            ],
        ];

        $metadata = $this->manager->extractMetadata($translationData, 'translation');

        $this->assertArrayHasKey('translation_quality', $metadata);
        $this->assertEquals(0.85, round($metadata['translation_quality']['overall_score'], 2)); // Average of 0.9 and 0.8

        $this->assertArrayHasKey('sections_count', $metadata);
        $this->assertEquals(2, $metadata['sections_count']['total_sections']);
        $this->assertEquals(1, $metadata['sections_count']['sections_with_summary']);

        $this->assertArrayHasKey('terms_preserved', $metadata);
        $this->assertEquals(2, $metadata['terms_preserved']['total_terms']);
        $this->assertEquals(2, $metadata['terms_preserved']['terms_with_explanation']);
        $this->assertEquals(1, $metadata['terms_preserved']['terms_with_context']);

        $this->assertArrayHasKey('key_concepts', $metadata);
        $this->assertEquals(2, $metadata['key_concepts']['total_concepts']);
        $this->assertEquals(['high' => 1, 'medium' => 1, 'low' => 0], $metadata['key_concepts']['importance_distribution']);

        $this->assertArrayHasKey('complexity_metrics', $metadata);
        $this->assertEquals(0.5, $metadata['complexity_metrics']['compression_ratio']);
    }

    public function testCanExtractContradictionMetadata(): void
    {
        $contradictionData = [
            'analysis_type' => 'contradiction',
            'contradictions_found' => [
                [
                    'id' => 'c1',
                    'type' => 'logical',
                    'severity' => 'high',
                    'description' => 'Test contradiction 1',
                ],
                [
                    'id' => 'c2',
                    'type' => 'legal',
                    'severity' => 'medium',
                    'description' => 'Test contradiction 2',
                ],
                [
                    'id' => 'c3',
                    'type' => 'logical',
                    'severity' => 'low',
                    'description' => 'Test contradiction 3',
                ],
            ],
            'analysis_summary' => [
                'total_contradictions' => 3,
                'overall_consistency_score' => 0.6,
            ],
            'confidence' => 0.85,
        ];

        $metadata = $this->manager->extractMetadata($contradictionData, 'contradiction');

        $this->assertArrayHasKey('contradiction_metrics', $metadata);
        $this->assertEquals(3, $metadata['contradiction_metrics']['total_contradictions']);
        $this->assertEquals(0.6, $metadata['contradiction_metrics']['consistency_score']);

        $expectedTypeDistribution = ['logical' => 2, 'legal' => 1];
        $this->assertEquals($expectedTypeDistribution, $metadata['contradiction_metrics']['type_distribution']);

        $expectedSeverityDistribution = ['high' => 1, 'medium' => 1, 'low' => 1];
        $this->assertEquals($expectedSeverityDistribution, $metadata['contradiction_metrics']['severity_distribution']);

        $this->assertEquals(0.85, $metadata['confidence']);
    }

    public function testCanExtractAmbiguityMetadata(): void
    {
        $ambiguityData = [
            'analysis_type' => 'ambiguity',
            'ambiguities_found' => [
                [
                    'type' => 'semantic',
                    'risk_level' => 'critical',
                ],
                [
                    'type' => 'syntactic',
                    'risk_level' => 'high',
                ],
            ],
            'clarity_assessment' => [
                'overall_clarity_score' => 0.7,
                'readability_metrics' => [
                    'average_sentence_length' => 25.5,
                    'complex_terms_count' => 15,
                ],
            ],
            'quality_indicators' => [
                'analysis_confidence' => 0.9,
            ],
        ];

        $metadata = $this->manager->extractMetadata($ambiguityData, 'ambiguity');

        $this->assertArrayHasKey('ambiguity_metrics', $metadata);
        $this->assertEquals(2, $metadata['ambiguity_metrics']['total_ambiguities']);
        $this->assertEquals(0.7, $metadata['ambiguity_metrics']['clarity_score']);

        $expectedTypeDistribution = ['semantic' => 1, 'syntactic' => 1];
        $this->assertEquals($expectedTypeDistribution, $metadata['ambiguity_metrics']['type_distribution']);

        $expectedRiskDistribution = ['critical' => 1, 'high' => 1];
        $this->assertEquals($expectedRiskDistribution, $metadata['ambiguity_metrics']['risk_distribution']);

        $this->assertEquals(0.9, $metadata['confidence']);
    }

    public function testCanExtractGeneralAnalysisMetadata(): void
    {
        $generalData = [
            'analysis_type' => 'general',
            'result' => [
                'summary' => 'General analysis summary',
                'details' => ['detail1', 'detail2'],
                'key_findings' => ['finding1', 'finding2', 'finding3'],
            ],
            'recommendations' => [
                [
                    'action' => 'Review section 1',
                    'priority' => 'high',
                ],
                [
                    'action' => 'Update terms',
                    'priority' => 'medium',
                ],
            ],
            'confidence' => 0.8,
        ];

        $metadata = $this->manager->extractMetadata($generalData, 'general');

        $this->assertArrayHasKey('general_metrics', $metadata);
        $this->assertTrue($metadata['general_metrics']['has_summary']);
        $this->assertTrue($metadata['general_metrics']['has_details']);
        $this->assertEquals(3, $metadata['general_metrics']['key_findings_count']);
        $this->assertEquals(2, $metadata['general_metrics']['recommendations_count']);

        $expectedPriorityDistribution = ['high' => 1, 'medium' => 1];
        $this->assertEquals($expectedPriorityDistribution, $metadata['general_metrics']['priority_distribution']);
    }

    public function testAutoDetectsSchemaType(): void
    {
        $translationData = [
            'section_translations' => [
                ['anchor' => 'test', 'content' => 'test'],
            ],
        ];

        $contradictionData = [
            'analysis_type' => 'contradiction',
            'contradictions_found' => [
                ['id' => 'c1', 'type' => 'logical', 'severity' => 'high'],
            ],
        ];

        $ambiguityData = [
            'analysis_type' => 'ambiguity',
            'ambiguities_found' => [
                ['type' => 'semantic', 'risk_level' => 'high'],
            ],
        ];

        // Test auto-detection without explicit schema type
        $translationMetadata = $this->manager->extractMetadata($translationData);
        $contradictionMetadata = $this->manager->extractMetadata($contradictionData);
        $ambiguityMetadata = $this->manager->extractMetadata($ambiguityData);

        $this->assertArrayHasKey('translation_quality', $translationMetadata);
        $this->assertArrayHasKey('contradiction_metrics', $contradictionMetadata);
        $this->assertArrayHasKey('ambiguity_metrics', $ambiguityMetadata);
    }

    public function testHandlesEmptyData(): void
    {
        $emptyData = [];

        $metadata = $this->manager->extractMetadata($emptyData, 'translation');

        $this->assertArrayHasKey('timestamp', $metadata);
        $this->assertEquals(0, $metadata['data_size']);
        $this->assertFalse($metadata['has_warnings']);
        $this->assertEquals(0, $metadata['warnings_count']);
    }

    public function testGetsSupportedSchemaTypes(): void
    {
        $supportedTypes = $this->manager->getSupportedSchemaTypes();

        $this->assertContains('translation', $supportedTypes);
        $this->assertContains('contradiction', $supportedTypes);
        $this->assertContains('ambiguity', $supportedTypes);
        $this->assertContains('general', $supportedTypes);
        $this->assertContains('analysis', $supportedTypes);
    }

    public function testHasExtractor(): void
    {
        $this->assertTrue($this->manager->hasExtractor('translation'));
        $this->assertTrue($this->manager->hasExtractor('contradiction'));
        $this->assertTrue($this->manager->hasExtractor('ambiguity'));
        $this->assertFalse($this->manager->hasExtractor('unsupported_type'));
    }
}
