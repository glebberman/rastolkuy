<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DocumentProcessing;
use App\Services\CreditService;
use App\Services\LLM\CostCalculator;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Queue\QueueConfigurationService;
use App\Services\Structure\StructureAnalyzer;
use Error;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class AnalyzeDocumentStructureJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        $queueConfig = app(QueueConfigurationService::class);
        $jobConfig = $queueConfig->getAnalysisJobConfig();
        $baseDelay = $jobConfig['retry_after_seconds'];
        
        // Exponential backoff: 60s, 120s, 240s
        return [
            $baseDelay,
            $baseDelay * 2,
            $baseDelay * 4,
        ];
    }

    /**
     * Unique key for the job to prevent duplicates.
     */
    public function uniqueId(): string
    {
        return "analyze_document_{$this->documentProcessingId}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly int $documentProcessingId,
        private readonly ?string $model = null,
    ) {
        $queueConfig = app(QueueConfigurationService::class);
        
        $this->onQueue($queueConfig->getDocumentAnalysisQueue());
        
        $jobConfig = $queueConfig->getAnalysisJobConfig();
        $this->tries = $jobConfig['max_tries'];
        $this->timeout = $jobConfig['timeout_seconds'];
    }

    /**
     * Execute the job.
     */
    public function handle(
        ExtractorManager $extractorManager,
        StructureAnalyzer $structureAnalyzer,
        CostCalculator $costCalculator,
        CreditService $creditService,
    ): void {
        Log::info('Starting async document structure analysis job', [
            'document_processing_id' => $this->documentProcessingId,
            'model' => $this->model,
        ]);

        $documentProcessing = DocumentProcessing::find($this->documentProcessingId);

        if (!$documentProcessing) {
            Log::error('Document processing not found for analysis job', [
                'document_processing_id' => $this->documentProcessingId,
            ]);

            return;
        }

        if (!$documentProcessing->isAnalyzing()) {
            Log::warning('Document is not in analyzing status, skipping', [
                'uuid' => $documentProcessing->uuid,
                'current_status' => $documentProcessing->status,
            ]);

            return;
        }

        try {
            $this->performStructureAnalysis(
                $documentProcessing,
                $extractorManager,
                $structureAnalyzer,
                $costCalculator,
                $creditService,
            );

            Log::info('Document structure analysis job completed successfully', [
                'uuid' => $documentProcessing->uuid,
                'document_processing_id' => $this->documentProcessingId,
            ]);
        } catch (Exception $e) {
            Log::error('Document structure analysis job failed', [
                'uuid' => $documentProcessing->uuid,
                'document_processing_id' => $this->documentProcessingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handleAnalysisFailure($documentProcessing, $e, $costCalculator, $creditService);
        }
    }

    /**
     * Handle failed job.
     */
    public function failed(Exception|Error $exception): void
    {
        Log::error('AnalyzeDocumentStructureJob permanently failed', [
            'document_processing_id' => $this->documentProcessingId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $documentProcessing = DocumentProcessing::find($this->documentProcessingId);

        if ($documentProcessing && $documentProcessing->isAnalyzing()) {
            $documentProcessing->markAsFailed(
                'Structure analysis job failed permanently: ' . $exception->getMessage(),
                [
                    'failed_at' => now()->toISOString(),
                    'job_class' => self::class,
                    'attempts' => $this->attempts(),
                ],
            );
        }
    }

    /**
     * Perform the actual structure analysis.
     */
    private function performStructureAnalysis(
        DocumentProcessing $documentProcessing,
        ExtractorManager $extractorManager,
        StructureAnalyzer $structureAnalyzer,
        CostCalculator $costCalculator,
        CreditService $creditService,
    ): void {
        // Check file size limits
        $maxFileSizeMb = config('document.structure_analysis.max_file_size_mb', 50);
        assert(is_numeric($maxFileSizeMb));
        $maxFileSizeMb = (int) $maxFileSizeMb;
        $fileSizeMb = $documentProcessing->file_size / (1024 * 1024);

        if ($fileSizeMb > $maxFileSizeMb) {
            throw new RuntimeException("File too large for structure analysis: {$fileSizeMb}MB > {$maxFileSizeMb}MB");
        }

        // Extract document content
        $extractedDocument = $extractorManager->extract(
            Storage::disk('local')->path($documentProcessing->file_path),
        );

        // Analyze structure
        $structureResult = $structureAnalyzer->analyze($extractedDocument);

        // Prepare structural data
        $structuralData = [
            'sections_count' => count($structureResult->sections),
            'average_confidence' => $structureResult->averageConfidence,
            'analysis_duration_ms' => (int) ($structureResult->analysisTime * 1000),
            'sections' => array_map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'anchor' => $section->anchor,
                'level' => $section->level,
                'confidence' => $section->confidence,
                'start_position' => $section->startPosition,
                'end_position' => $section->endPosition,
            ], $structureResult->sections),
        ];

        // Calculate cost with structure
        $model = $this->model ?? $this->getDefaultModel();
        $estimation = $this->estimateProcessingCostWithStructure(
            $documentProcessing->file_size,
            count($structureResult->sections),
            $model,
            $costCalculator,
        );

        // Convert USD to credits
        $creditsNeeded = $creditService->convertUsdToCredits($estimation['estimated_cost_usd']);

        $user = $documentProcessing->user;

        if (!$user) {
            throw new InvalidArgumentException('Document processing must have an associated user');
        }

        $estimationData = array_merge($estimation, [
            'credits_needed' => $creditsNeeded,
            'model_selected' => $model,
            'has_sufficient_balance' => $creditService->hasSufficientBalance($user, $creditsNeeded),
            'user_balance' => $creditService->getBalance($user),
        ]);

        // Update document with results
        $documentProcessing->markAsEstimatedWithStructure($estimationData, $structuralData);

        Log::info('Document structure analysis completed successfully', [
            'uuid' => $documentProcessing->uuid,
            'sections_found' => count($structureResult->sections),
            'average_confidence' => $structureResult->averageConfidence,
            'estimated_cost_usd' => $estimation['estimated_cost_usd'],
            'credits_needed' => $creditsNeeded,
            'model' => $model,
        ]);
    }

    /**
     * Handle analysis failure with fallback.
     */
    private function handleAnalysisFailure(
        DocumentProcessing $documentProcessing,
        Exception $exception,
        CostCalculator $costCalculator,
        CreditService $creditService,
    ): void {
        Log::warning('Document structure analysis failed, falling back to simple estimation', [
            'uuid' => $documentProcessing->uuid,
            'error' => $exception->getMessage(),
        ]);

        // Fallback to simple estimation
        $structuralData = [
            'sections_count' => 0,
            'average_confidence' => 0.0,
            'analysis_duration_ms' => 0,
            'sections' => [],
            'analysis_failed' => true,
            'error_message' => 'Structure analysis failed: ' . $exception->getMessage(),
            'fallback_used' => true,
        ];

        // Simple cost estimation without structure
        $model = $this->model ?? $this->getDefaultModel();
        $estimation = $this->estimateProcessingCost(
            $documentProcessing->file_size,
            $model,
            $costCalculator,
        );

        // Convert USD to credits
        $creditsNeeded = $creditService->convertUsdToCredits($estimation['estimated_cost_usd']);

        $user = $documentProcessing->user;

        if (!$user) {
            throw new InvalidArgumentException('Document processing must have an associated user');
        }

        $estimationData = array_merge($estimation, [
            'credits_needed' => $creditsNeeded,
            'model_selected' => $model,
            'has_sufficient_balance' => $creditService->hasSufficientBalance($user, $creditsNeeded),
            'user_balance' => $creditService->getBalance($user),
        ]);

        // Update document with fallback results
        $documentProcessing->markAsEstimatedWithStructure($estimationData, $structuralData);
    }

    /**
     * Get the default model from configuration.
     */
    private function getDefaultModel(): string
    {
        $defaultModel = config('llm.default_model', 'claude-3-5-sonnet-20241022');

        return is_string($defaultModel) ? $defaultModel : 'claude-3-5-sonnet-20241022';
    }

    /**
     * Get cost estimation with structure analysis.
     */
    private function estimateProcessingCostWithStructure(
        int $fileSizeBytes,
        int $sectionsCount,
        string $model,
        CostCalculator $costCalculator,
    ): array {
        $estimatedInputTokens = $costCalculator->estimateTokensFromFileSize($fileSizeBytes);

        // Section-based cost adjustment from config
        $sectionCostMultiplier = config('document.cost_estimation.section_cost_multiplier', 0.1);
        assert(is_numeric($sectionCostMultiplier));
        $sectionCostMultiplier = (float) $sectionCostMultiplier;

        $maxSectionMultiplier = config('document.cost_estimation.max_section_multiplier', 3.0);
        assert(is_numeric($maxSectionMultiplier));
        $maxSectionMultiplier = (float) $maxSectionMultiplier;

        // More sections = more context for LLM = more output tokens
        $sectionMultiplier = max(1.0, 1.0 + ($sectionsCount * $sectionCostMultiplier));
        $sectionMultiplier = min($sectionMultiplier, $maxSectionMultiplier);

        $estimatedOutputTokens = (int) ($estimatedInputTokens * 1.5 * $sectionMultiplier);
        $estimatedCost = $costCalculator->calculateCost($estimatedInputTokens, $estimatedOutputTokens, $model);

        return [
            'estimated_input_tokens' => $estimatedInputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
            'estimated_total_tokens' => $estimatedInputTokens + $estimatedOutputTokens,
            'estimated_cost_usd' => $estimatedCost,
            'model_used' => $model,
            'pricing_info' => $costCalculator->getPricingInfo($model),
            'sections_count' => $sectionsCount,
            'section_multiplier' => $sectionMultiplier,
        ];
    }

    /**
     * Get simple cost estimation without structure analysis.
     */
    private function estimateProcessingCost(int $fileSizeBytes, string $model, CostCalculator $costCalculator): array
    {
        $estimatedInputTokens = $costCalculator->estimateTokensFromFileSize($fileSizeBytes);
        $estimatedOutputTokens = (int) ($estimatedInputTokens * 1.5);
        $estimatedCost = $costCalculator->calculateCost($estimatedInputTokens, $estimatedOutputTokens, $model);

        return [
            'estimated_input_tokens' => $estimatedInputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
            'estimated_total_tokens' => $estimatedInputTokens + $estimatedOutputTokens,
            'estimated_cost_usd' => $estimatedCost,
            'model_used' => $model,
            'pricing_info' => $costCalculator->getPricingInfo($model),
        ];
    }
}
