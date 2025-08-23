<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use App\Services\Structure\SectionDetector;
use App\Services\Structure\StructureAnalyzer;
use Illuminate\Support\ServiceProvider;

class StructureAnalysisServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем интерфейсы и их реализации
        $this->app->bind(AnchorGeneratorInterface::class, AnchorGenerator::class);
        $this->app->bind(SectionDetectorInterface::class, SectionDetector::class);

        // Регистрируем основной анализатор как singleton для переиспользования
        $this->app->singleton(StructureAnalyzer::class, function ($app) {
            return new StructureAnalyzer(
                $app->make(SectionDetectorInterface::class),
                $app->make(AnchorGeneratorInterface::class),
            );
        });

        // Алиас для удобства использования
        $this->app->alias(StructureAnalyzer::class, 'structure.analyzer');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Публикация конфигурации (если нужно)
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/structure_analysis.php' => config_path('structure_analysis.php'),
            ], 'structure-analysis-config');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            AnchorGeneratorInterface::class,
            SectionDetectorInterface::class,
            StructureAnalyzer::class,
            'structure.analyzer',
        ];
    }
}
