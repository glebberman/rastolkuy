<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Parser\Extractors\ExtractorFactory;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Exception;

class ExtractorDemoController
{
    private ExtractorManager $manager;

    public function __construct()
    {
        // Initialize services - в продакшене это должно быть через DI
        $encodingDetector = new EncodingDetector();
        $elementClassifier = new ElementClassifier();
        $metricsCollector = new MetricsCollector();
        
        $factory = new ExtractorFactory(
            $encodingDetector,
            $elementClassifier,
            $metricsCollector,
            app()
        );
        
        $this->manager = new ExtractorManager($factory);
    }

    public function demo(): View
    {
        return view('extractor-demo');
    }

    public function testBasic(): JsonResponse
    {
        try {
            $testContent = "# ТЕСТОВЫЙ ДОКУМЕНТ

Это первый параграф с обычным текстом для проверки работы системы извлечения документов.

## Список требований

1. Поддержка кириллицы
2. Определение заголовков
3. Распознавание списков

- Пункт А
- Пункт Б  
- Пункт В

ВАЖНО: Система должна корректно обрабатывать различные типы элементов.

Заключительный параграф документа.";

            $tempFile = tempnam(sys_get_temp_dir(), 'extractor_test');
            file_put_contents($tempFile, $testContent);

            $config = ExtractionConfig::createDefault();
            $result = $this->manager->extract($tempFile, $config);

            unlink($tempFile);

            return $this->formatExtractionResult($result);

        } catch (Exception $e) {
            return $this->formatError($e);
        }
    }

    public function testStreaming(): JsonResponse
    {
        try {
            // Create large test file for streaming test
            $largeContent = "";
            for ($i = 0; $i < 1000; $i++) {
                $largeContent .= "Строка номер {$i} для тестирования потоковой обработки больших файлов.\n";
            }
            $largeContent .= "\n# БОЛЬШОЙ ЗАГОЛОВОК\n\n";
            for ($i = 0; $i < 2000; $i++) {
                $largeContent .= "Параграф номер {$i} в большом документе.\n";
            }
            
            $tempFile = tempnam(sys_get_temp_dir(), 'extractor_large');
            file_put_contents($tempFile, $largeContent);

            $config = ExtractionConfig::createStreaming();
            $result = $this->manager->extract($tempFile, $config);

            unlink($tempFile);

            return response()->json([
                'status' => 'success',
                'processing_mode' => $result->metadata['processing_mode'] ?? 'regular',
                'extraction_time' => round($result->extractionTime, 4) . 's',
                'elements_count' => $result->getElementsCount(),
                'file_size' => $result->metadata['file_size'] ?? 0,
                'config' => [
                    'stream_processing' => $config->streamProcessing,
                    'chunk_size' => $config->chunkSize,
                    'timeout' => $config->timeoutSeconds,
                ],
                'metrics' => $result->metrics,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            return $this->formatError($e);
        }
    }

    public function upload(Request $request): JsonResponse
    {
        if (!$request->hasFile('document')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }
        
        $file = $request->file('document');
        
        if (!$file->isValid()) {
            return response()->json(['error' => 'Invalid file'], 400);
        }
        
        $configType = $request->input('config', 'default');
        
        try {
            // Get config based on user selection
            $config = match($configType) {
                'fast' => ExtractionConfig::createFast(),
                'streaming' => ExtractionConfig::createStreaming(),
                'large' => ExtractionConfig::createForLargeFiles(),
                default => ExtractionConfig::createDefault(),
            };
            
            $result = $this->manager->extract($file->getPathname(), $config);
            
            // Format elements for display
            $elements = [];
            foreach ($result->elements as $element) {
                $elements[] = [
                    'type' => $element->type,
                    'content' => $element->content,
                    'confidence' => round($element->getConfidenceScore(), 2),
                    'page' => $element->pageNumber,
                    'metadata' => $element->metadata,
                ];
            }
            
            return response()->json([
                'status' => 'success',
                'file_info' => [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $result->mimeType,
                    'encoding' => $result->metadata['encoding'] ?? 'unknown',
                    'line_count' => $result->metadata['line_count'] ?? 0,
                    'processing_mode' => $result->metadata['processing_mode'] ?? 'regular',
                ],
                'extraction' => [
                    'time' => round($result->extractionTime, 4),
                    'elements_count' => count($elements),
                    'config_used' => $configType,
                ],
                'elements' => $elements,
                'metrics' => $result->metrics,
            ], 200, [], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            return $this->formatError($e);
        }
    }

    private function formatExtractionResult(ExtractedDocument $result): JsonResponse
    {
        $elements = [];
        foreach ($result->elements as $element) {
            $elements[] = [
                'type' => $element->type,
                'content' => mb_substr($element->content, 0, 100) . (mb_strlen($element->content) > 100 ? '...' : ''),
                'confidence' => $element->getConfidenceScore(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'extraction_time' => round($result->extractionTime, 4) . 's',
            'elements_count' => $result->getElementsCount(),
            'file_info' => [
                'mime_type' => $result->mimeType,
                'encoding' => $result->metadata['encoding'] ?? 'unknown',
                'file_size' => $result->metadata['file_size'] ?? 0,
                'line_count' => $result->metadata['line_count'] ?? 0,
            ],
            'elements' => $elements,
            'metrics' => $result->metrics,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function formatError(Exception $e): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ], 500, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}