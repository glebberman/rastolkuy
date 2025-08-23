<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\LLM\Adapters\ClaudeAdapter;
use App\Services\LLM\Contracts\LLMAdapterInterface;
use App\Services\LLM\Exceptions\LLMException;
use App\Services\LLM\LLMService;
use App\Services\LLM\Support\RateLimiter;
use App\Services\LLM\Support\RetryHandler;
use App\Services\LLM\UsageMetrics;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for LLM services.
 */
final class LLMServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register LLM adapter based on configuration
        $this->app->singleton(LLMAdapterInterface::class, function (Application $app): LLMAdapterInterface {
            $defaultProvider = config('llm.default', 'claude');

            if (!is_string($defaultProvider)) {
                $defaultProvider = 'claude';
            }
            
            return match ($defaultProvider) {
                'claude' => $this->createClaudeAdapter(),
                default => throw new LLMException("Unsupported LLM provider: {$defaultProvider}"),
            };
        });

        // Register rate limiter
        $this->app->singleton(RateLimiter::class, function (): RateLimiter {
            $provider = config('llm.default', 'claude');
            if (!is_string($provider)) {
                $provider = 'claude';
            }

            return RateLimiter::forProvider($provider);
        });

        // Register retry handler
        $this->app->singleton(RetryHandler::class, function (): RetryHandler {
            return RetryHandler::forLLMOperations();
        });

        // Register usage metrics
        $this->app->singleton(UsageMetrics::class, function (): UsageMetrics {
            $provider = config('llm.default', 'claude');
            if (!is_string($provider)) {
                $provider = 'claude';
            }

            return new UsageMetrics($provider);
        });

        // Register main LLM service
        $this->app->singleton(LLMService::class, function (Application $app): LLMService {
            return new LLMService(
                adapter: $app->make(LLMAdapterInterface::class),
                rateLimiter: $app->make(RateLimiter::class),
                retryHandler: $app->make(RetryHandler::class),
                usageMetrics: $app->make(UsageMetrics::class),
            );
        });

        // Register alias for easier access
        $this->app->alias(LLMService::class, 'llm');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration if running as package
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/llm.php' => config_path('llm.php'),
            ], 'llm-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            LLMAdapterInterface::class,
            RateLimiter::class,
            RetryHandler::class,
            UsageMetrics::class,
            LLMService::class,
            'llm',
        ];
    }

    /**
     * Create Claude adapter instance.
     *
     * @throws LLMException
     */
    private function createClaudeAdapter(): ClaudeAdapter
    {
        $config = config('llm.providers.claude', []);
        if (!is_array($config)) {
            $config = [];
        }

        $apiKey = $config['api_key'] ?? '';
        if (!is_string($apiKey) || empty($apiKey)) {
            throw new LLMException('Claude API key is required but not configured');
        }

        $baseUrl = $config['base_url'] ?? 'https://api.anthropic.com/v1/messages';
        if (!is_string($baseUrl)) {
            $baseUrl = 'https://api.anthropic.com/v1/messages';
        }
        
        $timeout = $config['timeout'] ?? 60;
        if (!is_int($timeout)) {
            $timeout = 60;
        }

        return new ClaudeAdapter(
            apiKey: $apiKey,
            baseUrl: $baseUrl,
            timeoutSeconds: $timeout,
        );
    }
}
