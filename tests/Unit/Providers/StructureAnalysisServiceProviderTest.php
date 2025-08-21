<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\StructureAnalysisServiceProvider;
use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use App\Services\Structure\SectionDetector;
use App\Services\Structure\StructureAnalyzer;
use Tests\TestCase;

class StructureAnalysisServiceProviderTest extends TestCase
{
    public function testProviderRegistersServices(): void
    {
        // Test that interfaces are bound to their implementations
        $this->assertInstanceOf(
            AnchorGenerator::class,
            $this->app->make(AnchorGeneratorInterface::class)
        );

        $this->assertInstanceOf(
            SectionDetector::class,
            $this->app->make(SectionDetectorInterface::class)
        );

        // Test that StructureAnalyzer is registered as singleton
        $analyzer1 = $this->app->make(StructureAnalyzer::class);
        $analyzer2 = $this->app->make(StructureAnalyzer::class);

        $this->assertSame($analyzer1, $analyzer2);
    }

    public function testProviderRegistersAlias(): void
    {
        // Test that alias works
        $analyzer1 = $this->app->make('structure.analyzer');
        $analyzer2 = $this->app->make(StructureAnalyzer::class);

        $this->assertSame($analyzer1, $analyzer2);
        $this->assertInstanceOf(StructureAnalyzer::class, $analyzer1);
    }

    public function testProviderProvidesCorrectServices(): void
    {
        $provider = new StructureAnalysisServiceProvider($this->app);
        $provides = $provider->provides();

        $expectedServices = [
            AnchorGeneratorInterface::class,
            SectionDetectorInterface::class,
            StructureAnalyzer::class,
            'structure.analyzer',
        ];

        $this->assertEquals($expectedServices, $provides);
    }

    public function testStructureAnalyzerReceivesCorrectDependencies(): void
    {
        $analyzer = $this->app->make(StructureAnalyzer::class);

        // Use reflection to check that dependencies are injected correctly
        $reflection = new \ReflectionClass($analyzer);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor, 'Constructor should exist');
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('sectionDetector', $parameters[0]->getName());
        $this->assertEquals('anchorGenerator', $parameters[1]->getName());
    }
}