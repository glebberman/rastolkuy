<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LLM\LLMService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Command to switch between LLM providers (Claude/Fake) for development.
 */
class SwitchLLMProvider extends Command
{
    protected $signature = 'llm:switch
                           {provider : The provider to switch to (claude|fake)}
                           {--test : Test the connection after switching}';

    protected $description = 'Switch between LLM providers for development';

    public function handle(): void
    {
        $provider = $this->argument('provider');

        if (!in_array($provider, ['claude', 'fake'], true)) {
            $this->error('Invalid provider. Use: claude or fake');
            return;
        }

        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('.env file not found. Please copy .env.example to .env first.');
            return;
        }

        try {
            $this->updateEnvFile($envPath, $provider);
            $this->info("✅ Switched LLM provider to: {$provider}");

            // Clear config cache to ensure new settings take effect
            $this->call('config:clear');

            if ($this->option('test')) {
                $this->testProvider($provider);
            }

            $this->displayProviderInfo($provider);
        } catch (\Exception $e) {
            $this->error("Failed to switch provider: {$e->getMessage()}");
        }
    }

    private function updateEnvFile(string $envPath, string $provider): void
    {
        $envContent = File::get($envPath);

        if (!is_string($envContent)) {
            throw new \RuntimeException("Failed to read .env file at {$envPath}");
        }

        // Update LLM_DEFAULT_PROVIDER
        if (preg_match('/^LLM_DEFAULT_PROVIDER=.*/m', $envContent)) {
            $updated = preg_replace(
                '/^LLM_DEFAULT_PROVIDER=.*/m',
                "LLM_DEFAULT_PROVIDER={$provider}",
                $envContent
            );

            if ($updated === null) {
                throw new \RuntimeException('Failed to update LLM_DEFAULT_PROVIDER in .env file');
            }

            $envContent = $updated;
        } else {
            $envContent .= "\nLLM_DEFAULT_PROVIDER={$provider}\n";
        }

        File::put($envPath, $envContent);
    }

    private function testProvider(string $provider): void
    {
        $this->info("🧪 Testing {$provider} provider connection...");

        try {
            /** @var LLMService $llmService */
            $llmService = app(LLMService::class);

            $info = $llmService->getProviderInfo();

            if ($info['connection_valid']) {
                $this->info("✅ Connection test successful!");
            } else {
                $this->warn("⚠️  Connection test failed, but provider switched successfully.");
            }

            // Test with a simple request
            if ($provider === 'fake') {
                $this->info("🤖 Testing fake response generation...");
                $response = $llmService->generate('Тест фейкового адаптера');

                $this->info("📄 Response length: " . mb_strlen($response->content) . " characters");
                $this->info("💰 Fake cost: $" . number_format($response->costUsd, 6));
                $this->info("⏱️  Execution time: " . number_format($response->executionTimeMs, 2) . "ms");
            }
        } catch (\Exception $e) {
            $this->error("❌ Provider test failed: {$e->getMessage()}");
        }
    }

    private function displayProviderInfo(string $provider): void
    {
        $this->newLine();

        if ($provider === 'fake') {
            $this->info("🎭 Using FAKE LLM provider");
            $this->info("   • No real API calls will be made");
            $this->info("   • Realistic fake responses will be generated");
            $this->info("   • Very low cost simulation");
            $this->info("   • Fast response times");
            $this->newLine();
            $this->info("💡 Perfect for:");
            $this->info("   • Development and testing");
            $this->info("   • UI/UX work without API costs");
            $this->info("   • Integration testing");
            $this->info("   • Demo presentations");
        } else {
            $this->info("🤖 Using REAL Claude API");
            $this->info("   • Real API calls to Anthropic Claude");
            $this->info("   • Actual costs will be incurred");
            $this->info("   • Production-quality responses");
            $this->info("   • Rate limiting applies");
            $this->newLine();
            $this->warn("⚠️  Remember:");
            $this->warn("   • API key must be configured");
            $this->warn("   • Costs will be charged to your account");
            $this->warn("   • Rate limits apply");
        }

        $this->newLine();
        $this->info("🔄 To switch back, run:");
        $this->info("   php artisan llm:switch " . ($provider === 'fake' ? 'claude' : 'fake'));
    }
}