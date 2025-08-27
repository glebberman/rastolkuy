<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePromptTemplateRequest;
use App\Http\Requests\Api\UpdatePromptTemplateRequest;
use App\Http\Resources\PromptTemplateResource;
use App\Models\PromptTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PromptTemplate::class);

        $query = PromptTemplate::query()->with('promptSystem');

        if ($request->has('prompt_system_id')) {
            $query->where('prompt_system_id', $request->get('prompt_system_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');

            if (is_string($search)) {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('template', 'like', "%{$search}%");
                });
            }
        }

        $perPage = $request->get('per_page', 15);
        $templates = $query->withCount('executions')
            ->orderBy('name')
            ->paginate(is_numeric($perPage) ? (int) $perPage : 15);

        return PromptTemplateResource::collection($templates);
    }

    public function store(StorePromptTemplateRequest $request): PromptTemplateResource
    {
        $this->authorize('create', PromptTemplate::class);

        $template = PromptTemplate::create($request->validated());

        return new PromptTemplateResource(
            $template->load('promptSystem')->loadCount('executions'),
        );
    }

    public function show(PromptTemplate $promptTemplate): PromptTemplateResource
    {
        $this->authorize('view', $promptTemplate);

        return new PromptTemplateResource(
            $promptTemplate->load('promptSystem')->loadCount('executions'),
        );
    }

    public function update(UpdatePromptTemplateRequest $request, PromptTemplate $promptTemplate): PromptTemplateResource
    {
        $this->authorize('update', $promptTemplate);

        $promptTemplate->update($request->validated());

        $freshTemplate = $promptTemplate->fresh(['promptSystem']);

        if ($freshTemplate) {
            $freshTemplate->loadCount('executions');
        }

        return new PromptTemplateResource($freshTemplate ?? $promptTemplate);
    }

    public function destroy(PromptTemplate $promptTemplate): JsonResponse
    {
        $this->authorize('delete', $promptTemplate);

        $promptTemplate->delete();

        return response()->json([
            'message' => 'Шаблон промпта успешно удален',
        ]);
    }

    public function toggle(PromptTemplate $promptTemplate): PromptTemplateResource
    {
        $this->authorize('update', $promptTemplate);

        $promptTemplate->update([
            'is_active' => !$promptTemplate->is_active,
        ]);

        return new PromptTemplateResource($promptTemplate->fresh());
    }

    public function executions(PromptTemplate $promptTemplate): JsonResponse
    {
        $this->authorize('view', $promptTemplate);

        $executions = $promptTemplate->executions()
            ->with('promptSystem')
            ->latest()
            ->paginate(15);

        return response()->json($executions);
    }

    public function stats(PromptTemplate $promptTemplate): JsonResponse
    {
        $this->authorize('view', $promptTemplate);

        $executions = $promptTemplate->executions();

        $stats = [
            'total_executions' => $executions->count(),
            'successful_executions' => $executions->where('status', 'success')->count(),
            'failed_executions' => $executions->where('status', 'failed')->count(),
            'average_execution_time' => $executions->where('status', 'success')
                ->whereNotNull('execution_time_ms')
                ->avg('execution_time_ms'),
            'total_cost' => $executions->where('status', 'success')
                ->whereNotNull('cost_usd')
                ->sum('cost_usd'),
            'total_tokens' => $executions->where('status', 'success')
                ->whereNotNull('tokens_used')
                ->sum('tokens_used'),
            'recent_executions' => $executions->latest()
                ->limit(10)
                ->get(['id', 'execution_id', 'status', 'created_at', 'execution_time_ms']),
        ];

        return response()->json($stats);
    }
}
