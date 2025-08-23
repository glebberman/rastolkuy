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
        Schema::create('prompt_templates', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_system_id')->constrained('prompt_systems')->onDelete('cascade');
            $table->string('name');
            $table->text('template'); // Template content with placeholders
            $table->json('required_variables'); // Array of required template variables
            $table->json('optional_variables')->nullable(); // Array of optional template variables
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['prompt_system_id', 'name']);
            $table->index(['prompt_system_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
