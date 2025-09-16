<?php

declare(strict_types=1);

namespace Tests\Unit\Services\LLM;

use App\Services\LLM\CostCalculator;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CostCalculatorTest extends TestCase
{
    private CostCalculator $costCalculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->costCalculator = new CostCalculator();

        // Set test configuration for task-specific multipliers
        Config::set('credits.output_token_multipliers', [
            'translation' => [
                'content_multiplier' => 0.5,
                'json_overhead' => 0.3,
                'summary_base_tokens' => 150,
                'min_multiplier' => 0.4,
                'max_multiplier' => 1.0,
            ],
            'analysis' => [
                'content_multiplier' => 0.3,
                'json_overhead' => 0.2,
                'summary_base_tokens' => 100,
                'min_multiplier' => 0.25,
                'max_multiplier' => 0.6,
            ],
            'ambiguity' => [
                'content_multiplier' => 0.4,
                'json_overhead' => 0.25,
                'summary_base_tokens' => 120,
                'min_multiplier' => 0.3,
                'max_multiplier' => 0.8,
            ],
        ]);
    }

    public function testCalculateCost(): void
    {
        $cost = $this->costCalculator->calculateCost(1000, 1200, 'claude-3-5-sonnet-20241022');

        // Expected: (1000/1_000_000 * 3.00) + (1200/1_000_000 * 15.00) = 0.003 + 0.018 = 0.021
        $this->assertEquals(0.021, $cost);
    }

    public function testEstimateOutputTokensByTaskTypeTranslation(): void
    {
        $inputTokens = 2000;
        $structureAnalysis = ['sections_count' => 5];

        $outputTokens = $this->costCalculator->estimateOutputTokensByTaskType(
            $inputTokens,
            'translation',
            $structureAnalysis,
        );

        // Expected calculation:
        // Content: 2000 * 0.5 = 1000
        // JSON: 2000 * 0.3 * 1.1 (complexity) = 660
        // Summary: 150
        // Total: 1000 + 660 + 150 = 1810
        // Multiplier: 1810/2000 = 0.905 (within limits 0.4-1.0)
        $expectedTokens = (int) round(2000 * 0.905);

        $this->assertEquals($expectedTokens, $outputTokens);
    }

    public function testEstimateOutputTokensByTaskTypeAnalysis(): void
    {
        $inputTokens = 1500;
        $structureAnalysis = ['sections_count' => 3];

        $outputTokens = $this->costCalculator->estimateOutputTokensByTaskType(
            $inputTokens,
            'analysis',
            $structureAnalysis,
        );

        // Expected calculation:
        // Content: 1500 * 0.3 = 450
        // JSON: 1500 * 0.2 * 1.06 = 318
        // Summary: 100
        // Total: 450 + 318 + 100 = 868
        // Multiplier: 868/1500 = 0.578667 (within limits 0.25-0.6)
        $expectedTokens = (int) round(1500 * (868.0 / 1500));

        $this->assertEquals($expectedTokens, $outputTokens);
    }

    public function testEstimateOutputTokensByTaskTypeWithMinLimit(): void
    {
        $inputTokens = 100; // Smaller input to force minimum limit
        $structureAnalysis = ['sections_count' => 1];

        $outputTokens = $this->costCalculator->estimateOutputTokensByTaskType(
            $inputTokens,
            'analysis',
            $structureAnalysis,
        );

        // Expected calculation with small input:
        // Content: 100 * 0.3 = 30
        // JSON: 100 * 0.2 * 1.02 = 20.4
        // Summary: 100
        // Total: 30 + 20.4 + 100 = 150.4
        // Multiplier: 150.4/100 = 1.504 (exceeds max 0.6, so limited to 0.6)
        $expectedTokens = (int) round(100 * 0.6); // max_multiplier for analysis

        $this->assertEquals($expectedTokens, $outputTokens);
    }

    public function testEstimateOutputTokensByTaskTypeWithMaxLimit(): void
    {
        $inputTokens = 1000;
        $structureAnalysis = ['sections_count' => 50]; // Very high sections count

        $outputTokens = $this->costCalculator->estimateOutputTokensByTaskType(
            $inputTokens,
            'translation',
            $structureAnalysis,
        );

        // Should apply maximum multiplier when calculated value is too high
        $expectedTokens = (int) round(1000 * 1.0); // max_multiplier for translation

        $this->assertEquals($expectedTokens, $outputTokens);
    }

    public function testEstimateOutputTokensByTaskTypeFallbackToTranslation(): void
    {
        $inputTokens = 1000;
        $structureAnalysis = ['sections_count' => 5];

        $outputTokens = $this->costCalculator->estimateOutputTokensByTaskType(
            $inputTokens,
            'unknown_task_type',
            $structureAnalysis,
        );

        // Should fallback to translation task configuration
        $this->assertGreaterThan(0, $outputTokens);
        $this->assertLessThanOrEqual(1000, $outputTokens); // Should be <= input for translation
    }

    public function testEstimateTokens(): void
    {
        $text = str_repeat('word', 1000); // 4000 characters exactly
        $tokens = $this->costCalculator->estimateTokens($text);

        // Expected: 4000 / 4 = 1000 tokens
        $this->assertEquals(1000, $tokens);
    }

    public function testEstimateTokensFromFileSize(): void
    {
        $fileSizeBytes = 8000;
        $tokens = $this->costCalculator->estimateTokensFromFileSize($fileSizeBytes);

        // Expected: 8000 / 4 = 2000 tokens
        $this->assertEquals(2000, $tokens);
    }

    public function testGetPricingInfo(): void
    {
        $pricingInfo = $this->costCalculator->getPricingInfo('claude-3-5-sonnet-20241022');

        $this->assertTrue($pricingInfo['found']);
        $this->assertEquals('claude-3-5-sonnet-20241022', $pricingInfo['model']);
        $this->assertEquals(3.0, $pricingInfo['input_per_million']);
        $this->assertEquals(15.0, $pricingInfo['output_per_million']);
    }

    public function testGetPricingInfoNotFound(): void
    {
        $pricingInfo = $this->costCalculator->getPricingInfo('unknown-model');

        $this->assertFalse($pricingInfo['found']);
        $this->assertEquals('unknown-model', $pricingInfo['model']);
        $this->assertNull($pricingInfo['input_per_million']);
        $this->assertNull($pricingInfo['output_per_million']);
    }
}
