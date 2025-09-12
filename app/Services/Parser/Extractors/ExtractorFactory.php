<?php

declare(strict_types=1);

namespace App\Services\Parser\Extractors;

use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

class ExtractorFactory
{
    /**
     * @var array<string, class-string<ExtractorInterface>>
     */
    private array $extractors = [];

    public function __construct(
        private readonly EncodingDetector $encodingDetector,
        private readonly ElementClassifier $classifier,
        private readonly MetricsCollector $metrics,
        private readonly ?Container $container = null,
    ) {
        $this->registerDefaultExtractors();
    }

    public function create(string $mimeType): ExtractorInterface
    {
        foreach ($this->extractors as $supportedType => $extractorClass) {
            if ($supportedType === $mimeType) {
                return $this->instantiateExtractor($extractorClass);
            }
        }

        // Try to find by pattern matching
        foreach ($this->extractors as $supportedType => $extractorClass) {
            $extractor = $this->instantiateExtractor($extractorClass);

            if ($extractor->supports($mimeType)) {
                return $extractor;
            }
        }

        throw new InvalidArgumentException("No extractor found for MIME type: {$mimeType}");
    }

    public function createFromFile(string $filePath): ExtractorInterface
    {
        $mimeType = $this->detectMimeType($filePath);

        return $this->create($mimeType);
    }

    /**
     * @param class-string<ExtractorInterface> $extractorClass
     */
    public function register(string $mimeType, string $extractorClass): void
    {
        if (!is_subclass_of($extractorClass, ExtractorInterface::class)) {
            throw new InvalidArgumentException('Extractor class must implement ExtractorInterface');
        }

        $this->extractors[$mimeType] = $extractorClass;
    }

    /**
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array
    {
        return array_keys($this->extractors);
    }

    public function supports(string $mimeType): bool
    {
        return isset($this->extractors[$mimeType]);
    }

    private function registerDefaultExtractors(): void
    {
        $this->extractors = [
            // Text files
            'text/plain' => TxtExtractor::class,
            'text/txt' => TxtExtractor::class,
            'application/txt' => TxtExtractor::class,

            // PDF files
            'application/pdf' => PdfExtractor::class,

            // DOCX files
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => DocxExtractor::class,
            'application/docx' => DocxExtractor::class,
        ];
    }

    /**
     * @param class-string<ExtractorInterface> $extractorClass
     */
    private function instantiateExtractor(string $extractorClass): ExtractorInterface
    {
        // Try to use Laravel's container if available
        if ($this->container !== null && $this->container->bound($extractorClass)) {
            return $this->container->make($extractorClass);
        }

        // Fallback to manual instantiation
        return match ($extractorClass) {
            TxtExtractor::class => new TxtExtractor(
                $this->encodingDetector,
                $this->classifier,
                $this->metrics,
            ),
            PdfExtractor::class => new PdfExtractor(
                $this->classifier,
                $this->metrics,
            ),
            DocxExtractor::class => new DocxExtractor(
                $this->classifier,
                $this->metrics,
            ),
            default => throw new RuntimeException("Unknown extractor class: {$extractorClass}")
        };
    }

    private function detectMimeType(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        // Use finfo to detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new RuntimeException('Cannot initialize file info resource');
        }

        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if ($mimeType === false) {
            throw new RuntimeException("Cannot detect MIME type for file: {$filePath}");
        }

        // Handle common aliases and edge cases
        return $this->normalizeMimeType($mimeType, $filePath);
    }

    private function normalizeMimeType(string $mimeType, string $filePath): string
    {
        // Get file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Handle specific cases where finfo might be wrong
        switch ($extension) {
            case 'txt':
                return 'text/plain';
            case 'pdf':
                return 'application/pdf';
            case 'docx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'rtf':
                return 'application/rtf';
        }

        // Fix common finfo inconsistencies
        return match ($mimeType) {
            'text/x-c', 'text/x-c++' => 'text/plain',
            'application/octet-stream' => $this->guessFromExtension($extension),
            default => $mimeType
        };
    }

    private function guessFromExtension(string $extension): string
    {
        return match ($extension) {
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'rtf' => 'application/rtf',
            default => 'application/octet-stream'
        };
    }
}
