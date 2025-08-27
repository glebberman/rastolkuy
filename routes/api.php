<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentProcessingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Все API маршруты приложения Legal Translator.
| Сгруппированы по типу доступа: публичные и защищенные.
| Каждый маршрут описан от корня для удобства сопровождения.
|
*/

// =============================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ (не требуют авторизации)
// =============================================================================

// -----------------------------------------------------------------------------
// РЕГИСТРАЦИЯ И АУТЕНТИФИКАЦИЯ
// -----------------------------------------------------------------------------

// Регистрация нового пользователя
Route::post('auth/register', [AuthController::class, 'register'])
    ->middleware('custom.throttle:5,1')
    ->name('api.auth.register');

// Вход в систему
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('custom.throttle:10,1')
    ->name('api.auth.login');

// Запрос сброса пароля
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('custom.throttle:3,1')
    ->name('api.auth.forgot-password');

// Сброс пароля по токену
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('custom.throttle:5,1')
    ->name('password.reset');

// -----------------------------------------------------------------------------
// ПОДТВЕРЖДЕНИЕ EMAIL (требует подписанную ссылку)
// -----------------------------------------------------------------------------

// Подтверждение email по подписанной ссылке
Route::get('auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

// =============================================================================
// ЗАЩИЩЕННЫЕ МАРШРУТЫ (требуют авторизации через Sanctum)
// =============================================================================

// -----------------------------------------------------------------------------
// УПРАВЛЕНИЕ СЕССИЕЙ И ПРОФИЛЕМ
// -----------------------------------------------------------------------------

// Выход из системы
Route::post('auth/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.logout');

// Получение данных текущего пользователя
Route::get('auth/user', [AuthController::class, 'user'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.user');

// Обновление профиля пользователя
Route::put('auth/user', [AuthController::class, 'updateUser'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.update-user');

// Обновление токена доступа
Route::post('auth/refresh', [AuthController::class, 'refreshToken'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.refresh');

// Повторная отправка письма подтверждения
Route::post('auth/resend-verification', [AuthController::class, 'resendVerification'])
    ->middleware(['auth:sanctum', 'custom.throttle:3,1'])
    ->name('api.auth.resend-verification');

// -----------------------------------------------------------------------------
// ЗАГРУЗКА И ОБРАБОТКА ДОКУМЕНТОВ
// -----------------------------------------------------------------------------

// Загрузка документа для обработки
Route::post('v1/documents', [DocumentProcessingController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:documents.create'])
    ->name('api.documents.store');

// Получение статуса обработки документа по UUID
Route::get('v1/documents/{uuid}/status', [DocumentProcessingController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('api.documents.status');

// Получение результата обработки документа по UUID
Route::get('v1/documents/{uuid}/result', [DocumentProcessingController::class, 'result'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('api.documents.result');

// -----------------------------------------------------------------------------
// УПРАВЛЕНИЕ ДОКУМЕНТАМИ
// -----------------------------------------------------------------------------

// Отмена обработки документа (если в статусе pending)
Route::post('v1/documents/{uuid}/cancel', [DocumentProcessingController::class, 'cancel'])
    ->middleware(['auth:sanctum', 'permission:documents.cancel'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('api.documents.cancel');

// Удаление записи об обработке документа
Route::delete('v1/documents/{uuid}', [DocumentProcessingController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'permission:documents.delete'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('api.documents.destroy');

// -----------------------------------------------------------------------------
// АДМИНИСТРАТИВНЫЕ ФУНКЦИИ (только для администраторов)
// -----------------------------------------------------------------------------

// Список всех обработок с фильтрацией и пагинацией
Route::get('v1/documents/admin', [DocumentProcessingController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.documents.admin.index');

// Получение статистики по обработкам документов
Route::get('v1/documents/admin/stats', [DocumentProcessingController::class, 'stats'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.documents.admin.stats');
