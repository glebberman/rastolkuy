<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Validation;

use App\Services\Validation\Validators\ContentValidator;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class ContentValidatorTest extends TestCase
{
    private ContentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ContentValidator();
    }

    public function testValidatesValidLegalTextFile(): void
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
        $this->assertArrayHasKey('content_length', $result->metadata);
        $this->assertArrayHasKey('legal_keyword_matches', $result->metadata);
        $this->assertGreaterThanOrEqual(2, $result->metadata['legal_keyword_matches']);
    }

    public function testValidatesEnglishLegalContent(): void
    {
        $legalContent = 'This contract is made between the parties. ' .
                       'Rights and obligations of the parties are defined by this agreement. ' .
                       str_repeat('Additional contract terms and conditions. ', 20);

        $file = UploadedFile::fake()->createWithContent(
            'contract.txt',
            $legalContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
        $this->assertGreaterThanOrEqual(2, $result->metadata['legal_keyword_matches']);
    }

    public function testRejectsContentTooShort(): void
    {
        $shortContent = 'Short text';
        $file = UploadedFile::fake()->createWithContent(
            'short.txt',
            $shortContent,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('too short', $result->errors[0]);
    }

    public function testRejectsContentTooLong(): void
    {
        // Create content longer than max limit (1MB)
        $longContent = str_repeat('a', 1500000); // 1.5MB
        $file = UploadedFile::fake()->createWithContent(
            'huge.txt',
            $longContent,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('too long', $result->errors[0]);
    }

    public function testWarnsAboutNonLegalContent(): void
    {
        $nonLegalContent = str_repeat('Hello world! This text has no legal keywords whatsoever. ', 10);
        $file = UploadedFile::fake()->createWithContent(
            'regular.txt',
            $nonLegalContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertCount(1, $result->warnings);
        $this->assertStringContainsString('may not be a legal document', $result->warnings[0]);
    }

    public function testRejectsGarbageContent(): void
    {
        $garbageContent = str_repeat('@#$%^&*()_+={}[]|\\:";\'<>?,./ ', 20); // Only special characters and symbols
        $file = UploadedFile::fake()->createWithContent(
            'garbage.txt',
            $garbageContent,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('unreadable content', $result->errors[0]);
    }

    public function testValidatesPdfPlaceholder(): void
    {
        $pdfContent = '%PDF-1.4' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'document.pdf',
            $pdfContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertArrayHasKey('content_length', $result->metadata);
        $this->assertGreaterThan(0, $result->metadata['legal_keyword_matches']);
    }

    public function testValidatesDocxPlaceholder(): void
    {
        $docxContent = 'PK' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'document.docx',
            $docxContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertArrayHasKey('content_length', $result->metadata);
        $this->assertGreaterThan(0, $result->metadata['legal_keyword_matches']);
    }

    public function testRejectsInvalidPdf(): void
    {
        $invalidPdfContent = 'NOT A PDF' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'invalid.pdf',
            $invalidPdfContent,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Could not extract', $result->errors[0]);
    }

    public function testRejectsInvalidDocx(): void
    {
        $invalidDocxContent = 'NOT A DOCX' . str_repeat('a', 1000);
        $file = UploadedFile::fake()->createWithContent(
            'invalid.docx',
            $invalidDocxContent,
        );

        $result = $this->validator->validate($file);

        $this->assertFalse($result->isValid);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Could not extract', $result->errors[0]);
    }

    public function testSupportsOnlyAllowedExtensions(): void
    {
        $txtFile = UploadedFile::fake()->create('test.txt', 1000);
        $pdfFile = UploadedFile::fake()->create('test.pdf', 1000);
        $docxFile = UploadedFile::fake()->create('test.docx', 1000);
        $unsupportedFile = UploadedFile::fake()->create('test.exe', 1000);

        $this->assertTrue($this->validator->supports($txtFile));
        $this->assertTrue($this->validator->supports($pdfFile));
        $this->assertTrue($this->validator->supports($docxFile));
        $this->assertFalse($this->validator->supports($unsupportedFile));
    }

    public function testHasCorrectName(): void
    {
        $this->assertEquals('content', $this->validator->getName());
    }

    public function testDetectsEncoding(): void
    {
        $utf8Content = 'Это текст в UTF-8 кодировке с договором и правами сторон. ' .
                      str_repeat('Дополнительный текст. ', 10);

        $file = UploadedFile::fake()->createWithContent(
            'utf8.txt',
            $utf8Content,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertArrayHasKey('detected_encoding', $result->metadata);
        $this->assertEquals('UTF-8', $result->metadata['detected_encoding']);
    }

    public function testCalculatesQualityMetrics(): void
    {
        $goodContent = 'Договор между сторонами определяет права и обязанности. ' .
                      str_repeat('Качественный текст договора. ', 15);

        $file = UploadedFile::fake()->createWithContent(
            'quality.txt',
            $goodContent,
        );

        $result = $this->validator->validate($file);

        $this->assertTrue($result->isValid);
        $this->assertArrayHasKey('readable_ratio', $result->metadata);
        $this->assertArrayHasKey('avg_word_length', $result->metadata);
        $this->assertGreaterThan(0.5, $result->metadata['readable_ratio']);
    }
}
