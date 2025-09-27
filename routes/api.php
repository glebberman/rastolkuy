<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DocumentExportController;
use App\Http\Controllers\Api\DocumentProcessingController;
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

// Смена пароля
Route::put('v1/auth/change-password', [AuthController::class, 'changePassword'])
    ->middleware(['auth:sanctum', 'custom.throttle:5,1'])
    ->name('api.v1.auth.change-password');

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

// Курсы обмена валют
Route::get('v1/credits/rates', [CreditController::class, 'exchangeRates'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.credits.rates');

// Стоимость кредитов в валютах
Route::get('v1/credits/costs', [CreditController::class, 'creditCosts'])
    ->middleware(['auth:sanctum', 'custom.throttle:60,1'])
    ->name('api.v1.credits.costs');

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

// Получение документа с разметкой якорями (без LLM обработки)
Route::get('v1/documents/{uuid}/markup', [DocumentProcessingController::class, 'markup'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->name('api.v1.documents.markup');

// Предварительный просмотр промпта без отправки в LLM (для тестирования)
Route::post('v1/documents/{uuid}/preview-prompt', [DocumentProcessingController::class, 'previewPrompt'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->name('api.v1.documents.preview-prompt');

// Отмена обработки документа (если в статусе pending)
Route::post('v1/documents/{uuid}/cancel', [DocumentProcessingController::class, 'cancel'])
    ->middleware(['auth:sanctum', 'permission:documents.cancel'])
    ->name('api.v1.documents.cancel');

// Удаление записи об обработке документа
Route::delete('v1/documents/{uuid}', [DocumentProcessingController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'permission:documents.delete'])
    ->name('api.v1.documents.destroy');

// Список документов пользователя
Route::get('v1/documents', [DocumentProcessingController::class, 'userIndex'])
    ->middleware(['auth:sanctum', 'permission:documents.view'])
    ->name('api.v1.documents.index');

// -----------------------------------------------------------------------------
// ЭКСПОРТ ДОКУМЕНТОВ
// -----------------------------------------------------------------------------

// Получение списка доступных форматов экспорта
Route::get('v1/export/formats', [DocumentExportController::class, 'formats'])
    ->middleware(['custom.throttle:60,1'])
    ->name('api.v1.export.formats');

// Экспорт документа в указанный формат
Route::post('v1/export', [DocumentExportController::class, 'export'])
    ->middleware(['auth:sanctum', 'permission:documents.export', 'export.rate_limit'])
    ->name('api.v1.export.create');

// Скачивание экспортированного документа по токену (публичный доступ)
Route::get('v1/export/download/{token}', [DocumentExportController::class, 'download'])
    ->middleware(['custom.throttle:60,1'])
    ->name('api.export.download');

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
