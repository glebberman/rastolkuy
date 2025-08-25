<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\LLM\LLMService;
use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\DTOs\ExtractionConfig;
use App\Services\Parser\Extractors\Elements\TextElement;
use App\Services\Parser\Extractors\ExtractorManager;
use App\Services\Prompt\DTOs\LlmParsingRequest;
use App\Services\Prompt\LlmResponseParser;
use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\DTOs\DocumentSection;
use App\Services\Structure\StructureAnalyzer;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Единая система обработки документов: файл → структура → LLM → результат
 */
final readonly class DocumentProcessor
{
    public function __construct(
        private ExtractorManager $extractorManager,
        private StructureAnalyzer $structureAnalyzer,
        private AnchorGenerator $anchorGenerator,
        private LLMService $llmService,
        private LlmResponseParser $responseParser,
    ) {
    }

    /**
     * Обрабатывает файл документа (PDF, DOCX, TXT)
     */
    public function processFile(
        string|UploadedFile $file,
        string $taskType = 'translation',
        array $options = [],
        bool $addAnchorAtStart = false
    ): string {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        
        Log::info('Starting file processing', [
            'file_path' => $filePath,
            'task_type' => $taskType,
            'file_size' => is_string($file) ? filesize($file) : $file->getSize(),
            'anchor_position' => $addAnchorAtStart ? 'start' : 'end',
        ]);

        try {
            // 1. Извлекаем содержимое и структуру файла
            $extractedDocument = $this->extractorManager->extract($filePath, ExtractionConfig::createDefault());
            
            // 2. Обрабатываем извлеченный документ
            return $this->processExtractedDocument($extractedDocument, $taskType, $options, $addAnchorAtStart);
            
        } catch (Exception $e) {
            Log::error('File processing failed', [
                'file_path' => $filePath,
                'task_type' => $taskType,
                'error' => $e->getMessage(),
            ]);
            
            throw new InvalidArgumentException("Failed to process file: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Обрабатывает уже извлеченный документ
     */
    public function processExtractedDocument(
        ExtractedDocument $extractedDocument,
        string $taskType = 'translation', 
        array $options = [],
        bool $addAnchorAtStart = false
    ): string {
        Log::info('Starting extracted document processing', [
            'task_type' => $taskType,
            'elements_count' => count($extractedDocument->elements),
            'document_path' => $extractedDocument->originalPath,
            'anchor_position' => $addAnchorAtStart ? 'start' : 'end',
        ]);

        try {
            // 1. Анализируем структуру документа
            $structureResult = $this->structureAnalyzer->analyze($extractedDocument);
            
            if (!$structureResult->isSuccessful()) {
                Log::warning('Structure analysis failed, using fallback processing', [
                    'warnings' => $structureResult->warnings,
                ]);
                
                // Fallback: обрабатываем как простой текст
                return $this->processPlainText($extractedDocument->getPlainText(), $taskType, $options, $addAnchorAtStart);
            }

            // 2. Добавляем якоря к документу
            $originalContent = $extractedDocument->getPlainText();
            $sectionsWithAnchors = $this->addAnchorsToDocument($originalContent, $structureResult->sections, $addAnchorAtStart);

            // 3. Подготавливаем список якорей для валидации
            $anchorIds = $this->extractAnchorIds($structureResult->sections);

            // 4. Отправляем в LLM с указанием якорей
            $llmResponse = $this->sendToLLM($sectionsWithAnchors, $taskType, $anchorIds, $options);

            // 5. Парсим ответ LLM и валидируем якоря
            $parsedResponse = $this->parseAndValidateResponse($llmResponse, $anchorIds, $taskType);

            if (!$parsedResponse->isSuccessful()) {
                Log::warning('LLM response parsing failed', [
                    'errors' => $parsedResponse->errors,
                    'warnings' => $parsedResponse->warnings,
                ]);
                
                // Возвращаем документ с якорями если парсинг провалился
                return $sectionsWithAnchors;
            }

            // 6. Заменяем якоря на обработанное содержимое
            $processedDocument = $this->replaceAnchorsWithContent($sectionsWithAnchors, $parsedResponse);

            Log::info('Document processing completed successfully', [
                'anchors_processed' => $parsedResponse->getValidAnchorCount(),
                'sections_found' => $structureResult->getSectionsCount(),
                'warnings' => count($parsedResponse->warnings),
            ]);

            return $processedDocument;
            
        } catch (Exception $e) {
            Log::error('Document processing failed', [
                'document_path' => $extractedDocument->originalPath,
                'task_type' => $taskType,
                'error' => $e->getMessage(),
            ]);
            
            throw new InvalidArgumentException("Document processing failed: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Обрабатывает простой текст (fallback для случаев когда нет файла)
     */
    public function processPlainText(
        string $documentContent,
        string $taskType = 'translation',
        array $options = [],
        bool $addAnchorAtStart = false
    ): string {
        Log::info('Starting plain text processing', [
            'task_type' => $taskType,
            'content_length' => mb_strlen($documentContent),
            'anchor_position' => $addAnchorAtStart ? 'start' : 'end',
        ]);

        try {
            // Создаем минимальный ExtractedDocument для простого текста
            $textElement = new TextElement(
                content: $documentContent,
                position: [],
                metadata: ['source' => 'plain_text']
            );
            
            $extractedDocument = new ExtractedDocument(
                originalPath: 'plain_text_input',
                mimeType: 'text/plain',
                elements: [$textElement],
                metadata: ['processing_mode' => 'plain_text'],
                totalPages: 1,
                extractionTime: 0.0
            );

            // Используем общий процессор для извлеченных документов
            return $this->processExtractedDocument($extractedDocument, $taskType, $options, $addAnchorAtStart);
            
        } catch (Exception $e) {
            Log::error('Plain text processing failed', [
                'task_type' => $taskType,
                'content_length' => mb_strlen($documentContent),
                'error' => $e->getMessage(),
            ]);
            
            throw new InvalidArgumentException("Plain text processing failed: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Извлекает ID якорей из секций документа
     *
     * @param array<DocumentSection> $sections
     * @return array<string>
     */
    private function extractAnchorIds(array $sections): array
    {
        $anchorIds = [];
        
        foreach ($sections as $section) {
            if ($section instanceof DocumentSection) {
                $anchorId = $this->anchorGenerator->extractAnchorId($section->anchor);
                if ($anchorId !== null) {
                    $anchorIds[] = $anchorId;
                }
            }
        }
        
        return $anchorIds;
    }

    /**
     * Добавляет якоря к документу для идентификации секций
     *
     * @param array<DocumentSection> $sections
     * @param bool $addAnchorAtStart По умолчанию false (якорь в конце секции)
     */
    private function addAnchorsToDocument(string $content, array $sections, bool $addAnchorAtStart = false): string
    {
        $documentWithAnchors = $content;
        
        // Сортируем секции по позиции в убывающем порядке для корректной вставки якорей
        $sortedSections = array_filter($sections, fn($s) => $s instanceof DocumentSection);
        usort($sortedSections, fn($a, $b) => $b->startPosition <=> $a->startPosition);

        foreach ($sortedSections as $section) {
            // Используем существующий якорь секции (уже сгенерированный StructureAnalyzer)
            $anchor = $section->anchor;
            
            $beforeSection = substr($documentWithAnchors, 0, $section->startPosition);
            $sectionContent = substr($documentWithAnchors, $section->startPosition, 
                $section->endPosition - $section->startPosition);
            $afterSection = substr($documentWithAnchors, $section->endPosition);

            if ($addAnchorAtStart) {
                // Вставляем якорь в начало секции
                $documentWithAnchors = $beforeSection . $anchor . "\n" . $sectionContent . $afterSection;
            } else {
                // Вставляем якорь в конец секции (по умолчанию)
                $documentWithAnchors = $beforeSection . $sectionContent . "\n" . $anchor . $afterSection;
            }
        }

        return $documentWithAnchors;
    }

    /**
     * Отправляет документ с якорями в LLM для обработки
     *
     * @param array<string> $anchorIds
     */
    private function sendToLLM(string $content, string $taskType, array $anchorIds, array $options): string
    {
        // Формируем промпт в зависимости от задачи
        $prompt = $this->buildPrompt($content, $taskType, $anchorIds, $options);

        // Настройки модели в зависимости от типа задачи и размера документа
        $modelOptions = $this->getModelOptions($taskType, mb_strlen($content), $options);

        Log::debug('Sending request to LLM', [
            'task_type' => $taskType,
            'content_length' => mb_strlen($content),
            'anchors_count' => count($anchorIds),
            'model' => $modelOptions['model'],
        ]);

        // Отправляем через LLMService
        $response = $this->llmService->generate($prompt, $modelOptions);

        return $response->content;
    }

    /**
     * Определяет оптимальные настройки модели для задачи
     */
    private function getModelOptions(string $taskType, int $contentLength, array $userOptions): array
    {
        // Базовые настройки
        $baseOptions = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4000,
            'temperature' => 0.1,
        ];

        // Адаптивный выбор модели для экономии
        if ($contentLength < 2000 && in_array($taskType, ['translation'])) {
            $baseOptions['model'] = 'claude-3-5-haiku-20241022'; // Экономичная модель для простых задач
            $baseOptions['max_tokens'] = 2000;
        } elseif ($contentLength > 10000) {
            $baseOptions['max_tokens'] = 8000; // Больше токенов для длинных документов
        }

        // Пользовательские переопределения имеют приоритет
        return array_merge($baseOptions, $userOptions);
    }

    /**
     * Строит промпт для LLM в зависимости от типа задачи
     *
     * @param array<string> $anchorIds
     */
    private function buildPrompt(string $content, string $taskType, array $anchorIds, array $options): string
    {
        $anchorList = implode(', ', $anchorIds);
        
        switch ($taskType) {
            case 'translation':
                return "Переведи следующий юридический документ в простой, понятный язык по секциям.

ДОКУМЕНТ:
{$content}

ВАЖНО: В документе есть якоря секций. Ответь в формате JSON:
{
  \"sections\": [
    {\"anchor\": \"anchor_id\", \"content\": \"переведенный текст\", \"type\": \"translation\"},
    ...
  ]
}

Доступные якоря: {$anchorList}

Переведи каждую секцию отдельно, указав правильный якорь.";

            case 'contradiction':
                return "Проанализируй следующий юридический документ на предмет противоречий.

ДОКУМЕНТ:
{$content}

ВАЖНО: Ответь в формате JSON:
{
  \"sections\": [
    {\"anchor\": \"anchor_id\", \"content\": \"найденное противоречие\", \"analysis_type\": \"contradiction\", \"severity\": \"high\"},
    ...
  ]
}

Доступные якоря: {$anchorList}";

            case 'ambiguity':
                return "Проанализируй следующий юридический документ на предмет неоднозначностей.

ДОКУМЕНТ:  
{$content}

ВАЖНО: Ответь в формате JSON:
{
  \"sections\": [
    {\"anchor\": \"anchor_id\", \"content\": \"найденная неоднозначность\", \"analysis_type\": \"ambiguity\", \"severity\": \"medium\"},
    ...
  ]
}

Доступные якоря: {$anchorList}";

            default:
                return "Проанализируй следующий юридический документ.

ДОКУМЕНТ:
{$content}

Ответь в формате JSON с результатами анализа.";
        }
    }

    /**
     * Парсит и валидирует ответ LLM
     *
     * @param array<string> $anchorIds
     */
    private function parseAndValidateResponse(string $response, array $anchorIds, string $taskType): \App\Services\Prompt\DTOs\ParsedLlmResponse
    {
        $request = new LlmParsingRequest(
            rawResponse: $response,
            schemaType: $taskType,
            originalAnchors: $anchorIds,
            validationRules: ['anchors_required'],
            strictValidation: false, // Используем мягкую валидацию для лучшей совместимости
        );

        return $this->responseParser->parseWithFallback($request);
    }

    /**
     * Заменяет якоря в документе на обработанное LLM содержимое
     */
    private function replaceAnchorsWithContent(string $documentWithAnchors, \App\Services\Prompt\DTOs\ParsedLlmResponse $parsedResponse): string
    {
        $processedDocument = $documentWithAnchors;
        $anchorContentMap = $parsedResponse->getAnchorContentMap();

        if (empty($anchorContentMap)) {
            Log::warning('No anchor content found in LLM response');
            return $processedDocument;
        }

        foreach ($anchorContentMap as $anchorId => $content) {
            // Находим полный якорь в документе
            $fullAnchor = $this->findFullAnchorInDocument($processedDocument, $anchorId);
            
            if ($fullAnchor !== null) {
                // Формируем замену в зависимости от типа содержимого
                $replacement = $this->formatReplacementContent($fullAnchor, $content, $parsedResponse->schemaType);
                $processedDocument = str_replace($fullAnchor, $replacement, $processedDocument);
                
                Log::debug('Replaced anchor with content', [
                    'anchor_id' => $anchorId,
                    'content_length' => mb_strlen($content),
                ]);
            } else {
                Log::warning('Could not find anchor in document', [
                    'anchor_id' => $anchorId,
                ]);
            }
        }

        return $processedDocument;
    }

    /**
     * Форматирует замену якоря в зависимости от типа обработки
     */
    private function formatReplacementContent(string $anchor, string $content, ?string $taskType): string
    {
        $label = match($taskType) {
            'translation' => '**[Переведено]:**',
            'contradiction' => '**[Найдено противоречие]:**',
            'ambiguity' => '**[Найдена неоднозначность]:**',
            default => '**[Обработано]:**',
        };
        
        return "{$anchor}\n\n{$label} {$content}\n";
    }

    /**
     * Находит полный якорь в документе по его ID
     */
    private function findFullAnchorInDocument(string $document, string $anchorId): ?string
    {
        $anchors = $this->anchorGenerator->findAnchorsInText($document);
        
        foreach ($anchors as $anchor) {
            $extractedId = $this->anchorGenerator->extractAnchorId($anchor);
            if ($extractedId === $anchorId) {
                return $anchor;
            }
        }

        return null;
    }
}