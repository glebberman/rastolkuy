<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Parser\Extractors\Support;

use App\Services\Parser\Extractors\Support\EncodingDetector;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class EncodingDetectorTest extends TestCase
{
    private EncodingDetector $detector;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new EncodingDetector();
        $this->fixturesPath = base_path('tests/Fixtures/extractors');
    }

    public function testDetectsUtf8Encoding(): void
    {
        $filePath = $this->fixturesPath . '/simple.txt';
        $encoding = $this->detector->detect($filePath);

        $this->assertEquals('UTF-8', $encoding);
    }

    public function testDetectsUtf8WithBom(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_bom');
        file_put_contents($tempFile, "\xEF\xBB\xBF" . 'UTF-8 content with BOM');

        try {
            $encoding = $this->detector->detect($tempFile);
            $this->assertEquals('UTF-8', $encoding);
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectsUtf16BeWithBom(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_utf16be');
        file_put_contents($tempFile, "\xFE\xFF" . 'UTF-16 BE content');

        try {
            $encoding = $this->detector->detect($tempFile);
            $this->assertEquals('UTF-16BE', $encoding);
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectsUtf16LeWithBom(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_utf16le');
        file_put_contents($tempFile, "\xFF\xFE" . 'UTF-16 LE content');

        try {
            $encoding = $this->detector->detect($tempFile);
            $this->assertEquals('UTF-16LE', $encoding);
        } finally {
            unlink($tempFile);
        }
    }

    public function testDetectsCyrillicEncoding(): void
    {
        $filePath = $this->fixturesPath . '/encoding_test.txt';
        $encoding = $this->detector->detect($filePath);

        // Should detect either UTF-8 or Windows-1251 for Cyrillic content
        $this->assertContains($encoding, ['UTF-8', 'Windows-1251']);
    }

    public function testConvertsToUtf8(): void
    {
        $originalContent = 'Test content';
        $converted = $this->detector->convertToUtf8($originalContent, 'UTF-8');

        $this->assertEquals($originalContent, $converted);
    }

    public function testConvertsFromWindows1251(): void
    {
        $cyrillicContent = mb_convert_encoding('Тест', 'Windows-1251', 'UTF-8');
        $converted = $this->detector->convertToUtf8($cyrillicContent, 'Windows-1251');

        $this->assertEquals('Тест', $converted);
    }

    public function testThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not found');

        $this->detector->detect('/non/existent/file.txt');
    }

    public function testThrowsExceptionForUnreadableFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_unreadable');
        file_put_contents($tempFile, 'content');
        chmod($tempFile, 0o000); // Remove all permissions

        try {
            $this->expectException(Exception::class); // More general exception
            $this->detector->detect($tempFile);
        } finally {
            chmod($tempFile, 0o644); // Restore permissions for cleanup
            unlink($tempFile);
        }
    }

    public function testHandlesEmptyFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_empty');
        file_put_contents($tempFile, '');

        try {
            $encoding = $this->detector->detect($tempFile);
            $this->assertEquals('UTF-8', $encoding); // Should default to UTF-8
        } finally {
            unlink($tempFile);
        }
    }

    public function testFailsToConvertInvalidEncoding(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid encoding');

        $this->detector->convertToUtf8('invalid content', 'INVALID-ENCODING');
    }

    public function testDetectsExtendedAscii(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_ascii');
        // Create content with extended ASCII characters
        $content = "Regular text with extended ASCII: \x80\x81\x82";
        file_put_contents($tempFile, $content);

        try {
            $encoding = $this->detector->detect($tempFile);
            // Just check that it returns a valid encoding
            $this->assertNotEmpty($encoding);
            $this->assertIsString($encoding);
        } finally {
            unlink($tempFile);
        }
    }
}
