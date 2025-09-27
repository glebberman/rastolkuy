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
        Schema::create('document_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_processing_id')->constrained()->onDelete('cascade');
            $table->enum('format', ['html', 'docx', 'pdf']);
            $table->string('filename');
            $table->string('file_path', 1000);
            $table->unsignedInteger('file_size');
            $table->string('download_token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['document_processing_id', 'format']);
            $table->index('download_token');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_exports');
    }
};
