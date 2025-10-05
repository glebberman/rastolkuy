<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Export;

use App\Services\Export\ContentProcessor;
use Tests\TestCase;

class ContentProcessorBenchmarkTest extends TestCase
{
    private ContentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = app(ContentProcessor::class);
    }

    public function testBenchmarkSmallDocument(): void
    {
        $content = $this->generateTestContent(5, 100); // 5 sections, 100 words each

        $metrics = $this->measurePerformance(fn() => $this->processor->parseContent($content), 100);

        $this->assertLessThan(0.01, $metrics['avg_time'], 'Small document should parse in <10ms on average');
        $this->assertLessThan(0.05, $metrics['max_time'], 'Small document max time should be <50ms');
        $this->assertGreaterThan(0.000001, $metrics['min_time'], 'Min time should be measurable');
    }

    public function testBenchmarkMediumDocument(): void
    {
        $content = $this->generateTestContent(20, 500); // 20 sections, 500 words each

        $metrics = $this->measurePerformance(fn() => $this->processor->parseContent($content), 50);

        $this->assertLessThan(0.05, $metrics['avg_time'], 'Medium document should parse in <50ms on average');
        $this->assertLessThan(0.15, $metrics['max_time'], 'Medium document max time should be <150ms');
    }

    public function testBenchmarkLargeDocument(): void
    {
        $content = $this->generateTestContent(100, 1000); // 100 sections, 1000 words each

        $metrics = $this->measurePerformance(fn() => $this->processor->parseContent($content), 10);

        $this->assertLessThan(0.2, $metrics['avg_time'], 'Large document should parse in <200ms on average');
        $this->assertLessThan(0.5, $metrics['max_time'], 'Large document max time should be <500ms');
    }

    public function testBenchmarkMemoryUsage(): void
    {
        $content = $this->generateTestContent(50, 1000);

        $memoryBefore = memory_get_usage(true);
        $result = $this->processor->parseContent($content);
        $memoryAfter = memory_get_usage(true);

        $memoryUsed = $memoryAfter - $memoryBefore;

        $this->assertLessThan(10 * 1024 * 1024, $memoryUsed, 'Memory usage should be less than 10MB');
        $this->assertCount(50, $result->sections);
        $this->assertCount(50, $result->anchors);
    }

    public function testBenchmarkRegexPerformance(): void
    {
        $contentWithManyAnchors = '';
        for ($i = 1; $i <= 1000; $i++) {
            $contentWithManyAnchors .= "## Section {$i}\nContent {$i}\n<!-- SECTION_ANCHOR_section_{$i} -->\n";
        }

        $startTime = microtime(true);
        $result = $this->processor->parseContent($contentWithManyAnchors);
        $executionTime = microtime(true) - $startTime;

        $this->assertLessThan(0.3, $executionTime, 'Regex parsing of 1000 anchors should be <300ms');
        $this->assertCount(1000, $result->anchors);
    }

    public function testBenchmarkConcurrentParsing(): void
    {
        $content = $this->generateTestContent(10, 200);
        $results = [];
        $times = [];

        // Simulate concurrent parsing
        for ($i = 0; $i < 10; $i++) {
            $startTime = microtime(true);
            $results[] = $this->processor->parseContent($content);
            $times[] = microtime(true) - $startTime;
        }

        $avgTime = array_sum($times) / count($times);
        $maxTime = max($times);

        $this->assertLessThan(0.1, $avgTime, 'Concurrent parsing average should be <100ms');
        $this->assertLessThan(0.2, $maxTime, 'Concurrent parsing max should be <200ms');
        $this->assertCount(10, $results);

        // All results should be identical
        foreach ($results as $result) {
            $this->assertCount(10, $result->sections);
            $this->assertCount(10, $result->anchors);
        }
    }

    public function testBenchmarkStringOperations(): void
    {
        $content = str_repeat('A', 1000000); // 1MB of text

        $startTime = microtime(true);
        $result = $this->processor->removeAnchors($content);
        $removeTime = microtime(true) - $startTime;

        $startTime = microtime(true);
        $replaced = $this->processor->replaceAnchors($content, []);
        $replaceTime = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $removeTime, 'Remove anchors on 1MB text should be <100ms');
        $this->assertLessThan(0.1, $replaceTime, 'Replace anchors on 1MB text should be <100ms');
        $this->assertSame($content, $result); // No anchors to remove
        $this->assertSame($content, $replaced); // No anchors to replace
    }

    /**
     * Measure performance metrics for a callable.
     *
     * @param callable $operation
     * @param int $iterations
     * @return array{avg_time: float, min_time: float, max_time: float, total_time: float}
     */
    private function measurePerformance(callable $operation, int $iterations = 10): array
    {
        $times = [];

        // Warm-up run
        $operation();

        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $operation();
            $times[] = microtime(true) - $startTime;
        }

        return [
            'avg_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'total_time' => array_sum($times),
        ];
    }

    /**
     * Generate test content with specified number of sections and words.
     */
    private function generateTestContent(int $sections, int $wordsPerSection): string
    {
        $content = '';
        $words = ['contract', 'agreement', 'party', 'service', 'payment', 'delivery', 'terms', 'conditions', 'liability', 'warranty'];

        for ($i = 1; $i <= $sections; $i++) {
            $content .= "## {$i}. SECTION {$i}\n\n";

            // Generate random text
            for ($j = 0; $j < $wordsPerSection; $j++) {
                $content .= $words[array_rand($words)] . ' ';
            }

            $content .= "\n\n<!-- SECTION_ANCHOR_section_{$i} -->\n\n";
        }

        return $content;
    }
}