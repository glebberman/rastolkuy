<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePromptFeedbackRequest;
use App\Http\Requests\Api\UpdatePromptFeedbackRequest;
use App\Http\Resources\PromptFeedbackResource;
use App\Models\PromptFeedback;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptFeedbackController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PromptFeedback::class);

        $query = PromptFeedback::query()->with('promptExecution');

        if ($request->has('prompt_execution_id')) {
            $query->where('prompt_execution_id', $request->get('prompt_execution_id'));
        }

        if ($request->has('feedback_type')) {
            $query->where('feedback_type', $request->get('feedback_type'));
        }

        if ($request->has('user_type')) {
            $query->where('user_type', $request->get('user_type'));
        }

        if ($request->has('rating_min')) {
            $ratingMin = $request->get('rating_min');

            if (is_numeric($ratingMin)) {
                $query->where('rating', '>=', (float) $ratingMin);
            }
        }

        if ($request->has('rating_max')) {
            $ratingMax = $request->get('rating_max');

            if (is_numeric($ratingMax)) {
                $query->where('rating', '<=', (float) $ratingMax);
            }
        }

        if ($request->has('search')) {
            $search = $request->get('search');

            if (is_string($search)) {
                $query->where(function ($q) use ($search): void {
                    $q->where('comment', 'like', "%{$search}%")
                        ->orWhere('user_id', 'like', "%{$search}%");
                });
            }
        }

        $perPage = $request->get('per_page', 15);
        $feedback = $query->latest()
            ->paginate(is_numeric($perPage) ? (int) $perPage : 15);

        return PromptFeedbackResource::collection($feedback);
    }

    public function store(StorePromptFeedbackRequest $request): PromptFeedbackResource
    {
        $this->authorize('create', PromptFeedback::class);

        $feedback = PromptFeedback::create($request->validated());

        return new PromptFeedbackResource($feedback->load('promptExecution'));
    }

    public function show(PromptFeedback $promptFeedback): PromptFeedbackResource
    {
        $this->authorize('view', $promptFeedback);

        return new PromptFeedbackResource($promptFeedback->load('promptExecution'));
    }

    public function update(UpdatePromptFeedbackRequest $request, PromptFeedback $promptFeedback): PromptFeedbackResource
    {
        $this->authorize('update', $promptFeedback);

        $promptFeedback->update($request->validated());

        $freshFeedback = $promptFeedback->fresh(['promptExecution']);

        return new PromptFeedbackResource($freshFeedback ?? $promptFeedback);
    }

    public function destroy(PromptFeedback $promptFeedback): JsonResponse
    {
        $this->authorize('delete', $promptFeedback);

        $promptFeedback->delete();

        return response()->json([
            'message' => 'Обратная связь успешно удалена',
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PromptFeedback::class);

        $query = PromptFeedback::query();

        if ($request->has('feedback_type')) {
            $query->where('feedback_type', $request->get('feedback_type'));
        }

        if ($request->has('user_type')) {
            $query->where('user_type', $request->get('user_type'));
        }

        $stats = [
            'total_feedback' => $query->count(),
            'average_rating' => $query->whereNotNull('rating')->avg('rating'),
            'rating_distribution' => [
                '1_star' => $query->clone()->whereBetween('rating', [0, 1])->count(),
                '2_star' => $query->clone()->whereBetween('rating', [1.01, 2])->count(),
                '3_star' => $query->clone()->whereBetween('rating', [2.01, 3])->count(),
                '4_star' => $query->clone()->whereBetween('rating', [3.01, 4])->count(),
                '5_star' => $query->clone()->whereBetween('rating', [4.01, 5])->count(),
            ],
            'feedback_by_type' => PromptFeedback::query()
                ->selectRaw('feedback_type, COUNT(*) as count, AVG(rating) as average_rating')
                ->groupBy('feedback_type')
                ->get(),
            'feedback_by_user_type' => PromptFeedback::query()
                ->selectRaw('user_type, COUNT(*) as count, AVG(rating) as average_rating')
                ->whereNotNull('user_type')
                ->groupBy('user_type')
                ->get(),
            'recent_feedback' => PromptFeedback::query()
                ->with('promptExecution:id,execution_id,status')
                ->latest()
                ->limit(10)
                ->get(['id', 'feedback_type', 'rating', 'created_at', 'prompt_execution_id']),
        ];

        return response()->json($stats);
    }

    public function executionFeedback(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PromptFeedback::class);

        $executionId = $request->get('execution_id');

        if (!is_string($executionId)) {
            return response()->json([
                'success' => false,
                'error' => 'Идентификатор выполнения обязателен',
            ], 400);
        }

        $feedback = PromptFeedback::query()
            ->whereHas('promptExecution', function ($q) use ($executionId): void {
                $q->where('execution_id', $executionId);
            })
            ->with('promptExecution')
            ->get();

        return response()->json([
            'success' => true,
            'data' => PromptFeedbackResource::collection($feedback),
        ]);
    }
}
