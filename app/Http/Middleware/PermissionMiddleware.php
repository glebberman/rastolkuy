<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Необходима авторизация для доступа к этому ресурсу',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user has any of the required permissions
        $hasRequiredPermission = false;

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                $hasRequiredPermission = true;
                break;
            }
        }

        if (!$hasRequiredPermission) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => 'У вас недостаточно прав для доступа к этому ресурсу',
                'required_permissions' => $permissions,
                'user_permissions' => $user->getPermissionNames()->toArray(),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
