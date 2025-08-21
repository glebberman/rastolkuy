<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ExtractorDemoUploadRequest;
use App\Http\Responses\ExtractorErrorResponse;
use App\Http\Responses\ExtractorStreamingResponse;
use App\Http\Responses\ExtractorTestResponse;
use App\Http\Responses\ExtractorUploadResponse;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use App\Services\Parser\Extractors\ExtractorFactory;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Parser\Extractors\Support\ElementClassifier;
use App\Services\Parser\Extractors\Support\EncodingDetector;
use App\Services\Parser\Extractors\Support\MetricsCollector;
use Exception;
use Illuminate\View\View;

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
            app(),
        );

        $this->manager = new ExtractorManager($factory);
    }

    public function demo(): View
    {
        return view('extractor-demo');
    }

    public function testBasic(): ExtractorTestResponse|ExtractorErrorResponse
    {
        try {
            $testContent = '# ТЕСТОВЫЙ ДОКУМЕНТ

Это первый параграф с обычным текстом для проверки работы системы извлечения документов.

## Список требований

1. Поддержка кириллицы
2. Определение заголовков
3. Распознавание списков

- Пункт А
- Пункт Б  
- Пункт В

ВАЖНО: Система должна корректно обрабатывать различные типы элементов.

Заключительный параграф документа.';

            $tempFile = tempnam(sys_get_temp_dir(), 'extractor_test');
            file_put_contents($tempFile, $testContent);

            $config = ExtractionConfig::createDefault();
            $result = $this->manager->extract($tempFile, $config);

            unlink($tempFile);

            return new ExtractorTestResponse($result, 'basic');
        } catch (Exception $e) {
            return new ExtractorErrorResponse($e);
        }
    }

    public function testStreaming(): ExtractorStreamingResponse|ExtractorErrorResponse
    {
        try {
            // Create large test file for streaming test
            $largeContent = '';

            for ($i = 0; $i < 1000; ++$i) {
                $largeContent .= "Строка номер {$i} для тестирования потоковой обработки больших файлов.\n";
            }
            $largeContent .= "\n# БОЛЬШОЙ ЗАГОЛОВОК\n\n";

            for ($i = 0; $i < 2000; ++$i) {
                $largeContent .= "Параграф номер {$i} в большом документе.\n";
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'extractor_large');
            file_put_contents($tempFile, $largeContent);

            $config = ExtractionConfig::createStreaming();
            $result = $this->manager->extract($tempFile, $config);

            unlink($tempFile);

            return new ExtractorStreamingResponse($result, $config);
        } catch (Exception $e) {
            return new ExtractorErrorResponse($e);
        }
    }

    public function upload(ExtractorDemoUploadRequest $request): ExtractorUploadResponse|ExtractorErrorResponse
    {
        $file = $request->file('document');
        $configType = $request->getConfigType();

        try {
            // Get config based on user selection
            $config = match ($configType) {
                'fast' => ExtractionConfig::createFast(),
                'streaming' => ExtractionConfig::createStreaming(),
                'large' => ExtractionConfig::createForLargeFiles(),
                default => ExtractionConfig::createDefault(),
            };

            $result = $this->manager->extract($file->getPathname(), $config);

            return new ExtractorUploadResponse($result, $file, $configType);
        } catch (Exception $e) {
            return new ExtractorErrorResponse($e);
        }
    }
}
