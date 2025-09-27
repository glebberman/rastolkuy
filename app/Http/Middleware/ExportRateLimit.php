<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware для ограничения частоты экспорта документов.
 */
class ExportRateLimit
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'error' => 'Authentication required',
                'message' => 'Требуется аутентификация',
            ], 401);
        }

        // Определяем лимиты для пользователя
        $hourlyLimit = $this->getHourlyLimit($user);
        $dailyLimit = $this->getDailyLimit($user);

        // Проверяем почасовой лимит
        $hourlyKey = 'export_rate_limit_hourly:' . $user->id;
        $executed = RateLimiter::attempt(
            $hourlyKey,
            $hourlyLimit,
            function (): void {
                // Пустая функция, мы просто проверяем лимит
            },
            3600, // 1 час в секундах
        );

        if (!$executed) {
            $remainingSeconds = RateLimiter::availableIn($hourlyKey);

            return response()->json([
                'error' => 'Rate limit exceeded',
                'message' => "Превышен лимит экспортов в час ({$hourlyLimit}). Попробуйте через " . $this->formatTime($remainingSeconds),
                'retry_after' => $remainingSeconds,
                'limits' => [
                    'hourly' => $hourlyLimit,
                    'daily' => $dailyLimit,
                ],
            ], 429);
        }

        // Проверяем дневной лимит
        $dailyKey = 'export_rate_limit_daily:' . $user->id;
        $dailyExecuted = RateLimiter::attempt(
            $dailyKey,
            $dailyLimit,
            function (): void {
                // Пустая функция, мы просто проверяем лимит
            },
            86400, // 24 часа в секундах
        );

        if (!$dailyExecuted) {
            // Сбрасываем почасовой счетчик, так как дневной лимит уже превышен
            RateLimiter::clear($hourlyKey);

            $remainingSeconds = RateLimiter::availableIn($dailyKey);

            return response()->json([
                'error' => 'Daily limit exceeded',
                'message' => "Превышен дневной лимит экспортов ({$dailyLimit}). Обновите тарифный план или попробуйте завтра",
                'retry_after' => $remainingSeconds,
                'limits' => [
                    'hourly' => $hourlyLimit,
                    'daily' => $dailyLimit,
                ],
            ], 429);
        }

        // Добавляем информацию о лимитах в заголовки ответа
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->headers->set('X-RateLimit-Hourly-Limit', (string) $hourlyLimit);
            $response->headers->set('X-RateLimit-Daily-Limit', (string) $dailyLimit);
            $response->headers->set('X-RateLimit-Hourly-Remaining', (string) RateLimiter::remaining($hourlyKey, $hourlyLimit));
            $response->headers->set('X-RateLimit-Daily-Remaining', (string) RateLimiter::remaining($dailyKey, $dailyLimit));
        }

        return $response;
    }

    /**
     * Получает лимит экспортов в час для пользователя.
     */
    private function getHourlyLimit(\App\Models\User $user): int
    {
        /** @var array<string, int> $limits */
        $limits = config('export.rate_limits.per_hour', []);

        if ($user->hasRole('admin')) {
            return (int) ($limits['enterprise'] ?? 500);
        }

        if ($user->hasRole('pro')) {
            return (int) ($limits['pro'] ?? 100);
        }

        if ($user->hasRole('customer')) {
            return (int) ($limits['basic'] ?? 20);
        }

        return (int) ($limits['guest'] ?? 3);
    }

    /**
     * Получает лимит экспортов в день для пользователя.
     */
    private function getDailyLimit(\App\Models\User $user): int
    {
        /** @var array<string, int> $limits */
        $limits = config('export.rate_limits.per_day', []);

        if ($user->hasRole('admin')) {
            return (int) ($limits['enterprise'] ?? 5000);
        }

        if ($user->hasRole('pro')) {
            return (int) ($limits['pro'] ?? 1000);
        }

        if ($user->hasRole('customer')) {
            return (int) ($limits['basic'] ?? 100);
        }

        return (int) ($limits['guest'] ?? 5);
    }

    /**
     * Форматирует время ожидания в читаемый вид.
     */
    private function formatTime(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} сек";
        }

        $minutes = (int) ($seconds / 60);

        if ($minutes < 60) {
            return "{$minutes} мин";
        }

        $hours = (int) ($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0 ? "{$hours} ч {$remainingMinutes} мин" : "{$hours} ч";
        }

        $days = (int) ($hours / 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0 ? "{$days} дн {$remainingHours} ч" : "{$days} дн";
    }
}
