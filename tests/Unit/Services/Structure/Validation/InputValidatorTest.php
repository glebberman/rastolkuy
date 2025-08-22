<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Structure\Validation;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\ParagraphElement;
use App\Services\Structure\Validation\InputValidator;
use InvalidArgumentException;
use Tests\TestCase;

class InputValidatorTest extends TestCase
{
    public function testValidatesDocumentSuccessfully(): void
    {
        $document = $this->createValidDocument();

        // Не должно выбрасывать исключение
        InputValidator::validateDocument($document);

        $this->assertTrue(true); // Тест прошел если не выбросило исключение
    }

    public function testRejectsEmptyDocument(): void
    {
        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document must contain at least one element');

        InputValidator::validateDocument($document);
    }

    public function testRejectsTooManyElements(): void
    {
        $elements = [];

        for ($i = 0; $i < 10001; ++$i) {
            $elements[] = new ParagraphElement('Test content ' . $i);
        }

        $document = new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: $elements,
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document contains too many elements');

        InputValidator::validateDocument($document);
    }

    public function testValidatesSectionTitleSuccessfully(): void
    {
        InputValidator::validateSectionTitle('Valid Section Title');
        $this->assertTrue(true);
    }

    public function testRejectsEmptySectionTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Section title cannot be empty');

        InputValidator::validateSectionTitle('');
    }

    public function testRejectsWhitespaceOnlySectionTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Section title cannot be empty');

        InputValidator::validateSectionTitle('   ');
    }

    public function testRejectsTooLongSectionTitle(): void
    {
        $longTitle = str_repeat('a', 1001);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Section title too long');

        InputValidator::validateSectionTitle($longTitle);
    }

    public function testRejectsSectionTitleWithInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Section title contains invalid characters');

        InputValidator::validateSectionTitle('Title with <script>alert("xss")</script>');
    }

    public function testValidatesAnchorIdSuccessfully(): void
    {
        InputValidator::validateAnchorId('valid_anchor_id_123');
        $this->assertTrue(true);
    }

    public function testRejectsEmptyAnchorId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Anchor ID cannot be empty');

        InputValidator::validateAnchorId('');
    }

    public function testRejectsInvalidAnchorIdCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Anchor ID can only contain letters, numbers, underscores and hyphens');

        InputValidator::validateAnchorId('invalid@id');
    }

    public function testValidatesConfidenceSuccessfully(): void
    {
        InputValidator::validateConfidence(0.5);
        InputValidator::validateConfidence(0.0);
        InputValidator::validateConfidence(1.0);
        $this->assertTrue(true);
    }

    public function testRejectsInvalidConfidenceValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0');

        InputValidator::validateConfidence(-0.1);
    }

    public function testRejectsConfidenceAboveOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0');

        InputValidator::validateConfidence(1.1);
    }

    public function testValidatesDocumentBatchSuccessfully(): void
    {
        $documents = [
            'doc1' => $this->createValidDocument(),
            'doc2' => $this->createValidDocument(),
        ];

        InputValidator::validateDocumentBatch($documents);
        $this->assertTrue(true);
    }

    public function testRejectsEmptyDocumentBatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Document batch cannot be empty');

        InputValidator::validateDocumentBatch([]);
    }

    public function testRejectsTooLargeDocumentBatch(): void
    {
        $documents = [];

        for ($i = 0; $i < 101; ++$i) {
            $documents["doc{$i}"] = $this->createValidDocument();
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch too large');

        InputValidator::validateDocumentBatch($documents);
    }

    public function testRejectsInvalidDocumentInBatch(): void
    {
        $documents = [
            'doc1' => $this->createValidDocument(),
            'doc2' => 'not a document',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid document at key "doc2"');

        InputValidator::validateDocumentBatch($documents);
    }

    public function testValidatesSearchTextSuccessfully(): void
    {
        InputValidator::validateSearchText('Some text to search in');
        $this->assertTrue(true);
    }

    public function testRejectsTooLargeSearchText(): void
    {
        $largeText = str_repeat('a', 1000001);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Text too large for search');

        InputValidator::validateSearchText($largeText);
    }

    public function testValidatesRegexPatternSuccessfully(): void
    {
        $safePattern = '/^(\\d+)\\s+(.+)$/';

        // Should not throw exception
        InputValidator::validateRegexPattern($safePattern);

        $this->assertTrue(true); // If we get here, validation passed
    }

    public function testRejectsUnsafeRegexPatterns(): void
    {
        $unsafePatterns = [
            '/(.*)+(.*)+/',  // Catastrophic backtracking
            '/a*+/',         // Nested quantifiers
            '/a+*/',         // Nested quantifiers
        ];

        foreach ($unsafePatterns as $pattern) {
            try {
                InputValidator::validateRegexPattern($pattern);
                $this->fail('Expected InvalidArgumentException for pattern: ' . $pattern);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Potentially unsafe regex pattern detected', $e->getMessage());
            }
        }
    }

    public function testSafeRegexMatchWorksWithValidPattern(): void
    {
        $pattern = '/^(\\d+)\\s+(.+)$/';
        $subject = '123 Test Title';

        $result = InputValidator::safeRegexMatch($pattern, $subject);

        $this->assertIsArray($result);
        $this->assertEquals('123 Test Title', $result[0]);
        $this->assertEquals('123', $result[1]);
        $this->assertEquals('Test Title', $result[2]);
    }

    public function testSafeRegexMatchReturnsFalseForNoMatch(): void
    {
        $pattern = '/^(\\d+)\\s+(.+)$/';
        $subject = 'No numbers here';

        $result = InputValidator::safeRegexMatch($pattern, $subject);

        $this->assertFalse($result);
    }

    public function testSafeRegexMatchTruncatesLongInput(): void
    {
        $pattern = '/test/';
        $longSubject = str_repeat('a', 15000) . 'test';

        // Should not throw exception and should work with truncated input
        $result = InputValidator::safeRegexMatch($pattern, $longSubject);

        // Since the 'test' part is at the end and gets truncated, it should return false
        $this->assertFalse($result);
    }

    private function createValidDocument(): ExtractedDocument
    {
        return new ExtractedDocument(
            originalPath: '/test.txt',
            mimeType: 'text/plain',
            elements: [
                new ParagraphElement('Valid content for testing purposes. This is long enough to pass validation.'),
            ],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
        );
    }
}
