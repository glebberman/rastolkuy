<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentProcessingController;
use App\Http\Controllers\Api\PromptExecutionController;
use App\Http\Controllers\Api\PromptFeedbackController;
use App\Http\Controllers\Api\PromptSystemController;
use App\Http\Controllers\Api\PromptTemplateController;
use App\Http\Controllers\Api\UserController;
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

// -----------------------------------------------------------------------------
// СИСТЕМЫ ПРОМПТОВ (только для администраторов)
// -----------------------------------------------------------------------------

// Список всех систем промптов с фильтрацией
Route::get('v1/prompt-systems', [PromptSystemController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.index');

// Создание новой системы промптов
Route::post('v1/prompt-systems', [PromptSystemController::class, 'store'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.store');

// Получение системы промптов по ID
Route::get('v1/prompt-systems/{promptSystem}', [PromptSystemController::class, 'show'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.show');

// Обновление системы промптов
Route::put('v1/prompt-systems/{promptSystem}', [PromptSystemController::class, 'update'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.update');

// Удаление системы промптов
Route::delete('v1/prompt-systems/{promptSystem}', [PromptSystemController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.destroy');

// Переключение активности системы промптов
Route::patch('v1/prompt-systems/{promptSystem}/toggle', [PromptSystemController::class, 'toggle'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.toggle');

// Получение шаблонов системы промптов
Route::get('v1/prompt-systems/{promptSystem}/templates', [PromptSystemController::class, 'templates'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.templates');

// Получение статистики системы промптов
Route::get('v1/prompt-systems/{promptSystem}/stats', [PromptSystemController::class, 'stats'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-systems.stats');

// -----------------------------------------------------------------------------
// ВЫПОЛНЕНИЕ ПРОМПТОВ (для всех авторизованных пользователей с правами)
// -----------------------------------------------------------------------------

// Список выполнений промптов с фильтрацией
Route::get('v1/prompt-executions', [PromptExecutionController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:system.view-logs'])
    ->name('api.prompt-executions.index');

// Выполнение промпта
Route::post('v1/prompt-executions/execute', [PromptExecutionController::class, 'execute'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-executions.execute');

// Рендеринг промпта без выполнения
Route::post('v1/prompt-executions/render', [PromptExecutionController::class, 'render'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-executions.render');

// Валидация промпта
Route::post('v1/prompt-executions/validate', [PromptExecutionController::class, 'validate'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-executions.validate');

// Получение информации о выполнении по execution_id
Route::get('v1/prompt-executions/{executionId}', [PromptExecutionController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:system.view-logs'])
    ->where('executionId', '[0-9a-f-]{36}')
    ->name('api.prompt-executions.show');

// Получение статистики выполнений
Route::get('v1/prompt-executions/stats', [PromptExecutionController::class, 'stats'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-executions.stats');

// Получение систем по типу
Route::get('v1/prompt-executions/systems-by-type', [PromptExecutionController::class, 'systemsByType'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-executions.systems-by-type');

// Получение шаблонов системы
Route::get('v1/prompt-executions/templates', [PromptExecutionController::class, 'templates'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-executions.templates');

// -----------------------------------------------------------------------------
// ШАБЛОНЫ ПРОМПТОВ (только для администраторов)
// -----------------------------------------------------------------------------

// Список всех шаблонов промптов с фильтрацией
Route::get('v1/prompt-templates', [PromptTemplateController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.index');

// Создание нового шаблона промпта
Route::post('v1/prompt-templates', [PromptTemplateController::class, 'store'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.store');

// Получение шаблона промпта по ID
Route::get('v1/prompt-templates/{promptTemplate}', [PromptTemplateController::class, 'show'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.show');

// Обновление шаблона промпта
Route::put('v1/prompt-templates/{promptTemplate}', [PromptTemplateController::class, 'update'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.update');

// Удаление шаблона промпта
Route::delete('v1/prompt-templates/{promptTemplate}', [PromptTemplateController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.destroy');

// Переключение активности шаблона промпта
Route::patch('v1/prompt-templates/{promptTemplate}/toggle', [PromptTemplateController::class, 'toggle'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.toggle');

// Получение выполнений шаблона промпта
Route::get('v1/prompt-templates/{promptTemplate}/executions', [PromptTemplateController::class, 'executions'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.executions');

// Получение статистики шаблона промпта
Route::get('v1/prompt-templates/{promptTemplate}/stats', [PromptTemplateController::class, 'stats'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-templates.stats');

// -----------------------------------------------------------------------------
// ОБРАТНАЯ СВЯЗЬ ПРОМПТОВ (только для администраторов)
// -----------------------------------------------------------------------------

// Список всей обратной связи с фильтрацией
Route::get('v1/prompt-feedback', [PromptFeedbackController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:system.view-logs'])
    ->name('api.prompt-feedback.index');

// Создание новой обратной связи
Route::post('v1/prompt-feedback', [PromptFeedbackController::class, 'store'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-feedback.store');

// Получение обратной связи по ID
Route::get('v1/prompt-feedback/{promptFeedback}', [PromptFeedbackController::class, 'show'])
    ->middleware(['auth:sanctum', 'permission:system.view-logs'])
    ->name('api.prompt-feedback.show');

// Обновление обратной связи
Route::put('v1/prompt-feedback/{promptFeedback}', [PromptFeedbackController::class, 'update'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-feedback.update');

// Удаление обратной связи
Route::delete('v1/prompt-feedback/{promptFeedback}', [PromptFeedbackController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.prompt-feedback.destroy');

// Получение статистики обратной связи
Route::get('v1/prompt-feedback/stats', [PromptFeedbackController::class, 'stats'])
    ->middleware(['auth:sanctum', 'permission:system.view-logs'])
    ->name('api.prompt-feedback.stats');

// Получение обратной связи по execution_id
Route::get('v1/prompt-feedback/by-execution', [PromptFeedbackController::class, 'executionFeedback'])
    ->middleware(['auth:sanctum', 'permission:system.view-logs'])
    ->name('api.prompt-feedback.by-execution');

// -----------------------------------------------------------------------------
// УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ (только для администраторов)
// -----------------------------------------------------------------------------

// Список всех пользователей с фильтрацией
Route::get('v1/users', [UserController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.index');

// Получение пользователя по ID
Route::get('v1/users/{user}', [UserController::class, 'show'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.show');

// Обновление пользователя
Route::put('v1/users/{user}', [UserController::class, 'update'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.update');

// Удаление пользователя
Route::delete('v1/users/{user}', [UserController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.destroy');

// Назначение роли пользователю
Route::post('v1/users/{user}/assign-role', [UserController::class, 'assignRole'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.assign-role');

// Снятие роли с пользователя
Route::post('v1/users/{user}/remove-role', [UserController::class, 'removeRole'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.remove-role');

// Получение статистики пользователей
Route::get('v1/users/admin/stats', [UserController::class, 'stats'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.stats');

// Получение документов пользователя
Route::get('v1/users/{user}/documents', [UserController::class, 'documents'])
    ->middleware(['auth:sanctum', 'role:admin'])
    ->name('api.users.documents');
