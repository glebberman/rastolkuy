<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Parser\Extractors\Support;

use App\Services\Parser\Extractors\Support\MetricsCollector;
use Tests\TestCase;

use const PHP_FLOAT_MAX;

class MetricsCollectorTest extends TestCase
{
    private MetricsCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->collector = new MetricsCollector();
    }

    public function testRecordsMetrics(): void
    {
        $this->collector->record('test_operation', 1.5, 1024, ['additional' => 'data']);

        $metrics = $this->collector->getMetrics();
        $this->assertArrayHasKey('test_operation', $metrics);

        $operationMetrics = $metrics['test_operation'];
        $this->assertEquals(1, $operationMetrics['count']);
        $this->assertEquals(1.5, $operationMetrics['total_duration']);
        $this->assertEquals(1.5, $operationMetrics['average_duration']);
        $this->assertEquals(1.5, $operationMetrics['min_duration']);
        $this->assertEquals(1.5, $operationMetrics['max_duration']);
        $this->assertEquals(1024, $operationMetrics['total_data_size']);
        $this->assertEquals(1024, $operationMetrics['average_data_size']);
    }

    public function testRecordsMultipleOperations(): void
    {
        $this->collector->record('operation_a', 1.0, 500);
        $this->collector->record('operation_b', 2.0, 1000);
        $this->collector->record('operation_a', 2.0, 750);

        $metrics = $this->collector->getMetrics();

        // Check operation_a metrics
        $opA = $metrics['operation_a'];
        $this->assertEquals(2, $opA['count']);
        $this->assertEquals(3.0, $opA['total_duration']);
        $this->assertEquals(1.5, $opA['average_duration']);
        $this->assertEquals(1.0, $opA['min_duration']);
        $this->assertEquals(2.0, $opA['max_duration']);
        $this->assertEquals(1250, $opA['total_data_size']);

        // Check operation_b metrics
        $opB = $metrics['operation_b'];
        $this->assertEquals(1, $opB['count']);
        $this->assertEquals(2.0, $opB['total_duration']);
        $this->assertEquals(1000, $opB['total_data_size']);
    }

    public function testCalculatesThroughput(): void
    {
        $this->collector->record('throughput_test', 2.0, 2000); // 1000 bytes/second

        $metrics = $this->collector->getMetrics();
        $throughput = $metrics['throughput_test']['throughput'];

        $this->assertEquals(1000.0, $throughput);
    }

    public function testHandlesZeroDuration(): void
    {
        $this->collector->record('zero_duration', 0.0, 1000);

        $metrics = $this->collector->getMetrics();
        $operationMetrics = $metrics['zero_duration'];

        $this->assertEquals(0.0, $operationMetrics['throughput']);
        $this->assertEquals(0.0, $operationMetrics['average_duration']);
    }

    public function testHandlesZeroDataSize(): void
    {
        $this->collector->record('zero_data', 1.0, 0);

        $metrics = $this->collector->getMetrics();
        $operationMetrics = $metrics['zero_data'];

        $this->assertEquals(0, $operationMetrics['total_data_size']);
        $this->assertEquals(0, $operationMetrics['average_data_size']);
        $this->assertEquals(0.0, $operationMetrics['throughput']);
    }

    public function testGetOperationMetrics(): void
    {
        $this->collector->record('specific_op', 1.0, 100);

        $operationMetrics = $this->collector->getOperationMetrics('specific_op');
        $this->assertNotNull($operationMetrics);
        $this->assertEquals(1, $operationMetrics['count']);

        $nonExistentMetrics = $this->collector->getOperationMetrics('non_existent');
        $this->assertNull($nonExistentMetrics);
    }

    public function testReset(): void
    {
        $this->collector->record('test_op', 1.0, 100);
        $this->assertNotEmpty($this->collector->getMetrics());

        $this->collector->reset();
        $this->assertEmpty($this->collector->getMetrics());
    }

    public function testGetTotalDuration(): void
    {
        $this->collector->record('op1', 1.5, 100);
        $this->collector->record('op2', 2.5, 200);
        $this->collector->record('op1', 1.0, 150);

        $totalDuration = $this->collector->getTotalDuration();
        $this->assertEquals(5.0, $totalDuration); // 1.5 + 2.5 + 1.0
    }

    public function testGetTotalOperations(): void
    {
        $this->collector->record('op1', 1.0, 100);
        $this->collector->record('op2', 2.0, 200);
        $this->collector->record('op1', 1.5, 150);

        $totalOperations = $this->collector->getTotalOperations();
        $this->assertEquals(3, $totalOperations);
    }

    public function testHandlesMinDurationCorrectly(): void
    {
        // Test that min_duration is not PHP_FLOAT_MAX when there's actual data
        $this->collector->record('min_test', 5.0, 100);
        $this->collector->record('min_test', 2.0, 150);
        $this->collector->record('min_test', 8.0, 200);

        $metrics = $this->collector->getMetrics();
        $minDuration = $metrics['min_test']['min_duration'];

        $this->assertEquals(2.0, $minDuration);
        $this->assertNotEquals(PHP_FLOAT_MAX, $minDuration);
    }

    public function testStoresAdditionalData(): void
    {
        $additionalData = ['file_type' => 'txt', 'encoding' => 'utf-8'];
        $this->collector->record('with_additional', 1.0, 100, $additionalData);

        $operationMetrics = $this->collector->getOperationMetrics('with_additional');
        $this->assertNotNull($operationMetrics);
        $operations = $operationMetrics['operations'] ?? [];

        $this->assertCount(1, $operations);
        $this->assertEquals($additionalData, $operations[0]['additional']);
        $this->assertArrayHasKey('timestamp', $operations[0]);
        $this->assertArrayHasKey('duration', $operations[0]);
        $this->assertArrayHasKey('data_size', $operations[0]);
    }

    public function testConcurrentOperations(): void
    {
        // Simulate concurrent operations by recording them with timestamps
        $this->collector->record('concurrent_op', 1.0, 100);
        usleep(1000); // 1ms delay
        $this->collector->record('concurrent_op', 1.5, 150);

        $operationMetrics = $this->collector->getOperationMetrics('concurrent_op');
        $this->assertNotNull($operationMetrics);
        $operations = $operationMetrics['operations'] ?? [];

        $this->assertCount(2, $operations);
        $this->assertGreaterThan($operations[0]['timestamp'], $operations[1]['timestamp']);
    }
}
