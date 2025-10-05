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
     * Обрабатывает файл документа (PDF, DOCX, TXT).
     */
    public function processFile(
        string|UploadedFile $file,
        string $taskType = 'translation',
        array $options = [],
        bool $addAnchorAtStart = false,
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
        bool $addAnchorAtStart = false,
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

            Log::debug('Document with anchors', [
                'sections_count' => count($structureResult->sections),
                'content_preview' => mb_substr($sectionsWithAnchors, 0, 1000),
                'content_length' => mb_strlen($sectionsWithAnchors),
            ]);

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
     * Обрабатывает простой текст (fallback для случаев когда нет файла).
     */
    public function processPlainText(
        string $documentContent,
        string $taskType = 'translation',
        array $options = [],
        bool $addAnchorAtStart = false,
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
                metadata: ['source' => 'plain_text'],
            );

            $extractedDocument = new ExtractedDocument(
                originalPath: 'plain_text_input',
                mimeType: 'text/plain',
                elements: [$textElement],
                metadata: ['processing_mode' => 'plain_text'],
                totalPages: 1,
                extractionTime: 0.0,
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
     * Извлекает ID якорей из секций документа.
     *
     * @param array<DocumentSection> $sections
     *
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
     * Добавляет якоря к документу для идентификации секций.
     *
     * Ищет текст секции в исходном документе и вставляет якорь после него.
     *
     * @param array<DocumentSection> $sections
     * @param bool $addAnchorAtStart По умолчанию false (якорь в конце секции)
     */
    private function addAnchorsToDocument(string $content, array $sections, bool $addAnchorAtStart = false): string
    {
        // Фильтруем валидные секции
        $validSections = array_filter($sections, fn ($s) => $s instanceof DocumentSection);

        if (empty($validSections)) {
            return $content;
        }

        $documentWithAnchors = $content;
        $insertedLength = 0; // Отслеживаем смещение из-за вставленных якорей

        foreach ($validSections as $section) {
            $sectionText = trim($section->content);

            // Ищем текст секции в документе
            $position = mb_strpos($documentWithAnchors, $sectionText, $insertedLength);

            if ($position === false) {
                Log::warning('Section text not found in document', [
                    'section_id' => $section->id,
                    'section_title' => $section->title,
                    'text_preview' => mb_substr($sectionText, 0, 100),
                ]);
                continue;
            }

            $anchor = $section->anchor;
            $sectionEndPos = $position + mb_strlen($sectionText);

            if ($addAnchorAtStart) {
                // Вставляем якорь в начало секции
                $documentWithAnchors = mb_substr($documentWithAnchors, 0, $position)
                    . $anchor . "\n"
                    . mb_substr($documentWithAnchors, $position);
                $insertedLength = $position + mb_strlen($anchor) + 1;
            } else {
                // Вставляем якорь в конец секции (по умолчанию)
                $documentWithAnchors = mb_substr($documentWithAnchors, 0, $sectionEndPos)
                    . "\n" . $anchor
                    . mb_substr($documentWithAnchors, $sectionEndPos);
                $insertedLength = $sectionEndPos + mb_strlen($anchor) + 1;
            }
        }

        Log::debug('Document with anchors created', [
            'sections_count' => count($validSections),
            'result_length' => mb_strlen($documentWithAnchors),
            'preview' => mb_substr($documentWithAnchors, 0, 500),
        ]);

        return $documentWithAnchors;
    }

    /**
     * Отправляет документ с якорями в LLM для обработки.
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
     * Определяет оптимальные настройки модели для задачи.
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
     * Строит промпт для LLM в зависимости от типа задачи.
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
     * Парсит и валидирует ответ LLM.
     *
     * @param array<string> $anchorIds
     */
    private function parseAndValidateResponse(string $response, array $anchorIds, string $taskType): Prompt\DTOs\ParsedLlmResponse
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
     * Заменяет якоря в документе на обработанное LLM содержимое.
     */
    private function replaceAnchorsWithContent(string $documentWithAnchors, Prompt\DTOs\ParsedLlmResponse $parsedResponse): string
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
     * Форматирует замену якоря в зависимости от типа обработки.
     *
     * ВАЖНО: Якорь ЗАМЕНЯЕТСЯ на перевод с маркерами блоков.
     * Маркеры используются для правильного разделения при экспорте.
     * Оригинальный текст секции находится ПЕРЕД якорем и не затрагивается.
     */
    private function formatReplacementContent(string $anchor, string $content, ?string $taskType): string
    {
        $type = $taskType ?? 'processed';

        // Используем XML-подобные маркеры для разделения блоков
        // Они будут заменены на нужный формат при экспорте
        return "\n\n<!-- TRANSLATION_BLOCK_START type=\"{$type}\" -->\n{$content}\n<!-- TRANSLATION_BLOCK_END -->\n";
    }

    /**
     * Находит полный якорь в документе по его ID.
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
