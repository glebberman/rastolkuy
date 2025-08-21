<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Services\Validation\Validators\FileSizeValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class FileSizeValidatorTest extends TestCase
{
    private FileSizeValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FileSizeValidator();
    }

    public function testValidatesFileWithinSizeLimits(): void
    {
        // 5MB file (well within 10MB limit)
        $file = UploadedFile::fake()->create('document.pdf', 5120);

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertArrayHasKey('file_size', $result->metadata);
        $this->assertArrayHasKey('file_size_human', $result->metadata);
    }

    public function testRejectsFileTooLarge(): void
    {
        // 15MB file (exceeds 10MB limit)
        $file = UploadedFile::fake()->create('document.pdf', 15360);

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('too large', $result->errors[0]);
        $this->assertStringContainsString('Maximum size', $result->errors[0]);
    }

    public function testRejectsFileTooSmall(): void
    {
        // 512 bytes file (below 1KB minimum)
        $file = UploadedFile::fake()->createWithContent('document.pdf', str_repeat('a', 512));

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('too small', $result->errors[0]);
        $this->assertStringContainsString('Minimum size', $result->errors[0]);
    }

    public function testWarnsAboutLargeFiles(): void
    {
        // 8.5MB file (85% of 10MB limit, should trigger warning)
        $file = UploadedFile::fake()->create('document.pdf', 8704);

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('quite large', $result->warnings[0]);
    }

    public function testFormatsBytesCorrectly(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 2048); // 2MB

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertIsString($result->metadata['file_size_human']);
        $this->assertStringContainsString('MB', $result->metadata['file_size_human']);
    }

    public function testSupportsAllFiles(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 1000);

        $this->assertTrue($this->validator->supports($file));
    }

    public function testHasCorrectName(): void
    {
        $this->assertEquals('file_size', $this->validator->getName());
    }

    public function testHandlesFalseFileSize(): void
    {
        // Mock a file that returns false for getSize()
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(false);

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Could not determine file size', $result->errors[0]);
    }
}
