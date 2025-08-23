<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePromptSystemRequest;
use App\Http\Requests\Api\UpdatePromptSystemRequest;
use App\Http\Resources\PromptSystemResource;
use App\Models\PromptSystem;
use App\Services\Prompt\DTOs\CreatePromptSystemData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptSystemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PromptSystem::query();

        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');

            if (is_string($search)) {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
        }

        $perPage = $request->get('per_page', 15);
        $systems = $query->withCount(['templates', 'executions'])
            ->orderBy('name')
            ->paginate(is_numeric($perPage) ? (int) $perPage : 15);

        return PromptSystemResource::collection($systems);
    }

    public function store(StorePromptSystemRequest $request): PromptSystemResource
    {
        $data = CreatePromptSystemData::fromRequest($request);

        $system = PromptSystem::create([
            'name' => $data->name,
            'type' => $data->type,
            'description' => $data->description,
            'system_prompt' => $data->systemPrompt,
            'default_parameters' => $data->defaultParameters,
            'schema' => $data->schema,
            'is_active' => $data->isActive,
            'version' => $data->version,
            'metadata' => $data->metadata,
        ]);

        return new PromptSystemResource($system->loadCount(['templates', 'executions']));
    }

    public function show(PromptSystem $promptSystem): PromptSystemResource
    {
        return new PromptSystemResource(
            $promptSystem->loadCount(['templates', 'executions']),
        );
    }

    public function update(UpdatePromptSystemRequest $request, PromptSystem $promptSystem): PromptSystemResource
    {
        $promptSystem->update($request->validated());

        $freshPromptSystem = $promptSystem->fresh(['templates', 'executions']);

        if ($freshPromptSystem) {
            $freshPromptSystem->loadCount(['templates', 'executions']);
        }

        return new PromptSystemResource($freshPromptSystem ?? $promptSystem);
    }

    public function destroy(PromptSystem $promptSystem): JsonResponse
    {
        $promptSystem->delete();

        return response()->json([
            'message' => 'Система промптов успешно удалена',
        ]);
    }

    public function templates(PromptSystem $promptSystem): JsonResponse
    {
        $templates = $promptSystem->templates()
            ->with(['executions' => function ($query): void {
                $query->latest()->limit(5);
            }])
            ->withCount('executions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'templates' => $templates,
        ]);
    }

    public function stats(PromptSystem $promptSystem): JsonResponse
    {
        $executions = $promptSystem->executions();

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

    public function toggle(PromptSystem $promptSystem): PromptSystemResource
    {
        $promptSystem->update([
            'is_active' => !$promptSystem->is_active,
        ]);

        return new PromptSystemResource($promptSystem->fresh());
    }
}
