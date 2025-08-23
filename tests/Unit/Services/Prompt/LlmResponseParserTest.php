<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Prompt;

use App\Services\Prompt\DTOs\LlmParsingRequest;
use App\Services\Prompt\LlmResponseParser;
use App\Services\Prompt\MetadataExtractorManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class LlmResponseParserTest extends TestCase
{
    private LlmResponseParser $parser;

    private MetadataExtractorManager $metadataExtractorManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadataExtractorManager = new MetadataExtractorManager();
        $this->parser = new LlmResponseParser($this->metadataExtractorManager);
    }

    public function testCanParseValidTranslationResponse(): void
    {
        $validResponse = $this->safeJsonEncode([
            'simplified_text' => 'Простой текст договора',
            'translation_quality' => [
                'clarity_score' => 0.9,
                'completeness_score' => 0.8,
                'readability_level' => 'beginner',
            ],
            'legal_terms_preserved' => [
                [
                    'term' => 'договор',
                    'explanation' => 'соглашение между сторонами',
                ],
            ],
            'section_translations' => [
                [
                    'anchor' => 'section_1',
                    'section_title' => 'Предмет договора',
                    'translated_content' => 'О чем этот договор',
                ],
            ],
        ]);

        $request = LlmParsingRequest::forTranslation($validResponse, ['section_1']);
        $result = $this->parser->parse($request);

        $this->assertTrue($result->isSuccessful());
        $this->assertEmpty($result->errors);
        $this->assertEquals('translation', $result->schemaType);
        $this->assertEquals(1, $result->getValidAnchorCount());
    }

    public function testCanParseResponseWithMarkdownCodeBlocks(): void
    {
        $responseWithMarkdown = "```json\n" . $this->safeJsonEncode([
            'simplified_text' => 'Test text',
            'translation_quality' => ['clarity_score' => 0.9, 'completeness_score' => 0.8],
            'legal_terms_preserved' => [],
            'section_translations' => [],
        ]) . "\n```";

        $request = LlmParsingRequest::forGeneral($responseWithMarkdown);
        $result = $this->parser->parse($request);

        $this->assertTrue($result->isValid);
        $this->assertNotEmpty($result->parsedData);
        $this->assertEquals('Test text', $result->parsedData['simplified_text']);
    }

    public function testHandlesInvalidJsonGracefully(): void
    {
        $invalidJson = '{"invalid": json, "missing": quotes}';

        $request = LlmParsingRequest::forGeneral($invalidJson);
        $result = $this->parser->parse($request);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('JSON', $result->errors[0]);
    }

    public function testCanRepairCommonJsonIssues(): void
    {
        $brokenJson = '{"text": "hello", "score": 0.9'; // Missing closing brace

        $request = LlmParsingRequest::forGeneral($brokenJson);
        $result = $this->parser->parse($request);

        $this->assertTrue($result->isValid);
        $this->assertEquals('hello', $result->parsedData['text']);
        $this->assertEquals(0.9, $result->parsedData['score']);
    }

    public function testValidatesAnchorsAgainstOriginal(): void
    {
        $responseWithInvalidAnchor = $this->safeJsonEncode([
            'section_translations' => [
                [
                    'anchor' => 'wrong_section',
                    'section_title' => 'Title',
                    'translated_content' => 'Content',
                ],
            ],
        ]);

        $request = LlmParsingRequest::forTranslation($responseWithInvalidAnchor, ['section_1']);
        $result = $this->parser->parse($request);

        $this->assertEquals(0, $result->getValidAnchorCount());
        $this->assertEquals(2, $result->getInvalidAnchorCount()); // 1 missing original + 1 unexpected in response
    }

    public function testFallbackParsingWorksWithoutFacades(): void
    {
        // Тестируем основную логику fallback без использования Laravel фасадов
        $responseWithSchemaIssues = '{"not_json": true, "missing_all_required_fields": "yes"}';

        $schema = [
            'required' => ['simplified_text', 'translation_quality', 'section_translations'],
        ];

        $request = new LlmParsingRequest(
            rawResponse: $responseWithSchemaIssues,
            expectedSchema: $schema,
            schemaType: 'translation',
            originalAnchors: ['section_1'],
            validationRules: ['anchors_required'],
            strictValidation: true,
        );

        // Основной парсинг должен упасть из-за отсутствия обязательных полей
        $result = $this->parser->parse($request);
        $this->assertFalse($result->isSuccessful());
        $this->assertNotEmpty($result->errors);
    }

    public function testExtractsMetadataCorrectly(): void
    {
        $translationResponse = $this->safeJsonEncode([
            'simplified_text' => 'Simple text',
            'translation_quality' => [
                'clarity_score' => 0.9,
                'completeness_score' => 0.85,
            ],
            'section_translations' => [
                [
                    'anchor' => 'section_1',
                    'section_title' => 'Title',
                    'translated_content' => 'Content',
                ],
            ],
        ]);

        $request = LlmParsingRequest::forTranslation($translationResponse, ['section_1']);
        $result = $this->parser->parse($request);

        $this->assertArrayHasKey('translation_quality', $result->metadata);
        $this->assertArrayHasKey('sections_count', $result->metadata);
        $this->assertEquals(0.875, $result->metadata['translation_quality']['overall_score']); // Average of clarity and completeness
    }

    public function testHandlesDifferentResponseTypes(): void
    {
        // Test contradiction analysis response
        $contradictionResponse = $this->safeJsonEncode([
            'analysis_type' => 'contradiction',
            'contradictions_found' => [
                [
                    'id' => 'c1',
                    'type' => 'logical',
                    'description' => 'Test contradiction',
                    'severity' => 'high',
                    'locations' => [
                        [
                            'section' => 'Section 1',
                            'text_fragment' => 'Fragment',
                            'anchor' => 'section_1',
                        ],
                    ],
                ],
            ],
            'analysis_summary' => [
                'total_contradictions' => 1,
                'severity_distribution' => ['high' => 1],
            ],
        ]);

        $request = LlmParsingRequest::forAnalysis($contradictionResponse, 'contradiction');
        $result = $this->parser->parse($request);

        $this->assertTrue($result->isValid);
        $this->assertEquals('contradiction', $result->schemaType);
        $this->assertArrayHasKey('contradiction_metrics', $result->metadata);
    }

    public function testNormalizesDataCorrectly(): void
    {
        $responseWithUnnormalizedData = $this->safeJsonEncode([
            'text_with_spaces' => '  Text with   extra   spaces  ',
            'string_number' => '42',
            'string_float' => '3.14',
        ]);

        $request = LlmParsingRequest::forGeneral($responseWithUnnormalizedData);
        $result = $this->parser->parse($request);

        $this->assertEquals('Text with extra spaces', $result->parsedData['text_with_spaces']);
        $this->assertEquals(42, $result->parsedData['string_number']);
        $this->assertEquals(3.14, $result->parsedData['string_float']);
    }

    public function testHandlesPartialResponses(): void
    {
        $partialResponse = '{"simplified_text": "Some text", "translation_quality": {'; // Incomplete JSON

        $request = LlmParsingRequest::forGeneral($partialResponse);
        $result = $this->parser->parse($request);

        // Парсер должен попытаться восстановить JSON
        $this->assertTrue($result->isValid); // Восстановление должно сработать
        $this->assertEquals('Some text', $result->parsedData['simplified_text']);
        $this->assertArrayHasKey('translation_quality', $result->parsedData); // Пустой объект должен быть добавлен
    }

    /**
     * @dataProvider validationRulesProvider
     */
    public function testAppliesValidationRules(array $data, array $rules, bool $shouldHaveErrors): void
    {
        $rawResponse = json_encode($data);

        if ($rawResponse === false) {
            throw new RuntimeException('JSON encoding failed');
        }

        $request = new LlmParsingRequest(
            rawResponse: $rawResponse,
            validationRules: $rules,
            strictValidation: true,
        );

        $result = $this->parser->parse($request);

        if ($shouldHaveErrors) {
            $this->assertNotEmpty($result->errors);
        } else {
            $this->assertEmpty($result->errors);
        }
    }

    public static function validationRulesProvider(): array
    {
        return [
            'anchors_required_with_anchors' => [
                ['section_translations' => [['anchor' => 'test']]],
                ['anchors_required'],
                false,
            ],
            'anchors_required_without_anchors' => [
                ['some_field' => 'value'],
                ['anchors_required'],
                true,
            ],
            'confidence_required_with_confidence' => [
                ['confidence' => 0.8],
                ['confidence_required'],
                false,
            ],
            'confidence_required_without_confidence' => [
                ['some_field' => 'value'],
                ['confidence_required'],
                false, // This should be a warning, not error
            ],
        ];
    }

    private function safeJsonEncode(array $data): string
    {
        $result = json_encode($data);

        if ($result === false) {
            throw new RuntimeException('JSON encoding failed');
        }

        return $result;
    }
}
