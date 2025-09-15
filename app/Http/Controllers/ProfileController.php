<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Api\ChangePasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    /**
     * Update user password.
     */
    public function updatePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'error' => 'Unauthenticated',
                    'message' => 'Пользователь не аутентифицирован',
                ], ResponseAlias::HTTP_UNAUTHORIZED);
            }

            $this->authService->changePassword($user, $request);

            Log::info('Password changed successfully via web', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Пароль успешно изменен',
                'success' => true,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Неверный текущий пароль',
                'errors' => $e->errors(),
            ], ResponseAlias::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Exception $e) {
            Log::error('Password change failed via web', [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Password change failed',
                'message' => 'Не удалось изменить пароль',
            ], ResponseAlias::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}