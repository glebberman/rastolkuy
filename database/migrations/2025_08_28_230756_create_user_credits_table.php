<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_credits', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('ID пользователя');
            $table->decimal('balance', 15, 2)->default(0.00)->comment('Текущий баланс кредитов');
            $table->timestamps();

            // Индексы для оптимизации запросов
            $table->unique('user_id')->comment('Каждый пользователь может иметь только один баланс');
            $table->index('balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_credits');
    }
};
