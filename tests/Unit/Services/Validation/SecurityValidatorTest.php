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

    public function testValidatesSafeFile(): void
    {
        $file = UploadedFile::fake()->create('safe_document.pdf', 1000);

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertArrayHasKey('original_filename', $result->metadata);
        $this->assertArrayHasKey('security_scan_timestamp', $result->metadata);
    }

    public function testRejectsFileWithPathTraversal(): void
    {
        // Mock the uploaded file to return a malicious filename
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('../../../etc/passwd.pdf');
        $file->method('getPathname')->willReturn(tempnam(sys_get_temp_dir(), 'test'));

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('path traversal', $result->errors[0]);
    }

    public function testRejectsFileWithNullBytes(): void
    {
        $content = 'Safe content';
        $file = UploadedFile::fake()->createWithContent(
            "file\0name.pdf",
            $content,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('null bytes', $result->errors[0]);
    }

    public function testRejectsFileWithTooLongName(): void
    {
        $longName = str_repeat('a', 300) . '.pdf';
        $file = UploadedFile::fake()->create($longName, 1000);

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('too long', $result->errors[0]);
    }

    public function testDetectsMaliciousJavascriptContent(): void
    {
        $maliciousContent = 'javascript:alert("xss")';
        $file = UploadedFile::fake()->createWithContent(
            'document.txt',
            $maliciousContent,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('malicious content', $result->errors[0]);
    }

    public function testDetectsScriptTagsInContent(): void
    {
        $maliciousContent = '<script>alert("xss")</script>';
        $file = UploadedFile::fake()->createWithContent(
            'document.txt',
            $maliciousContent,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('malicious content', $result->errors[0]);
    }

    public function testWarnsAboutSuspiciousBinaryInTxt(): void
    {
        // Create a .txt file with binary content
        $binaryContent = "\x89PNG\r\n\x1a\n"; // PNG header
        $file = UploadedFile::fake()->createWithContent(
            'document.txt',
            $binaryContent,
        );

        $result = $this->validator->validate($file);

        // Should be valid but with warnings
        $this->assertTrue($result->isValid);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('binary content', $result->warnings[0]);
    }

    public function testValidatesProperPdfHeader(): void
    {
        $pdfContent = '%PDF-1.4' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            $pdfContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->warnings);
    }

    public function testWarnsAboutInvalidPdfHeader(): void
    {
        $invalidPdfContent = 'NOT A PDF' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            $invalidPdfContent,
        );

        $result = $this->validator->validate($file);

        // Should be valid but with warnings
        $this->assertTrue($result->isValid);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('binary content', $result->warnings[0]);
    }

    public function testValidatesProperDocxHeader(): void
    {
        $docxContent = 'PK' . str_repeat('a', 1000); // ZIP header
        $file = UploadedFile::fake()->createWithContent(
            'document.docx',
            $docxContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->warnings);
    }

    public function testSupportsAllFiles(): void
    {
        $file = UploadedFile::fake()->create('test.pdf', 1000);

        $this->assertTrue($this->validator->supports($file));
    }

    public function testHasCorrectName(): void
    {
        $this->assertEquals('security', $this->validator->getName());
    }

    public function testHandlesUnreadableFile(): void
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
