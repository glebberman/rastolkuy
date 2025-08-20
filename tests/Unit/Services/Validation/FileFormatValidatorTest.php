<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Services\Validation\Validators\FileFormatValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class FileFormatValidatorTest extends TestCase
{
    private FileFormatValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FileFormatValidator();
    }

    public function test_validates_valid_pdf_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'application/pdf');
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertEquals('pdf', $result->metadata['extension']);
        $this->assertEquals('application/pdf', $result->metadata['mime_type']);
    }

    public function test_validates_valid_docx_file(): void
    {
        $file = UploadedFile::fake()->create(
            'document.docx', 
            1000, 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertEquals('docx', $result->metadata['extension']);
    }

    public function test_validates_valid_txt_file(): void
    {
        $file = UploadedFile::fake()->create('document.txt', 1000, 'text/plain');
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertEquals('txt', $result->metadata['extension']);
        $this->assertEquals('text/plain', $result->metadata['mime_type']);
    }

    public function test_rejects_invalid_extension(): void
    {
        $file = UploadedFile::fake()->create('document.exe', 1000, 'application/x-executable');
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(2, $result->errors); // Both extension and MIME type errors
        $this->assertStringContainsString('extension', $result->errors[0]);
        $this->assertStringContainsString('MIME type', $result->errors[1]);
    }

    public function test_rejects_invalid_mime_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'text/html');
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('MIME type', $result->errors[0]);
    }

    public function test_rejects_file_without_extension(): void
    {
        $file = UploadedFile::fake()->create('document', 1000, 'text/plain');
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('valid extension', $result->errors[0]);
    }

    public function test_warns_about_extension_mime_mismatch(): void
    {
        // Create a file with PDF extension but text/plain MIME type
        $file = UploadedFile::fake()->create('document.pdf', 1000, 'text/plain');
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid); // Should be valid but with warnings
        $this->assertEmpty($result->errors); // No errors
        $this->assertCount(1, $result->warnings); // But should have warnings
        $this->assertStringContainsString('does not match', $result->warnings[0]);
    }

    public function test_supports_all_files(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 1000);
        
        $this->assertTrue($this->validator->supports($file));
    }

    public function test_has_correct_name(): void
    {
        $this->assertEquals('file_format', $this->validator->getName());
    }
}