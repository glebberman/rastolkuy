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
        Schema::create('prompt_systems', static function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('type'); // 'translation', 'contradiction', 'ambiguity', 'general'
            $table->text('description')->nullable();
            $table->text('system_prompt');
            $table->json('default_parameters')->nullable();
            $table->json('schema')->nullable(); // JSON schema for response validation
            $table->boolean('is_active')->default(true);
            $table->string('version', 10)->default('1.0.0');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'is_active']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_systems');
    }
};
