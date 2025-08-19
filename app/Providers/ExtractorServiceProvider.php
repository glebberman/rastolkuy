<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Parser\Extractors\ExtractorFactory;
use App\Services\Parser\Extractors\ExtractorInterface;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use App\Services\Parser\Extractors\TxtExtractor;
use Illuminate\Support\ServiceProvider;

class ExtractorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register support classes as singletons
        $this->app->singleton(EncodingDetector::class);
        $this->app->singleton(ElementClassifier::class);
        $this->app->singleton(MetricsCollector::class);

        // Register extractors
        $this->app->bind(TxtExtractor::class, function ($app) {
            return new TxtExtractor(
                $app->make(EncodingDetector::class),
                $app->make(ElementClassifier::class),
                $app->make(MetricsCollector::class)
            );
        });

        // Register factory
        $this->app->singleton(ExtractorFactory::class, function ($app) {
            return new ExtractorFactory(
                $app->make(EncodingDetector::class),
                $app->make(ElementClassifier::class),
                $app->make(MetricsCollector::class),
                $app // Pass the container for DI
            );
        });

        // Register manager
        $this->app->singleton(ExtractorManager::class, function ($app) {
            return new ExtractorManager(
                $app->make(ExtractorFactory::class)
            );
        });

        // Bind interface to default implementation
        $this->app->when(ExtractorFactory::class)
            ->needs(ExtractorInterface::class)
            ->give(TxtExtractor::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/extractors.php' => config_path('extractors.php'),
        ], 'config');

        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/extractors.php', 'extractors');
    }
}