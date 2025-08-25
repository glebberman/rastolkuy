<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_processings', static function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique()->comment('Уникальный идентификатор задачи');
            $table->string('original_filename')->comment('Оригинальное название файла');
            $table->string('file_path')->comment('Путь к загруженному файлу');
            $table->string('file_type', 100)->comment('MIME тип файла');
            $table->unsignedBigInteger('file_size')->comment('Размер файла в байтах');
            $table->string('task_type', 50)->comment('Тип задачи: translation, contradiction, ambiguity');
            $table->json('options')->nullable()->comment('Опции обработки (JSON)');
            $table->boolean('anchor_at_start')->default(false)->comment('Позиция якорей: true = начало, false = конец');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('Статус обработки');
            $table->longText('result')->nullable()->comment('Результат обработки');
            $table->json('error_details')->nullable()->comment('Детали ошибки (JSON)');
            $table->json('processing_metadata')->nullable()->comment('Метаданные обработки (JSON)');
            $table->decimal('processing_time_seconds', 8, 3)->nullable()->comment('Время обработки в секундах');
            $table->decimal('cost_usd', 10, 6)->nullable()->comment('Стоимость обработки в USD');
            $table->timestamp('started_at')->nullable()->comment('Время начала обработки');
            $table->timestamp('completed_at')->nullable()->comment('Время завершения обработки');
            $table->timestamps();
            $table->softDeletes();

            // Индексы для оптимизации запросов
            $table->index('uuid');
            $table->index('status');
            $table->index('task_type');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_processings');
    }
};
