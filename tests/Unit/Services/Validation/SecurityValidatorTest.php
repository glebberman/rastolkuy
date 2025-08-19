<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Services\Validation\Validators\SecurityValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class SecurityValidatorTest extends TestCase
{
    private SecurityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SecurityValidator();
    }

    public function test_validates_safe_file(): void
    {
        $file = UploadedFile::fake()->create('safe_document.pdf', 1000);
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertArrayHasKey('original_filename', $result->metadata);
        $this->assertArrayHasKey('security_scan_timestamp', $result->metadata);
    }

    public function test_rejects_file_with_path_traversal(): void
    {
        $content = 'Safe content';
        $file = UploadedFile::fake()->createWithContent(
            '../../../etc/passwd.pdf',
            $content
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('path traversal', $result->errors[0]);
    }

    public function test_rejects_file_with_null_bytes(): void
    {
        $content = 'Safe content';
        $file = UploadedFile::fake()->createWithContent(
            "file\0name.pdf",
            $content
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('null bytes', $result->errors[0]);
    }

    public function test_rejects_file_with_too_long_name(): void
    {
        $longName = str_repeat('a', 300) . '.pdf';
        $file = UploadedFile::fake()->create($longName, 1000);
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('too long', $result->errors[0]);
    }

    public function test_detects_malicious_javascript_content(): void
    {
        $maliciousContent = 'javascript:alert("xss")';
        $file = UploadedFile::fake()->createWithContent(
            'document.txt',
            $maliciousContent
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('malicious content', $result->errors[0]);
    }

    public function test_detects_script_tags_in_content(): void
    {
        $maliciousContent = '<script>alert("xss")</script>';
        $file = UploadedFile::fake()->createWithContent(
            'document.txt',
            $maliciousContent
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('malicious content', $result->errors[0]);
    }

    public function test_warns_about_suspicious_binary_in_txt(): void
    {
        // Create a .txt file with binary content
        $binaryContent = "\x89PNG\r\n\x1a\n"; // PNG header
        $file = UploadedFile::fake()->createWithContent(
            'document.txt',
            $binaryContent
        );
        
        $result = $this->validator->validate($file);
        
        // Should be valid but with warnings
        $this->assertTrue($result->isValid);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('binary content', $result->warnings[0]);
    }

    public function test_validates_proper_pdf_header(): void
    {
        $pdfContent = '%PDF-1.4' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            $pdfContent
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->warnings);
    }

    public function test_warns_about_invalid_pdf_header(): void
    {
        $invalidPdfContent = 'NOT A PDF' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            $invalidPdfContent
        );
        
        $result = $this->validator->validate($file);
        
        // Should be valid but with warnings
        $this->assertTrue($result->isValid);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('binary content', $result->warnings[0]);
    }

    public function test_validates_proper_docx_header(): void
    {
        $docxContent = 'PK' . str_repeat('a', 1000); // ZIP header
        $file = UploadedFile::fake()->createWithContent(
            'document.docx',
            $docxContent
        );
        
        $result = $this->validator->validate($file);
        
        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->warnings);
    }

    public function test_supports_all_files(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 1000);
        
        $this->assertTrue($this->validator->supports($file));
    }

    public function test_has_correct_name(): void
    {
        $this->assertEquals('security', $this->validator->getName());
    }

    public function test_handles_unreadable_file(): void
    {
        // Mock a file that can't be read
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('test.pdf');
        $file->method('getPathname')->willReturn('/nonexistent/path');
        
        $result = $this->validator->validate($file);
        
        // Should still be valid even if content can't be read
        $this->assertTrue($result->isValid);
        $this->assertArrayHasKey('original_filename', $result->metadata);
    }
}