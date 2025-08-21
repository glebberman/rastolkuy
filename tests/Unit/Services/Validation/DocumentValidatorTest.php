<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Services\Validation\DocumentValidator;
use App\Services\Validation\DTOs\ValidationResult;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

final class DocumentValidatorTest extends TestCase
{
    private DocumentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new DocumentValidator();
    }

    public function testValidatesGoodLegalDocument(): void
    {
        $legalContent = 'Данный договор заключается между сторонами. ' .
                       'Права и обязанности сторон определяются настоящим соглашением. ' .
                       str_repeat('Дополнительный текст договора. ', 20);

        $file = UploadedFile::fake()->createWithContent(
            'contract.txt',
            $legalContent,
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
        $this->assertIsArray($executedValidators);
        $this->assertContains('file_format', $executedValidators);
        $this->assertContains('file_size', $executedValidators);
        $this->assertContains('security', $executedValidators);
        $this->assertContains('content', $executedValidators);
    }

    public function testRejectsFileWithFormatIssues(): void
    {
        // Create a file with wrong extension
        $file = UploadedFile::fake()->create('bad.exe', 1); // .exe extension, small size

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);

        // Should contain errors from format validator (critical validator stops here)
        $errorText = implode(' ', $result->errors);
        $this->assertStringContainsString('not allowed', $errorText); // From format validator
        // Note: Size validator won't run because format validator is critical and fails first
    }

    public function testRejectsFileWithSizeIssues(): void
    {
        // Create a file with correct extension but too small size - below 1KB minimum
        $file = UploadedFile::fake()->createWithContent('small.pdf', 'tiny'); // PDF extension, but tiny size (4 bytes)

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);

        // Should contain errors from size validator
        $errorText = implode(' ', $result->errors);
        $this->assertStringContainsString('too small', $errorText); // From size validator
    }

    public function testStopsOnCriticalValidatorFailure(): void
    {
        // Mock a file that will fail the critical security validator
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('../../../malicious.pdf');
        $file->method('getSize')->willReturn(1024); // 1KB, above minimum
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getClientOriginalExtension')->willReturn('pdf');
        $file->method('getPathname')->willReturn(tempnam(sys_get_temp_dir(), 'test'));

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);

        // Should contain path traversal error
        $errorText = implode(' ', $result->errors);
        $this->assertStringContainsString('path traversal', $errorText);
    }

    public function testContinuesOnNonCriticalValidatorFailure(): void
    {
        // Create a text file with non-legal content (should warn but not stop validation)
        $nonLegalContent = str_repeat('Hello world! Random text without any keywords. ', 30); // Make it long enough
        $file = UploadedFile::fake()->createWithContent(
            'regular.txt',
            $nonLegalContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid); // Should still pass
        $this->assertNotEmpty($result->warnings); // But with warnings

        // All validators should have been executed
        $executedValidators = $result->metadata['validators_executed'];
        $this->assertIsArray($executedValidators);
        $this->assertCount(4, $executedValidators);
    }

    public function testHandlesValidatorExceptions(): void
    {
        // Create a mock validator that throws an exception
        $mockValidator = $this->createMock(\App\Services\Validation\Contracts\ValidatorInterface::class);
        $mockValidator->method('getName')->willReturn('mock_validator');
        $mockValidator->method('supports')->willReturn(true);
        $mockValidator->method('validate')->willThrowException(new RuntimeException('Test exception'));

        // Add the mock validator
        $this->validator->addValidator($mockValidator);

        $file = UploadedFile::fake()->create('test.txt', 1000);
        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertNotEmpty($result->errors);

        // Should contain the exception message
        $errorText = implode(' ', $result->errors);
        $this->assertStringContainsString('Test exception', $errorText);
    }

    public function testMergesResultsCorrectly(): void
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

    public function testValidationResultHelperMethods(): void
    {
        $validResult = ValidationResult::valid(['meta' => 'data']);
        $this->assertTrue($validResult->isValid);
        $this->assertFalse($validResult->hasErrors());
        $this->assertFalse($validResult->hasWarnings());
        $this->assertNull($validResult->getFirstError());

        $invalidResult = ValidationResult::invalid(
            ['error1', 'error2'],
            ['warning1'],
            ['meta' => 'data'],
        );
        $this->assertFalse($invalidResult->isValid);
        $this->assertTrue($invalidResult->hasErrors());
        $this->assertTrue($invalidResult->hasWarnings());
        $this->assertEquals('error1', $invalidResult->getFirstError());
    }

    public function testGetsSupportedExtensions(): void
    {
        $extensions = $this->validator->getSupportedExtensions();

        $this->assertIsArray($extensions);
        $this->assertContains('pdf', $extensions);
        $this->assertContains('docx', $extensions);
        $this->assertContains('txt', $extensions);
    }

    public function testGetsMaxFileSize(): void
    {
        $maxSize = $this->validator->getMaxFileSize();

        $this->assertIsInt($maxSize);
        $this->assertGreaterThan(0, $maxSize);
    }

    public function testCanAddCustomValidator(): void
    {
        $customValidator = $this->createMock(\App\Services\Validation\Contracts\ValidatorInterface::class);
        $customValidator->method('getName')->willReturn('custom');
        $customValidator->method('supports')->willReturn(true);
        $customValidator->method('validate')->willReturn(ValidationResult::valid(['custom' => 'result']));

        $this->validator->addValidator($customValidator);

        // Create a file with proper content to pass all validators
        $legalContent = 'Данный договор заключается между сторонами. ' .
                       'Права и обязанности сторон определяются настоящим соглашением. ' .
                       str_repeat('Дополнительный текст договора. ', 20);

        $file = UploadedFile::fake()->createWithContent('test.txt', $legalContent);
        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertArrayHasKey('custom', $result->metadata);
        $this->assertEquals('result', $result->metadata['custom']);
    }

    public function testLogsValidationProcess(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 1000);

        // This test mainly ensures logging doesn't break validation
        $result = $this->validator->validate($file);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('total_execution_time_ms', $result->metadata);
    }
}
