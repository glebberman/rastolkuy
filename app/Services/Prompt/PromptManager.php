<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use App\PromptExecution;
use App\PromptSystem;
use App\PromptTemplate;
use App\Services\Prompt\DTOs\PromptExecutionResult;
use App\Services\Prompt\DTOs\PromptRenderRequest;
use App\Services\Prompt\Exceptions\PromptException;
use App\Services\Prompt\Exceptions\TemplateNotFoundException;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class PromptManager
{
    public function __construct(
        private TemplateEngine $templateEngine,
        private ClaudeApiClient $claudeApiClient,
        private QualityAnalyzer $qualityAnalyzer,
    ) {
    }

    public function executePrompt(PromptRenderRequest $request): PromptExecutionResult
    {
        $executionId = Str::uuid()->toString();
        $startTime = microtime(true);

        Log::info('Starting prompt execution', [
            'execution_id' => $executionId,
            'system_name' => $request->systemName,
            'template_name' => $request->templateName,
        ]);

        try {
            $promptSystem = $this->getPromptSystem($request->systemName);
            $template = $this->getTemplate($promptSystem, $request->templateName);

            // Обогащаем переменные структурой документа если она есть
            $enrichedVariables = $this->enrichVariablesWithDocumentStructure($request->variables);

            if ($template === null) {
                throw new TemplateNotFoundException('Template is required for prompt execution');
            }

            $renderedPrompt = $this->templateEngine->render($template, $enrichedVariables);

            $execution = $this->createExecution($promptSystem, $template, $executionId, $renderedPrompt, $enrichedVariables);

            $llmResponse = $this->claudeApiClient->execute($promptSystem, $renderedPrompt, $request->options);

            $qualityMetrics = $this->qualityAnalyzer->analyze($llmResponse['content'] ?? '', $promptSystem->schema);

            $result = $this->completeExecution($execution, $llmResponse, $qualityMetrics, $startTime);

            Log::info('Prompt execution completed successfully', [
                'execution_id' => $executionId,
                'execution_time_ms' => $result->executionTimeMs,
                'quality_score' => $result->qualityMetrics['overall_score'] ?? null,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->handleExecutionError($executionId, $e, $startTime);

            throw $e;
        }
    }

    public function renderTemplate(PromptRenderRequest $request): string
    {
        $promptSystem = $this->getPromptSystem($request->systemName);
        $template = $this->getTemplate($promptSystem, $request->templateName);

        // Обогащаем переменные структурой документа если она есть
        $enrichedVariables = $this->enrichVariablesWithDocumentStructure($request->variables);

        if ($template === null) {
            throw new TemplateNotFoundException('Template is required for rendering');
        }

        return $this->templateEngine->render($template, $enrichedVariables);
    }

    public function validateTemplate(string $systemName, string $templateName, array $variables): array
    {
        $promptSystem = $this->getPromptSystem($systemName);
        $template = $this->getTemplate($promptSystem, $templateName);

        if ($template === null) {
            throw new TemplateNotFoundException('Template is required for validation');
        }

        return $this->templateEngine->validate($template, $variables);
    }

    public function getSystemsByType(string $type): array
    {
        return PromptSystem::where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function getTemplatesBySystem(string $systemName): array
    {
        $promptSystem = $this->getPromptSystem($systemName);

        return $promptSystem->activeTemplates()
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function getExecutionStats(string $systemName, ?string $templateName = null): array
    {
        $query = PromptExecution::where('prompt_system_id', function ($query) use ($systemName): void {
            $query->select('id')
                ->from('prompt_systems')
                ->where('name', $systemName)
                ->limit(1);
        });

        if ($templateName !== null) {
            $query->where('prompt_template_id', function ($query) use ($templateName): void {
                $query->select('id')
                    ->from('prompt_templates')
                    ->where('name', $templateName)
                    ->limit(1);
            });
        }

        $executions = $query->get();

        return [
            'total_executions' => $executions->count(),
            'success_rate' => $this->calculateSuccessRate($executions),
            'average_execution_time' => $this->calculateAverageExecutionTime($executions),
            'average_cost' => $this->calculateAverageCost($executions),
            'quality_distribution' => $this->calculateQualityDistribution($executions),
        ];
    }

    private function getPromptSystem(string $name): PromptSystem
    {
        $system = PromptSystem::where('name', $name)
            ->where('is_active', true)
            ->first();

        if (!$system) {
            throw new PromptException("Prompt system not found or inactive: {$name}");
        }

        return $system;
    }

    private function getTemplate(PromptSystem $system, ?string $templateName): PromptTemplate|null
    {
        if ($templateName === null) {
            return null;
        }

        /** @var PromptTemplate|null $template */
        $template = $system->activeTemplates()
            ->where('name', $templateName)
            ->first();

        if (!$template) {
            throw new TemplateNotFoundException("Template not found: {$templateName} in system: {$system->name}");
        }

        return $template;
    }

    private function createExecution(
        PromptSystem $system,
        ?PromptTemplate $template,
        string $executionId,
        string $renderedPrompt,
        array $variables,
    ): PromptExecution {
        return PromptExecution::create([
            'prompt_system_id' => $system->id,
            'prompt_template_id' => $template?->id,
            'execution_id' => $executionId,
            'rendered_prompt' => $renderedPrompt,
            'input_variables' => $variables,
            'status' => 'pending',
            'started_at' => now(),
        ]);
    }

    private function completeExecution(
        PromptExecution $execution,
        array $llmResponse,
        array $qualityMetrics,
        float $startTime,
    ): PromptExecutionResult {
        $executionTime = (microtime(true) - $startTime) * 1000;

        $execution->update([
            'llm_response' => $llmResponse['content'] ?? '',
            'model_used' => $llmResponse['model'] ?? null,
            'tokens_used' => $llmResponse['tokens'] ?? null,
            'execution_time_ms' => $executionTime,
            'cost_usd' => $llmResponse['cost'] ?? null,
            'status' => 'success',
            'quality_metrics' => $qualityMetrics,
            'completed_at' => now(),
        ]);

        return new PromptExecutionResult(
            executionId: $execution->execution_id,
            response: $llmResponse['content'] ?? '',
            executionTimeMs: $executionTime,
            tokensUsed: $llmResponse['tokens'] ?? 0,
            costUsd: $llmResponse['cost'] ?? 0.0,
            qualityMetrics: $qualityMetrics,
            metadata: $llmResponse['metadata'] ?? [],
        );
    }

    private function handleExecutionError(string $executionId, Exception $e, float $startTime): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000;

        PromptExecution::where('execution_id', $executionId)->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'execution_time_ms' => $executionTime,
            'completed_at' => now(),
        ]);

        Log::error('Prompt execution failed', [
            'execution_id' => $executionId,
            'error' => $e->getMessage(),
            'execution_time_ms' => $executionTime,
        ]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $executions
     */
    private function calculateSuccessRate($executions): float
    {
        if ($executions->isEmpty()) {
            return 0.0;
        }

        $successCount = $executions->where('status', 'success')->count();

        return round(($successCount / $executions->count()) * 100, 2);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $executions
     */
    private function calculateAverageExecutionTime($executions): float
    {
        $successfulExecutions = $executions->where('status', 'success')->whereNotNull('execution_time_ms');

        if ($successfulExecutions->isEmpty()) {
            return 0.0;
        }

        return round($successfulExecutions->avg('execution_time_ms') ?? 0, 2);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $executions
     */
    private function calculateAverageCost($executions): float
    {
        $successfulExecutions = $executions->where('status', 'success')->whereNotNull('cost_usd');

        if ($successfulExecutions->isEmpty()) {
            return 0.0;
        }

        return round($successfulExecutions->avg('cost_usd') ?? 0, 6);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $executions
     */
    private function calculateQualityDistribution($executions): array
    {
        $distribution = [
            'excellent' => 0,
            'good' => 0,
            'fair' => 0,
            'poor' => 0,
            'unrated' => 0,
        ];

        foreach ($executions as $execution) {
            $qualityScore = $execution->quality_metrics['overall_score'] ?? null;

            if ($qualityScore === null) {
                ++$distribution['unrated'];
            } elseif ($qualityScore >= 0.9) {
                ++$distribution['excellent'];
            } elseif ($qualityScore >= 0.7) {
                ++$distribution['good'];
            } elseif ($qualityScore >= 0.5) {
                ++$distribution['fair'];
            } else {
                ++$distribution['poor'];
            }
        }

        return $distribution;
    }

    private function enrichVariablesWithDocumentStructure(array $variables): array
    {
        // Если в переменных есть document_sections, конвертируем их в readable структуру
        if (isset($variables['document_sections']) && is_array($variables['document_sections'])) {
            $variables['document_structure'] = $this->formatDocumentStructureForPrompt($variables['document_sections']);
        }

        return $variables;
    }

    private function formatDocumentStructureForPrompt(array $sections): string
    {
        $formatted = "Структура документа с якорями:\n\n";
        
        foreach ($sections as $section) {
            $formatted .= $this->formatSectionForPrompt($section, 0);
        }

        return $formatted;
    }

    private function formatSectionForPrompt(array $section, int $level): string
    {
        $indent = str_repeat('  ', $level);
        $formatted = sprintf(
            "%s- %s (якорь: %s, позиция: %d-%d)\n",
            $indent,
            $section['title'] ?? 'Untitled',
            $section['anchor'] ?? 'no-anchor',
            $section['start_position'] ?? 0,
            $section['end_position'] ?? 0
        );

        // Добавляем подсекции рекурсивно
        if (isset($section['subsections']) && is_array($section['subsections'])) {
            foreach ($section['subsections'] as $subsection) {
                $formatted .= $this->formatSectionForPrompt($subsection, $level + 1);
            }
        }

        return $formatted;
    }
}
