<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\UpdateUserRequest;
use App\Http\Resources\Api\UserResource;
use App\Services\AuditService;
use App\Services\AuthService;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class AuthController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AuthService $authService,
        private readonly AuditService $auditService,
    ) {
    }

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $this->authorize('auth.register');

        try {
            $user = $this->authService->register($request);

            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Пользователь успешно зарегистрирован. Проверьте email для подтверждения.',
                'data' => new UserResource($user),
            ], ResponseAlias::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Registration failed', [
                'email' => $request->validated('email') ?? 'unknown',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Registration failed',
                'message' => 'Не удалось зарегистрировать пользователя',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Login user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $this->authorize('auth.login');

        try {
            $result = $this->authService->login($request);

            Log::info('User logged in successfully', [
                'user_id' => $result['user']->id,
                'email' => $result['user']->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Вход выполнен успешно',
                'data' => [
                    'user' => new UserResource($result['user']),
                    'token' => $result['token'],
                ],
            ]);
        } catch (ValidationException $e) {
            Log::warning('Login validation failed', [
                'email' => $request->validated('email'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Invalid credentials',
                'message' => 'Неверный email или пароль',
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Login failed with exception', [
                'email' => $request->validated('email'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Login failed',
                'message' => 'Не удалось выполнить вход',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authorize('auth.logout');

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Пользователь не аутентифицирован',
            ], ResponseAlias::HTTP_UNAUTHORIZED);
        }

        // Delete current token
        if ($user->currentAccessToken() !== null) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Выход выполнен успешно',
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        $this->authorize('auth.user');

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Пользователь не аутентифицирован',
            ], ResponseAlias::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'message' => 'Данные пользователя',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateUser(UpdateUserRequest $request): JsonResponse
    {
        $this->authorize('auth.updateUser');

        try {
            $user = $this->authService->updateUser($request);

            /** @var \App\Models\User $currentUser */
            $currentUser = $request->user();
            $this->auditService->logUserDataAccess($currentUser, $user, 'profile_update');

            return response()->json([
                'message' => 'Профиль обновлен успешно',
                'data' => new UserResource($user),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Update failed',
                'message' => 'Не удалось обновить профиль',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Send password reset email.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authorize('auth.forgotPassword');

        try {
            $this->authService->sendPasswordResetEmail($request);

            return response()->json([
                'message' => 'Ссылка для сброса пароля отправлена на ваш email',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Reset email failed',
                'message' => 'Не удалось отправить письмо для сброса пароля',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reset password.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authorize('auth.resetPassword');

        try {
            $this->authService->resetPassword($request);

            return response()->json([
                'message' => 'Пароль успешно изменен',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Invalid token',
                'message' => 'Недействительный или истекший токен сброса',
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Password reset failed',
                'message' => 'Не удалось изменить пароль',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify email.
     */
    public function verifyEmail(EmailVerificationRequest $request): JsonResponse
    {
        $this->authorize('auth.verifyEmail');

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Пользователь не аутентифицирован',
            ], ResponseAlias::HTTP_UNAUTHORIZED);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email уже подтвержден',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email успешно подтвержден',
        ]);
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $this->authorize('auth.resendVerification');

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Пользователь не аутентифицирован',
            ], ResponseAlias::HTTP_UNAUTHORIZED);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'Already verified',
                'message' => 'Email уже подтвержден',
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Письмо подтверждения отправлено повторно',
        ]);
    }

    /**
     * Refresh token.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $this->authorize('auth.refreshToken');

        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Пользователь не аутентифицирован',
                ], ResponseAlias::HTTP_UNAUTHORIZED);
            }

            $result = $this->authService->refreshToken($user);

            return response()->json([
                'message' => 'Токен обновлен успешно',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $result['token'],
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Token refresh failed',
                'message' => 'Не удалось обновить токен',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get user statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('auth.stats');

        try {
            /** @var \App\Models\User|null $user */
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Пользователь не аутентифицирован',
                ], ResponseAlias::HTTP_UNAUTHORIZED);
            }

            $stats = $this->authService->getUserStats($user);

            return response()->json([
                'message' => 'Статистика пользователя',
                'data' => $stats,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get user stats', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Stats retrieval failed',
                'message' => 'Не удалось получить статистику пользователя',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
