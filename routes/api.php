<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DocumentProcessingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', static function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Document Processing API Routes
|--------------------------------------------------------------------------
|
| API для обработки документов с поддержкой асинхронной обработки
| через очереди и отслеживанием статуса.
|
*/

Route::prefix('v1/documents')->group(function () {
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
    Route::prefix('admin')->group(function () {
        // Список всех обработок с фильтрацией и пагинацией
        Route::get('/', [DocumentProcessingController::class, 'index'])
            ->name('api.documents.admin.index');
        
        // Статистика обработок
        Route::get('stats', [DocumentProcessingController::class, 'stats'])
            ->name('api.documents.admin.stats');
    });
});
