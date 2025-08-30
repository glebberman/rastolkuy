<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DocumentProcessingController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
|
| Все API маршруты приложения Растолкуй версии v1.
| Используется плоская структура без групповых префиксов.
| Разделены на публичные и защищенные маршруты.
| Все маршруты имеют именование через точку для удобства использования.
|
*/

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

// Загрузка документа для обработки
Route::post('v1/documents', [DocumentProcessingController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:documents.create'])
    ->name('api.v1.documents.store');

// Получение статуса обработки документа по UUID
Route::get('v1/documents/{uuid}/status', [DocumentProcessingController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('api.v1.documents.status');

// Получение результата обработки документа по UUID
Route::get('v1/documents/{uuid}/result', [DocumentProcessingController::class, 'result'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('api.v1.documents.result');

// Отмена обработки документа (если в статусе pending)
Route::post('v1/documents/{uuid}/cancel', [DocumentProcessingController::class, 'cancel'])
    ->middleware(['auth:sanctum', 'permission:documents.cancel'])
    ->where('uuid', '[0-9a-f-]{36}')
    ->name('api.v1.documents.cancel');

// Удаление записи об обработке документа
Route::delete('v1/documents/{uuid}', [DocumentProcessingController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'permission:documents.delete'])
    ->where('uuid', '[0-9a-f-]{36}')
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

// =============================================================================
// BACKWARD COMPATIBILITY REDIRECTS
// =============================================================================
// 
// Старые маршруты перенаправляются на новые v1 с deprecation warnings
// Планируется к удалению через 6 месяцев
//

// Auth redirects
Route::post('auth/register', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/auth/register',
        'new_route' => '/api/v1/auth/register',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.auth.register');
})->name('api.deprecated.auth.register');

Route::post('auth/login', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/auth/login', 
        'new_route' => '/api/v1/auth/login',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.auth.login');
})->name('api.deprecated.auth.login');

Route::post('auth/logout', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/auth/logout',
        'new_route' => '/api/v1/auth/logout', 
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.auth.logout');
})->name('api.deprecated.auth.logout');

Route::get('auth/user', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/auth/user',
        'new_route' => '/api/v1/auth/user',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.auth.user');
})->name('api.deprecated.auth.user');

// Credits redirects
Route::get('user/credits/balance', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/user/credits/balance',
        'new_route' => '/api/v1/credits/balance',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.credits.balance');
})->name('api.deprecated.credits.balance');

Route::get('user/credits/statistics', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/user/credits/statistics',
        'new_route' => '/api/v1/credits/statistics',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.credits.statistics');
})->name('api.deprecated.credits.statistics');

Route::get('user/credits/history', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/user/credits/history',
        'new_route' => '/api/v1/credits/history',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.credits.history');
})->name('api.deprecated.credits.history');

Route::post('user/credits/topup', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/user/credits/topup',
        'new_route' => '/api/v1/credits/topup',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.credits.topup');
})->name('api.deprecated.credits.topup');

Route::post('credits/convert-usd', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/credits/convert-usd',
        'new_route' => '/api/v1/credits/convert-usd',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.credits.convert-usd');
})->name('api.deprecated.credits.convert-usd');

Route::post('user/credits/check-balance', function() {
    Log::warning('Deprecated API route used', [
        'old_route' => '/api/user/credits/check-balance',
        'new_route' => '/api/v1/credits/check-balance',
        'ip' => request()->ip()
    ]);
    return redirect()->route('api.v1.credits.check-balance');
})->name('api.deprecated.credits.check-balance');