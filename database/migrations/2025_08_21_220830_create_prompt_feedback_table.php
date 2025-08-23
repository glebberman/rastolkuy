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
        Schema::create('prompt_feedback', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_execution_id')->constrained('prompt_executions')->onDelete('cascade');
            $table->string('feedback_type', 50); // 'rating', 'correction', 'improvement', 'issue'
            $table->decimal('rating', 3, 2)->nullable(); // 0.00 to 5.00
            $table->text('comment')->nullable();
            $table->json('details')->nullable(); // Additional structured feedback
            $table->string('user_type', 50)->default('anonymous'); // 'user', 'expert', 'system', 'anonymous'
            $table->string('user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['prompt_execution_id', 'feedback_type']);
            $table->index(['feedback_type', 'rating']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_feedback');
    }
};
