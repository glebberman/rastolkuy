<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Services\Validation\DocumentValidator;
use App\Services\Validation\DTOs\ValidationResult;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class DocumentValidatorTest extends TestCase
{
    private DocumentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DocumentValidator();
    }

    public function test_validates_good_legal_document(): void
    {
        $legalContent = 'Данный договор заключается между сторонами. ' .
                       'Права и обязанности сторон определяются настоящим соглашением. ' .
                       str_repeat('Дополнительный текст договора. ', 20);
        
        $file = UploadedFile::fake()->createWithContent(
            'contract.txt',
            strlen($legalContent),
            $legalContent
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        
        // Check execution metadata
        $this->assertArrayHasKey('total_execution_time_ms', $result->metadata);
        $this->assertArrayHasKey('validators_executed', $result->metadata);
        $this->assertArrayHasKey('validator_results', $result->metadata);
        
        // Should have executed all validators
        $executedValidators = $result->metadata['validators_executed'];
        $this->assertContains('file_format', $executedValidators);
        $this->assertContains('file_size', $executedValidators);
        $this->assertContains('security', $executedValidators);
        $this->assertContains('content', $executedValidators);
    }

    public function test_rejects_file_with_multiple_issues(): void
    {
        // Create a file with multiple issues: wrong extension and too small
        $file = UploadedFile::fake()->create('bad.exe', 0.1); // .exe extension, tiny size
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        
        // Should contain errors from multiple validators
        $errorText = implode(' ', $result->errors);
        $this->assertStringContainsString('not allowed', $errorText); // From format validator
        $this->assertStringContainsString('too small', $errorText); // From size validator
    }

    public function test_stops_on_critical_validator_failure(): void
    {
        // Create a file that will fail the critical security validator
        $file = UploadedFile::fake()->createWithContent(
            '../../../malicious.pdf',
            1000,
            'Safe content'
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        
        // Should contain path traversal error
        $errorText = implode(' ', $result->errors);
        $this->assertStringContainsString('path traversal', $errorText);
    }

    public function test_continues_on_non_critical_validator_failure(): void
    {
        // Create a text file with non-legal content (should warn but not stop validation)
        $nonLegalContent = str_repeat('This is just regular text. ', 20);
        $file = UploadedFile::fake()->createWithContent(
            'regular.txt',
            strlen($nonLegalContent),
            $nonLegalContent
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid); // Should still pass
        $this->assertNotEmpty($result->warnings); // But with warnings
        
        // All validators should have been executed
        $executedValidators = $result->metadata['validators_executed'];
        $this->assertCount(4, $executedValidators);
    }

    public function test_handles_validator_exceptions(): void
    {
        // Mock a file that could cause exceptions
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('test.pdf');
        $file->method('getSize')->willThrowException(new \RuntimeException('Test exception'));
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getClientOriginalExtension')->willReturn('pdf');
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);
        
        // Should contain the exception message
        $errorText = implode(' ', $result->errors);
        $this->assertStringContainsString('Test exception', $errorText);
    }

    public function test_merges_results_correctly(): void
    {
        $result1 = ValidationResult::valid(['key1' => 'value1']);
        $result2 = ValidationResult::invalid(['error2'], ['warning2'], ['key2' => 'value2']);
        
        $merged = $result1->merge($result2);
        
        $this->assertFalse($merged->isValid);
        $this->assertCount(1, $merged->errors);
        $this->assertCount(1, $merged->warnings);
        $this->assertEquals('error2', $merged->errors[0]);
        $this->assertEquals('warning2', $merged->warnings[0]);
        $this->assertEquals('value1', $merged->metadata['key1']);
        $this->assertEquals('value2', $merged->metadata['key2']);
    }

    public function test_validation_result_helper_methods(): void
    {
        $validResult = ValidationResult::valid(['meta' => 'data']);
        $this->assertTrue($validResult->isValid);
        $this->assertFalse($validResult->hasErrors());
        $this->assertFalse($validResult->hasWarnings());
        $this->assertNull($validResult->getFirstError());
        
        $invalidResult = ValidationResult::invalid(
            ['error1', 'error2'],
            ['warning1'],
            ['meta' => 'data']
        );
        $this->assertFalse($invalidResult->isValid);
        $this->assertTrue($invalidResult->hasErrors());
        $this->assertTrue($invalidResult->hasWarnings());
        $this->assertEquals('error1', $invalidResult->getFirstError());
    }

    public function test_gets_supported_extensions(): void
    {
        $extensions = $this->validator->getSupportedExtensions();
        
        $this->assertIsArray($extensions);
        $this->assertContains('pdf', $extensions);
        $this->assertContains('docx', $extensions);
        $this->assertContains('txt', $extensions);
    }

    public function test_gets_max_file_size(): void
    {
        $maxSize = $this->validator->getMaxFileSize();
        
        $this->assertIsInt($maxSize);
        $this->assertGreaterThan(0, $maxSize);
    }

    public function test_can_add_custom_validator(): void
    {
        $customValidator = $this->createMock(\App\Services\Validation\Contracts\ValidatorInterface::class);
        $customValidator->method('getName')->willReturn('custom');
        $customValidator->method('supports')->willReturn(true);
        $customValidator->method('validate')->willReturn(ValidationResult::valid(['custom' => 'result']));
        
        $this->validator->addValidator($customValidator);
        
        $file = UploadedFile::fake()->create('test.txt', 1000);
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertArrayHasKey('custom', $result->metadata);
        $this->assertEquals('result', $result->metadata['custom']);
    }

    public function test_logs_validation_process(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 1000);
        
        // This test mainly ensures logging doesn't break validation
        $result = $this->validator->validate($file);
        
        $this->assertNotNull($result);
        $this->assertArrayHasKey('total_execution_time_ms', $result->metadata);
    }
}