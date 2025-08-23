<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExecutePromptRequest;
use App\Models\PromptExecution;
use App\Services\Prompt\DTOs\PromptRenderRequest;
use App\Services\Prompt\Exceptions\PromptException;
use App\Services\Prompt\PromptManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromptExecutionController extends Controller
{
    public function __construct(
        private readonly PromptManager $promptManager,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = PromptExecution::query()->with(['promptSystem', 'promptTemplate']);

        if ($request->has('system_id')) {
            $query->where('prompt_system_id', $request->get('system_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('execution_id')) {
            $query->where('execution_id', $request->get('execution_id'));
        }

        $perPage = $request->get('per_page', 15);
        $executions = $query->latest()
            ->paginate(is_numeric($perPage) ? (int) $perPage : 15);

        return response()->json($executions);
    }

    public function execute(ExecutePromptRequest $request): JsonResponse
    {
        try {
            $systemName = $request->get('system_name');
            $templateName = $request->get('template_name');
            $variables = $request->get('variables', []);
            $options = $request->get('options', []);

            $promptRequest = PromptRenderRequest::create(
                systemName: is_string($systemName) ? $systemName : '',
                templateName: is_string($templateName) ? $templateName : null,
                variables: is_array($variables) ? $variables : [],
                options: is_array($options) ? $options : [],
            );

            $result = $this->promptManager->executePrompt($promptRequest);

            return response()->json([
                'success' => true,
                'data' => [
                    'execution_id' => $result->executionId,
                    'response' => $result->response,
                    'execution_time_ms' => $result->executionTimeMs,
                    'tokens_used' => $result->tokensUsed,
                    'cost_usd' => $result->costUsd,
                    'quality_metrics' => $result->qualityMetrics,
                    'metadata' => $result->metadata,
                ],
            ]);
        } catch (PromptException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function render(ExecutePromptRequest $request): JsonResponse
    {
        try {
            $systemName = $request->get('system_name');
            $templateName = $request->get('template_name');
            $variables = $request->get('variables', []);
            $options = $request->get('options', []);

            $promptRequest = PromptRenderRequest::create(
                systemName: is_string($systemName) ? $systemName : '',
                templateName: is_string($templateName) ? $templateName : null,
                variables: is_array($variables) ? $variables : [],
                options: is_array($options) ? $options : [],
            );

            $renderedPrompt = $this->promptManager->renderTemplate($promptRequest);

            return response()->json([
                'success' => true,
                'data' => [
                    'rendered_prompt' => $renderedPrompt,
                    'character_count' => mb_strlen($renderedPrompt),
                    'word_count' => str_word_count($renderedPrompt),
                ],
            ]);
        } catch (PromptException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function validate(ExecutePromptRequest $request): JsonResponse
    {
        try {
            $systemName = $request->get('system_name');
            $templateName = $request->get('template_name');
            $variables = $request->get('variables', []);

            $validation = $this->promptManager->validateTemplate(
                systemName: is_string($systemName) ? $systemName : '',
                templateName: is_string($templateName) ? $templateName : '',
                variables: is_array($variables) ? $variables : [],
            );

            return response()->json([
                'success' => true,
                'data' => $validation,
            ]);
        } catch (PromptException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(string $executionId): JsonResponse
    {
        $execution = PromptExecution::where('execution_id', $executionId)
            ->with(['promptSystem', 'promptTemplate'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $execution,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $systemName = $request->get('system_name');
        $templateName = $request->get('template_name');

        try {
            $stats = $this->promptManager->getExecutionStats(
                is_string($systemName) ? $systemName : '',
                is_string($templateName) ? $templateName : null,
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (PromptException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function systemsByType(Request $request): JsonResponse
    {
        $type = $request->get('type', 'general');

        try {
            $systems = $this->promptManager->getSystemsByType(is_string($type) ? $type : 'general');

            return response()->json([
                'success' => true,
                'data' => $systems,
            ]);
        } catch (PromptException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function templates(Request $request): JsonResponse
    {
        $systemName = $request->get('system_name');

        if (!$systemName) {
            return response()->json([
                'success' => false,
                'error' => 'Название системы обязательно',
            ], 400);
        }

        try {
            $templates = $this->promptManager->getTemplatesBySystem(is_string($systemName) ? $systemName : '');

            return response()->json([
                'success' => true,
                'data' => $templates,
            ]);
        } catch (PromptException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
