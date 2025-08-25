<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function __construct(
        protected RateLimiter $limiter,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttemptsInt = (int) $maxAttempts;
        $decayMinutesInt = (int) $decayMinutes;

        if ($this->limiter->tooManyAttempts($key, $maxAttemptsInt)) {
            return $this->buildTooManyAttemptsResponse($key, $maxAttemptsInt, $decayMinutesInt);
        }

        $this->limiter->hit($key, $decayMinutesInt * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttemptsInt,
            $this->calculateRemainingAttempts($key, $maxAttemptsInt),
        );
    }

    /**
     * Resolve request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = Auth::id();

        if ($userId) {
            // For authenticated users, limit by user ID
            return 'user_' . $userId;
        }

        // For anonymous users, limit by IP
        return 'ip_' . $request->ip();
    }

    /**
     * Build too many attempts response.
     */
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts, int $decayMinutes): JsonResponse
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'error' => 'Too Many Requests',
            'message' => 'Превышен лимит запросов. Попробуйте позже.',
            'retry_after' => $retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS)
            ->header('Retry-After', (string) $retryAfter)
            ->header('X-RateLimit-Limit', (string) $maxAttempts)
            ->header('X-RateLimit-Remaining', '0')
            ->header('X-RateLimit-Reset', (string) (time() + $retryAfter));
    }

    /**
     * Calculate remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        $attempts = $this->limiter->attempts($key);

        return (int) max(0, $maxAttempts - $attempts);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => (string) $maxAttempts,
            'X-RateLimit-Remaining' => (string) $remainingAttempts,
        ]);

        return $response;
    }
}
