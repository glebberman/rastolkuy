<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\UploadDocumentRequest;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final readonly class UploadDocumentDto
{
    public function __construct(
        public UploadedFile $file,
        public string $taskType,
        public bool $anchorAtStart,
        public array $options,
    ) {
    }

    public static function fromRequest(UploadDocumentRequest $request): self
    {
        /** @var array{task_type: string, anchor_at_start?: bool, options?: array<string, mixed>} $validated */
        $validated = $request->validated();

        $file = $request->file('file');

        if ($file === null) {
            throw new InvalidArgumentException('File is required');
        }

        return new self(
            file: $file,
            taskType: $validated['task_type'],
            anchorAtStart: $validated['anchor_at_start'] ?? false,
            options: $validated['options'] ?? [],
        );
    }
}
