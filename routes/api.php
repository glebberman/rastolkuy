<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DocumentProcessingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Все API маршруты приложения Растолкуй.
| Включает как новые v1 маршруты, так и legacy маршруты для совместимости.
| Разделены на публичные и защищенные маршруты.
| Все маршруты имеют именование через точку для удобства использования.
|
*/

// =============================================================================
// LEGACY API ROUTES (для обратной совместимости с фронтендом)
// =============================================================================

// Регистрация нового пользователя (legacy)
Route::post('auth/register', [AuthController::class, 'register'])
    ->middleware('custom.throttle:5,1')
    ->name('api.auth.register');

// Вход в систему (legacy)
Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('custom.throttle:10,1')
    ->name('api.auth.login');

// Запрос сброса пароля (legacy)
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('custom.throttle:3,1')
    ->name('api.auth.forgot-password');

// Сброс пароля по токену (legacy)
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('custom.throttle:5,1')
    ->name('api.auth.reset-password');

// Выход из системы (legacy)
Route::post('auth/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.logout');

// Получение данных текущего пользователя (legacy)
Route::get('auth/user', [AuthController::class, 'user'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.user');

// Обновление профиля пользователя (legacy)
Route::put('auth/user', [AuthController::class, 'updateUser'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.update-user');

// Обновление токена доступа (legacy)
Route::post('auth/refresh', [AuthController::class, 'refreshToken'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.auth.refresh');

// Повторная отправка письма подтверждения (legacy)
Route::post('auth/resend-verification', [AuthController::class, 'resendVerification'])
    ->middleware(['auth:sanctum', 'custom.throttle:3,1'])
    ->name('api.auth.resend-verification');

// =============================================================================
// ПУБЛИЧНЫЕ МАРШРУТЫ v1 (не требуют авторизации)
// =============================================================================

// -----------------------------------------------------------------------------
// РЕГИСТРАЦИЯ И АУТЕНТИФИКАЦИЯ
// -----------------------------------------------------------------------------

// Регистрация нового пользователя
Route::post('v1/auth/register', [AuthController::class, 'register'])
    ->middleware('custom.throttle:5,1')
    ->name('api.v1.auth.register');

// Вход в систему
Route::post('v1/auth/login', [AuthController::class, 'login'])
    ->middleware('custom.throttle:10,1')
    ->name('api.v1.auth.login');

// Запрос сброса пароля
Route::post('v1/auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('custom.throttle:3,1')
    ->name('api.v1.auth.forgot-password');

// Сброс пароля по токену
Route::post('v1/auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('custom.throttle:5,1')
    ->name('api.v1.auth.reset-password');

// Подтверждение email по подписанной ссылке
Route::get('v1/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('api.v1.auth.verify-email');

// Laravel email verification system expects this route name
Route::get('v1/auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

// =============================================================================
// ЗАЩИЩЕННЫЕ МАРШРУТЫ v1 (требуют авторизации через Sanctum)
// =============================================================================

// -----------------------------------------------------------------------------
// УПРАВЛЕНИЕ СЕССИЕЙ И ПРОФИЛЕМ
// -----------------------------------------------------------------------------

// Выход из системы
Route::post('v1/auth/logout', [AuthController::class, 'logout'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.auth.logout');

// Получение данных текущего пользователя
Route::get('v1/auth/user', [AuthController::class, 'user'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.auth.user');

// Обновление профиля пользователя
Route::put('v1/auth/user', [AuthController::class, 'updateUser'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.auth.update-user');

// Обновление токена доступа
Route::post('v1/auth/refresh', [AuthController::class, 'refreshToken'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.auth.refresh');

// Повторная отправка письма подтверждения
Route::post('v1/auth/resend-verification', [AuthController::class, 'resendVerification'])
    ->middleware(['auth:sanctum', 'custom.throttle:3,1'])
    ->name('api.v1.auth.resend-verification');

// Получение статистики пользователя
Route::get('v1/user/stats', [AuthController::class, 'stats'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.user.stats');

// -----------------------------------------------------------------------------
// УПРАВЛЕНИЕ КРЕДИТАМИ
// -----------------------------------------------------------------------------

// Получение баланса кредитов
Route::get('v1/credits/balance', [CreditController::class, 'balance'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.credits.balance');

// Получение статистики кредитов
Route::get('v1/credits/statistics', [CreditController::class, 'statistics'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.credits.statistics');

// Получение истории транзакций
Route::get('v1/credits/history', [CreditController::class, 'history'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.credits.history');

// Пополнение кредитов (только для разработки)
Route::post('v1/credits/topup', [CreditController::class, 'topup'])
    ->middleware(['auth:sanctum', 'custom.throttle:10,1'])
    ->name('api.v1.credits.topup');

// Конвертация USD в кредиты
Route::post('v1/credits/convert-usd', [CreditController::class, 'convertUsdToCredits'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.credits.convert-usd');

// Проверка достаточности баланса
Route::post('v1/credits/check-balance', [CreditController::class, 'checkSufficientBalance'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.credits.check-balance');

// -----------------------------------------------------------------------------
// УПРАВЛЕНИЕ ДОКУМЕНТАМИ
// -----------------------------------------------------------------------------

// Загрузка только файла (без обработки)
Route::post('v1/documents/upload', [DocumentProcessingController::class, 'upload'])
    ->middleware(['auth:sanctum', 'permission:documents.create'])
    ->name('api.v1.documents.upload');

// Получение предварительной оценки стоимости
Route::post('v1/documents/{uuid}/estimate', [DocumentProcessingController::class, 'estimate'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->name('api.v1.documents.estimate');

// Запуск обработки оцененного документа
Route::post('v1/documents/{uuid}/process', [DocumentProcessingController::class, 'process'])
    ->middleware(['auth:sanctum', 'permission:documents.create'])
    ->name('api.v1.documents.process');

// Загрузка документа для обработки (старый метод для обратной совместимости)
Route::post('v1/documents', [DocumentProcessingController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:documents.create'])
    ->name('api.v1.documents.store');

// Получение статуса обработки документа по UUID
Route::get('v1/documents/{uuid}/status', [DocumentProcessingController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->name('api.v1.documents.status');

// Получение результата обработки документа по UUID
Route::get('v1/documents/{uuid}/result', [DocumentProcessingController::class, 'result'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->name('api.v1.documents.result');

// Отмена обработки документа (если в статусе pending)
Route::post('v1/documents/{uuid}/cancel', [DocumentProcessingController::class, 'cancel'])
    ->middleware(['auth:sanctum', 'permission:documents.cancel'])
    ->name('api.v1.documents.cancel');

// Удаление записи об обработке документа
Route::delete('v1/documents/{uuid}', [DocumentProcessingController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'permission:documents.delete'])
    ->name('api.v1.documents.destroy');

// -----------------------------------------------------------------------------
// АДМИНИСТРАТИВНЫЕ ФУНКЦИИ (только для администраторов)
// -----------------------------------------------------------------------------

// Список всех обработок с фильтрацией и пагинацией
Route::get('v1/documents/admin', [DocumentProcessingController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.v1.documents.admin.index');

// Получение статистики по обработкам документов
Route::get('v1/documents/admin/stats', [DocumentProcessingController::class, 'stats'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.v1.documents.admin.stats');
