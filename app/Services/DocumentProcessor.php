<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\LLM\LLMService;
use App\Services\Prompt\DTOs\LlmParsingRequest;
use App\Services\Prompt\LlmResponseParser;
use App\Services\Structure\AnchorGenerator;
use App\Services\Structure\StructureAnalyzer;
use Illuminate\Support\Facades\Log;

final readonly class DocumentProcessor
{
    public function __construct(
        private StructureAnalyzer $structureAnalyzer,
        private AnchorGenerator $anchorGenerator,
        private LLMService $llmService,
        private LlmResponseParser $responseParser,
    ) {
    }

    /**
     * Обрабатывает документ: анализирует структуру, отправляет в LLM, парсит ответ, заменяет якоря
     */
    public function processDocument(
        string $documentContent,
        string $taskType = 'translation',
        array $options = []
    ): string {
        Log::info('Starting document processing', [
            'task_type' => $taskType,
            'content_length' => mb_strlen($documentContent),
        ]);

        // 1. Анализируем структуру документа и добавляем якоря
        // Создаем минимальный ExtractedDocument для анализа
        $textElement = new \App\Services\Parser\Extractors\Elements\TextElement(
            content: $documentContent,
            position: [],
            metadata: []
        );
        
        $extractedDocument = new \App\Services\Parser\Extractors\DTOs\ExtractedDocument(
            originalPath: 'direct_input',
            mimeType: 'text/plain',
            elements: [$textElement],
            metadata: ['processing_mode' => 'direct_content'],
            totalPages: 1,
            extractionTime: 0.0
        );
        $structureResult = $this->structureAnalyzer->analyze($extractedDocument);
        $sectionsWithAnchors = $this->addAnchorsToDocument($documentContent, $structureResult->sections);

        // 2. Подготавливаем список якорей для валидации
        $anchorIds = [];
        foreach ($structureResult->sections as $section) {
            if ($section instanceof \App\Services\Structure\DTOs\DocumentSection) {
                $anchorIds[] = $this->extractAnchorId($section->anchor);
            }
        }

        // 3. Отправляем в LLM с указанием якорей
        $llmResponse = $this->sendToLLM($sectionsWithAnchors, $taskType, $anchorIds, $options);

        // 4. Парсим ответ LLM и валидируем якоря
        $parsedResponse = $this->parseAndValidateResponse($llmResponse, $anchorIds, $taskType);

        if (!$parsedResponse->isSuccessful()) {
            Log::warning('LLM response parsing failed', [
                'errors' => $parsedResponse->errors,
                'warnings' => $parsedResponse->warnings,
            ]);
            
            // Возвращаем оригинал если парсинг провалился
            return $sectionsWithAnchors;
        }

        // 5. Заменяем якоря на переведенное содержимое
        $processedDocument = $this->replaceAnchorsWithContent($sectionsWithAnchors, $parsedResponse);

        Log::info('Document processing completed', [
            'anchors_processed' => $parsedResponse->getValidAnchorCount(),
            'warnings' => count($parsedResponse->warnings),
        ]);

        return $processedDocument;
    }

    private function addAnchorsToDocument(string $content, array $sections): string
    {
        $documentWithAnchors = $content;
        
        // Сортируем секции по позиции в убывающем порядке для корректной вставки якорей
        $sortedSections = array_filter($sections, fn($s) => $s instanceof \App\Services\Structure\DTOs\DocumentSection);
        usort($sortedSections, fn($a, $b) => $b->startPosition <=> $a->startPosition);

        foreach ($sortedSections as $section) {
            $anchorId = $section->id;
            $anchor = $this->anchorGenerator->generate($anchorId, $section->title);
            
            // Вставляем якорь в начало секции
            $beforeSection = substr($documentWithAnchors, 0, $section->startPosition);
            $sectionContent = substr($documentWithAnchors, $section->startPosition, 
                $section->endPosition - $section->startPosition);
            $afterSection = substr($documentWithAnchors, $section->endPosition);

            $documentWithAnchors = $beforeSection . $anchor . "\n" . $sectionContent . $afterSection;
        }

        return $documentWithAnchors;
    }

    private function sendToLLM(string $content, string $taskType, array $anchorIds, array $options): string
    {
        // Формируем промпт в зависимости от задачи
        $prompt = $this->buildPrompt($content, $taskType, $anchorIds, $options);

        // Отправляем через LLMService
        $response = $this->llmService->generate($prompt, [
            'model' => $options['model'] ?? 'claude-3-5-sonnet-20241022',
            'max_tokens' => $options['max_tokens'] ?? 4000,
            'temperature' => $options['temperature'] ?? 0.1,
        ]);

        return $response->content;
    }

    private function buildPrompt(string $content, string $taskType, array $anchorIds, array $options): string
    {
        // Вместо создания промпта здесь, используем PromptManager
        // Это временная заглушка для обратной совместимости
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

    private function parseAndValidateResponse(string $response, array $anchorIds, string $taskType): \App\Services\Prompt\DTOs\ParsedLlmResponse
    {
        $request = new LlmParsingRequest(
            rawResponse: $response,
            schemaType: $taskType,
            originalAnchors: $anchorIds,
            validationRules: ['anchors_required'],
            strictValidation: false, // Используем мягкую валидацию
        );

        return $this->responseParser->parseWithFallback($request);
    }

    private function replaceAnchorsWithContent(string $documentWithAnchors, \App\Services\Prompt\DTOs\ParsedLlmResponse $parsedResponse): string
    {
        $processedDocument = $documentWithAnchors;
        $anchorContentMap = $parsedResponse->getAnchorContentMap();

        foreach ($anchorContentMap as $anchorId => $content) {
            // Заменяем якорь на содержимое
            $fullAnchor = $this->findFullAnchorInDocument($processedDocument, $anchorId);
            if ($fullAnchor !== null) {
                $replacement = $fullAnchor . "\n\n**[Переведено]:** " . $content . "\n";
                $processedDocument = str_replace($fullAnchor, $replacement, $processedDocument);
            }
        }

        return $processedDocument;
    }

    private function extractAnchorId(string $fullAnchor): string
    {
        return $this->anchorGenerator->extractAnchorId($fullAnchor) ?? '';
    }

    private function findFullAnchorInDocument(string $document, string $anchorId): ?string
    {
        $anchors = $this->anchorGenerator->findAnchorsInText($document);
        
        foreach ($anchors as $anchor) {
            if ($this->anchorGenerator->extractAnchorId($anchor) === $anchorId) {
                return $anchor;
            }
        }

        return null;
    }
}