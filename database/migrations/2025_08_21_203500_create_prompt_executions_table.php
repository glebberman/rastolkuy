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
        Schema::create('prompt_executions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_system_id')->constrained('prompt_systems')->onDelete('cascade');
            $table->foreignId('prompt_template_id')->nullable()->constrained('prompt_templates')->onDelete('set null');
            $table->string('execution_id')->unique(); // UUID for tracking
            $table->text('rendered_prompt'); // Final prompt sent to LLM
            $table->longText('llm_response')->nullable(); // Response from LLM
            $table->json('input_variables'); // Variables used for rendering
            $table->string('model_used', 100)->nullable(); // Claude model version
            $table->integer('tokens_used')->nullable();
            $table->decimal('execution_time_ms', 10, 2)->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->string('status', 50)->default('pending'); // pending, success, failed, timeout
            $table->text('error_message')->nullable();
            $table->json('quality_metrics')->nullable(); // Response quality metrics
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['prompt_system_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('execution_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_executions');
    }
};
