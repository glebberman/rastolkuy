<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Api;

use App\Http\Requests\Api\PreviewPromptRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PreviewPromptRequestTest extends TestCase
{
    public function testValidationPassesWithValidData(): void
    {
        $request = new PreviewPromptRequest();
        $data = [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'system_name' => 'document_translation',
            'template_name' => 'translate_legal_document',
            'task_type' => 'translation',
            'options' => ['key' => 'value'],
        ];

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function testValidationPassesWithMinimalData(): void
    {
        $request = new PreviewPromptRequest();
        $data = [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function testValidationFailsWithInvalidUuid(): void
    {
        $request = new PreviewPromptRequest();
        $data = [
            'uuid' => 'invalid-uuid',
        ];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('uuid', $validator->errors()->toArray());
    }

    public function testValidationFailsWithMissingUuid(): void
    {
        $request = new PreviewPromptRequest();
        $data = [];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('uuid', $validator->errors()->toArray());
    }

    public function testValidationFailsWithInvalidTaskType(): void
    {
        $request = new PreviewPromptRequest();
        $data = [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'task_type' => 'invalid_task_type',
        ];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('task_type', $validator->errors()->toArray());
    }

    public function testValidationFailsWithInvalidOptions(): void
    {
        $request = new PreviewPromptRequest();
        $data = [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'options' => 'not_an_array',
        ];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('options', $validator->errors()->toArray());
    }

    public function testAuthorizationReturnsTrue(): void
    {
        $request = new PreviewPromptRequest();

        $this->assertTrue($request->authorize());
    }

    public function testHasCustomMessages(): void
    {
        $request = new PreviewPromptRequest();
        $messages = $request->messages();

        $this->assertIsArray($messages);
        $this->assertArrayHasKey('uuid.required', $messages);
        $this->assertArrayHasKey('uuid.uuid', $messages);
        $this->assertArrayHasKey('task_type.in', $messages);
    }
}