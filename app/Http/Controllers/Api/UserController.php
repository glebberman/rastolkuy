<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignRoleRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['roles', 'permissions']);

        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request): void {
                $q->where('name', $request->get('role'));
            });
        }

        if ($request->has('search')) {
            $search = $request->get('search');

            if (is_string($search)) {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }
        }

        if ($request->has('email_verified')) {
            $verified = $request->boolean('email_verified');

            if ($verified) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        $perPage = $request->get('per_page', 15);
        $users = $query->withCount('documents')
            ->orderBy('name')
            ->paginate(is_numeric($perPage) ? (int) $perPage : 15);

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['roles', 'permissions'])->loadCount('documents'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        if (isset($data['password']) && is_string($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return new UserResource($user->fresh(['roles', 'permissions']));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json([
            'message' => '>;L7>20B5;L CA?5H=> C40;5=',
        ]);
    }

    public function assignRole(AssignRoleRequest $request, User $user): UserResource
    {
        $this->authorize('assignRole', $user);

        $validatedData = $request->validated();
        $roleName = $validatedData['role'] ?? '';

        if (is_string($roleName) && !$user->hasRole($roleName)) {
            $user->assignRole($roleName);
        }

        return new UserResource($user->fresh(['roles', 'permissions']));
    }

    public function removeRole(AssignRoleRequest $request, User $user): UserResource
    {
        $this->authorize('removeRole', $user);

        $validatedData = $request->validated();
        $roleName = $validatedData['role'] ?? '';

        if (is_string($roleName) && $user->hasRole($roleName)) {
            $user->removeRole($roleName);
        }

        return new UserResource($user->fresh(['roles', 'permissions']));
    }

    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $stats = [
            'total_users' => User::count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
            'users_by_role' => User::query()
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', User::class)
                ->selectRaw('roles.name as role, COUNT(*) as count')
                ->groupBy('roles.name')
                ->get(),
            'recent_registrations' => User::latest()
                ->limit(10)
                ->get(['id', 'name', 'email', 'created_at']),
            'active_users_last_30_days' => User::where('updated_at', '>=', now()->subDays(30))->count(),
        ];

        return response()->json($stats);
    }

    public function documents(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $documents = $user->documents()
            ->latest()
            ->paginate(15, ['id', 'uuid', 'filename', 'status', 'created_at']);

        return response()->json($documents);
    }
}
