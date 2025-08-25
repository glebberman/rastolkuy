<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Необходима авторизация для доступа к этому ресурсу',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if user has any of the required roles
        $hasRequiredRole = false;

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                $hasRequiredRole = true;
                break;
            }
        }

        if (!$hasRequiredRole) {
            return response()->json([
                'error' => 'Insufficient permissions',
                'message' => 'У вас недостаточно прав для доступа к этому ресурсу',
                'required_roles' => $roles,
                'user_roles' => $user->getRoleNames()->toArray(),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
