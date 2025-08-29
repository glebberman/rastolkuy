<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('credit_transactions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ID пользователя');
            $table->string('type', 50)->comment('Тип транзакции: topup, debit, refund');
            $table->decimal('amount', 15, 2)->comment('Сумма транзакции (положительная для пополнения, отрицательная для списания)');
            $table->decimal('balance_before', 15, 2)->comment('Баланс до транзакции');
            $table->decimal('balance_after', 15, 2)->comment('Баланс после транзакции');
            $table->string('description')->comment('Описание транзакции');
            $table->json('metadata')->nullable()->comment('Дополнительные данные (JSON)');
            $table->string('reference_id')->nullable()->comment('Внешний ID для связи с другими объектами');
            $table->string('reference_type')->nullable()->comment('Тип связанного объекта');
            $table->timestamps();

            // Индексы для оптимизации запросов
            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
