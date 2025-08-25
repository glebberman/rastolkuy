<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentProcessingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication API Routes
|--------------------------------------------------------------------------
|
| API endpoints for user authentication, registration and profile management
|
*/

Route::prefix('auth')->group(function (): void {
    // Public auth routes with stricter rate limiting
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('custom.throttle:5,1') // 5 requests per minute
        ->name('api.auth.register');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('custom.throttle:10,1') // 10 requests per minute
        ->name('api.auth.login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('custom.throttle:3,1') // 3 requests per minute
        ->name('api.auth.forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('custom.throttle:5,1') // 5 requests per minute
        ->name('password.reset');

    // Email verification routes
    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Protected auth routes with standard rate limiting
    Route::middleware(['auth:sanctum', 'custom.throttle:60,1'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('user', [AuthController::class, 'user'])->name('api.auth.user');
        Route::put('user', [AuthController::class, 'updateUser'])->name('api.auth.update-user');
        Route::post('refresh', [AuthController::class, 'refreshToken'])->name('api.auth.refresh');
        Route::post('resend-verification', [AuthController::class, 'resendVerification'])
            ->middleware('custom.throttle:3,1') // Stricter limit for verification resend
            ->name('api.auth.resend-verification');
    });
});

/*
|--------------------------------------------------------------------------
| Document Processing API Routes
|--------------------------------------------------------------------------
|
| API для обработки документов с поддержкой асинхронной обработки
| через очереди и отслеживанием статуса.
|
*/

Route::prefix('v1/documents')->group(function (): void {
    // Загрузить документ для обработки
    Route::post('/', [DocumentProcessingController::class, 'store'])
        ->name('api.documents.store');

    // Получить статус обработки по UUID
    Route::get('{uuid}/status', [DocumentProcessingController::class, 'show'])
        ->name('api.documents.status')
        ->where('uuid', '[0-9a-f-]{36}');

    // Получить результат обработки по UUID
    Route::get('{uuid}/result', [DocumentProcessingController::class, 'result'])
        ->name('api.documents.result')
        ->where('uuid', '[0-9a-f-]{36}');

    // Отменить обработку документа (если pending)
    Route::post('{uuid}/cancel', [DocumentProcessingController::class, 'cancel'])
        ->name('api.documents.cancel')
        ->where('uuid', '[0-9a-f-]{36}');

    // Удалить запись об обработке
    Route::delete('{uuid}', [DocumentProcessingController::class, 'destroy'])
        ->name('api.documents.destroy')
        ->where('uuid', '[0-9a-f-]{36}');

    // Административные роуты
    Route::prefix('admin')->group(function (): void {
        // Список всех обработок с фильтрацией и пагинацией
        Route::get('/', [DocumentProcessingController::class, 'index'])
            ->name('api.documents.admin.index');

        // Статистика обработок
        Route::get('stats', [DocumentProcessingController::class, 'stats'])
            ->name('api.documents.admin.stats');
    });
});
