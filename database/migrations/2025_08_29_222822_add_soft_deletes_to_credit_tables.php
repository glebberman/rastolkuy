<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->softDeletes()->comment('Мягкое удаление для аудита');
        });

        Schema::table('user_credits', function (Blueprint $table): void {
            $table->softDeletes()->comment('Мягкое удаление для аудита');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('user_credits', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
