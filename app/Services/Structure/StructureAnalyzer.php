<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use App\Services\Structure\DTOs\DocumentSection;
use App\Services\Structure\DTOs\StructureAnalysisResult;
use App\Services\Structure\Validation\InputValidator;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final readonly class StructureAnalyzer
{
    private readonly float $minConfidenceThreshold;

    private readonly int $maxAnalysisTimeSeconds;

    public function __construct(
        private readonly SectionDetectorInterface $sectionDetector,
        private readonly AnchorGeneratorInterface $anchorGenerator,
    ) {
        /** @var array<string, mixed> $config */
        $config = Config::get('structure_analysis');

        /** @var array<string, mixed> $detection */
        $detection = $config['detection'] ?? [];
        // Безопасное извлечение значений
        /** @var float $threshold */
        $threshold = $detection['min_confidence_threshold'] ?? 0.5;
        /** @var int $maxTime */
        $maxTime = $detection['max_analysis_time_seconds'] ?? 30;

        $this->minConfidenceThreshold = $threshold;
        $this->maxAnalysisTimeSeconds = $maxTime;
    }

    public function analyze(ExtractedDocument $document): StructureAnalysisResult
    {
        // Валидация входных данных
        try {
            InputValidator::validateDocument($document);
        } catch (Exception $e) {
            Log::error('Document validation failed', [
                'document_path' => $document->originalPath,
                'error' => $e->getMessage(),
            ]);

            return new StructureAnalysisResult(
                documentId: $this->generateDocumentId($document),
                sections: [],
                analysisTime: 0.0,
                averageConfidence: 0.0,
                statistics: [],
                metadata: ['validation_error' => $e->getMessage()],
                warnings: ['Document validation failed: ' . $e->getMessage()],
            );
        }

        $startTime = microtime(true);

        Log::info('Starting document structure analysis', [
            'document_path' => $document->originalPath,
            'elements_count' => count($document->elements),
        ]);

        try {
            // Сброс состояния генератора якорей
            $this->anchorGenerator->resetUsedAnchors();

            // Детекция секций
            $sections = $this->sectionDetector->detectSections($document);

            // Фильтрация по confidence
            $filteredSections = $this->filterByConfidence($sections);

            // Построение иерархии
            $hierarchicalSections = $this->buildHierarchy($filteredSections);

            // Вычисление статистики
            $statistics = $this->calculateStatistics($hierarchicalSections, $document);

            // Проверка времени выполнения
            $analysisTime = microtime(true) - $startTime;

            if ($analysisTime > $this->maxAnalysisTimeSeconds) {
                Log::warning('Structure analysis took too long', [
                    'analysis_time' => $analysisTime,
                    'max_time' => $this->maxAnalysisTimeSeconds,
                ]);
            }

            $result = new StructureAnalysisResult(
                documentId: $this->generateDocumentId($document),
                sections: $hierarchicalSections,
                analysisTime: $analysisTime,
                averageConfidence: $this->calculateAverageConfidence($hierarchicalSections),
                statistics: $statistics,
                metadata: $this->extractAnalysisMetadata($document, $sections),
                warnings: $this->generateWarnings($hierarchicalSections, $analysisTime),
            );

            Log::info('Structure analysis completed successfully', [
                'document_path' => $document->originalPath,
                'sections_found' => $result->getSectionsCount(),
                'analysis_time' => $analysisTime,
                'average_confidence' => $result->averageConfidence,
            ]);

            return $result;
        } catch (Exception $e) {
            $analysisTime = microtime(true) - $startTime;

            Log::error('Structure analysis failed', [
                'document_path' => $document->originalPath,
                'error' => $e->getMessage(),
                'analysis_time' => $analysisTime,
            ]);

            // Возвращаем пустой результат при ошибке
            return new StructureAnalysisResult(
                documentId: $this->generateDocumentId($document),
                sections: [],
                analysisTime: $analysisTime,
                averageConfidence: 0.0,
                statistics: [],
                metadata: ['error' => $e->getMessage()],
                warnings: ['Analysis failed: ' . $e->getMessage()],
            );
        }
    }

    /**
     * @param array<ExtractedDocument> $documents
     *
     * @return array<string, StructureAnalysisResult>
     * @throws \Exception
     */
    public function analyzeBatch(array $documents): array
    {
        // Валидация батча
        try {
            InputValidator::validateDocumentBatch($documents);
        } catch (Exception $e) {
            Log::error('Document batch validation failed', [
                'error' => $e->getMessage(),
                'documents_count' => count($documents),
            ]);

            throw $e;
        }

        $results = [];

        foreach ($documents as $key => $document) {
            try {
                $results[$key] = $this->analyze($document);
            } catch (Exception $e) {
                Log::error('Batch analysis failed for document', [
                    'document_key' => $key,
                    'document_path' => $document->originalPath ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                $results[$key] = new StructureAnalysisResult(
                    documentId: $this->generateDocumentId($document),
                    sections: [],
                    analysisTime: 0.0,
                    averageConfidence: 0.0,
                    statistics: [],
                    metadata: ['batch_error' => $e->getMessage()],
                    warnings: ['Batch processing failed: ' . $e->getMessage()],
                );
            }
        }

        return $results;
    }

    public function canAnalyze(ExtractedDocument $document): bool
    {
        // Проверяем наличие элементов
        if (empty($document->elements)) {
            return false;
        }

        // Проверяем наличие текстового содержимого
        $plainText = $document->getPlainText();

        if (mb_strlen(trim($plainText)) < 100) {
            return false;
        }

        // Проверяем на наличие ошибок извлечения
        if ($document->hasErrors()) {
            Log::warning('Document has extraction errors, analysis may be inaccurate', [
                'document_path' => $document->originalPath,
                'errors' => $document->errors,
            ]);
        }

        return true;
    }

    /**
     * @param array<DocumentSection> $sections
     *
     * @return array<DocumentSection>
     */
    private function filterByConfidence(array $sections): array
    {
        return array_filter($sections, function (DocumentSection $section) {
            return $section->confidence >= $this->minConfidenceThreshold;
        });
    }

    /**
     * @param array<DocumentSection> $sections
     *
     * @return array<DocumentSection>
     */
    private function buildHierarchy(array $sections): array
    {
        if (empty($sections)) {
            return [];
        }

        // Сортируем по позиции в документе
        usort(
            $sections,
            fn (DocumentSection $a, DocumentSection $b) => $a->startPosition <=> $b->startPosition,
        );

        // Простой стековый алгоритм построения иерархии
        $result = [];
        $stack = []; // Стек родительских секций

        foreach ($sections as $section) {
            // Удаляем из стека все секции с уровнем >= текущего
            while (!empty($stack)) {
                $lastStackItem = end($stack);

                if ($lastStackItem !== false && $lastStackItem->level >= $section->level) {
                    array_pop($stack);
                } else {
                    break;
                }
            }

            $lastStackItem = end($stack);

            if ($lastStackItem === false) {
                // Корневая секция или проблема со стеком
                $result[] = $section;
                $stack[] = $section;
            } else {
                // Подсекция - добавляем к последней секции в стеке
                $parentSection = $lastStackItem;

                // Создаем новую версию родительской секции с добавленной подсекцией
                $updatedParent = $this->addSubsectionToParent($parentSection, $section);

                // Заменяем родительскую секцию в результате и стеке
                $this->replaceInStack($stack, $parentSection, $updatedParent);
                $this->replaceInResult($result, $parentSection, $updatedParent);

                // Добавляем текущую секцию в стек
                $stack[] = $section;
            }
        }

        return $result;
    }

    /**
     * Заменяет секцию в стеке.
     */
    private function replaceInStack(array &$stack, DocumentSection $oldSection, DocumentSection $newSection): void
    {
        foreach ($stack as $i => $section) {
            if ($section->id === $oldSection->id) {
                $stack[$i] = $newSection;
                break;
            }
        }
    }

    /**
     * Заменяет секцию в результате.
     */
    private function replaceInResult(array &$result, DocumentSection $oldSection, DocumentSection $newSection): void
    {
        foreach ($result as $i => $section) {
            if ($section->id === $oldSection->id) {
                $result[$i] = $newSection;

                return;
            }

            // Рекурсивная замена в подсекциях
            $result[$i] = $this->replaceInSubsections($result[$i], $oldSection, $newSection);
        }
    }

    /**
     * Рекурсивно заменяет секцию в подсекциях.
     */
    private function replaceInSubsections(DocumentSection $section, DocumentSection $oldSection, DocumentSection $newSection): DocumentSection
    {
        if ($section->id === $oldSection->id) {
            return $newSection;
        }

        if (empty($section->subsections)) {
            return $section;
        }

        $updatedSubsections = array_map(
            fn (DocumentSection $subsection) => $this->replaceInSubsections($subsection, $oldSection, $newSection),
            $section->subsections,
        );

        // Если есть изменения в подсекциях, создаем новую версию секции
        if ($updatedSubsections !== $section->subsections) {
            return new DocumentSection(
                id: $section->id,
                title: $section->title,
                content: $section->content,
                level: $section->level,
                startPosition: $section->startPosition,
                endPosition: $section->endPosition,
                anchor: $section->anchor,
                elements: $section->elements,
                subsections: $updatedSubsections,
                confidence: $section->confidence,
                metadata: $section->metadata,
            );
        }

        return $section;
    }

    /**
     * Добавляет подсекцию к родительской секции.
     */
    private function addSubsectionToParent(DocumentSection $parentSection, DocumentSection $subsection): DocumentSection
    {
        return new DocumentSection(
            id: $parentSection->id,
            title: $parentSection->title,
            content: $parentSection->content,
            level: $parentSection->level,
            startPosition: $parentSection->startPosition,
            endPosition: $parentSection->endPosition,
            anchor: $parentSection->anchor,
            elements: $parentSection->elements,
            subsections: [...$parentSection->subsections, $subsection],
            confidence: $parentSection->confidence,
            metadata: $parentSection->metadata,
        );
    }

    /**
     * @param array<DocumentSection> $sections
     */
    private function calculateStatistics(array $sections, ExtractedDocument $document): array
    {
        $allSections = $this->flattenSections($sections);

        if (empty($allSections)) {
            return [
                'total_sections' => 0,
                'sections_by_level' => [],
                'average_section_length' => 0,
                'total_content_length' => 0,
                'coverage_percentage' => 0.0,
            ];
        }

        $sectionsByLevel = [];
        $totalContentLength = 0;

        foreach ($allSections as $section) {
            $level = $section->level;
            $sectionsByLevel[$level] = ($sectionsByLevel[$level] ?? 0) + 1;
            $totalContentLength += mb_strlen($section->content);
        }

        $documentContentLength = mb_strlen($document->getPlainText());
        $coveragePercentage = $documentContentLength > 0
            ? ($totalContentLength / $documentContentLength) * 100
            : 0.0;

        return [
            'total_sections' => count($allSections),
            'sections_by_level' => $sectionsByLevel,
            'average_section_length' => (int) ($totalContentLength / count($allSections)),
            'total_content_length' => $totalContentLength,
            'coverage_percentage' => round($coveragePercentage, 2),
            'max_depth' => max(array_keys($sectionsByLevel)),
        ];
    }

    /**
     * @param array<DocumentSection> $sections
     *
     * @return array<DocumentSection>
     */
    private function flattenSections(array $sections): array
    {
        $result = [];

        foreach ($sections as $section) {
            $result[] = $section;
            array_push($result, ...$section->getAllSubsections());
        }

        return $result;
    }

    /**
     * @param array<DocumentSection> $sections
     */
    private function calculateAverageConfidence(array $sections): float
    {
        $allSections = $this->flattenSections($sections);

        if (empty($allSections)) {
            return 0.0;
        }

        $totalConfidence = array_sum(array_map(
            fn (DocumentSection $section) => $section->confidence,
            $allSections,
        ));

        return round($totalConfidence / count($allSections), 3);
    }

    private function generateDocumentId(ExtractedDocument $document): string
    {
        return 'doc_' . hash('md5', $document->originalPath . microtime(true));
    }

    /**
     * @param array<DocumentSection> $detectedSections
     *
     * @return array<string, mixed>
     */
    private function extractAnalysisMetadata(ExtractedDocument $document, array $detectedSections): array
    {
        return [
            'document_mime_type' => $document->mimeType,
            'document_pages' => $document->totalPages,
            'document_extraction_time' => $document->extractionTime,
            'total_elements' => count($document->elements),
            'element_types' => array_unique(array_map(
                fn ($element) => $element->type,
                $document->elements,
            )),
            'raw_sections_detected' => count($detectedSections),
            'analyzer_version' => '1.0.0',
            'analysis_timestamp' => now()->toISOString(),
        ];
    }

    /**
     * @param array<DocumentSection> $sections
     *
     * @return array<string>
     */
    private function generateWarnings(array $sections, float $analysisTime): array
    {
        $warnings = [];

        if (empty($sections)) {
            $warnings[] = 'No sections detected in document';
        }

        $allSections = $this->flattenSections($sections);
        $lowConfidenceSections = array_filter(
            $allSections,
            fn (DocumentSection $section) => $section->confidence < 0.7,
        );

        if (count($lowConfidenceSections) > 0) {
            $warnings[] = sprintf(
                '%d sections have low confidence scores (< 0.7)',
                count($lowConfidenceSections),
            );
        }

        if ($analysisTime > $this->maxAnalysisTimeSeconds * 0.8) {
            $warnings[] = sprintf(
                'Analysis time (%.2fs) approaching limit (%ds)',
                $analysisTime,
                $this->maxAnalysisTimeSeconds,
            );
        }

        $averageConfidence = $this->calculateAverageConfidence($sections);

        if ($averageConfidence < 0.6) {
            $warnings[] = sprintf(
                'Low average confidence score: %.2f',
                $averageConfidence,
            );
        }

        return $warnings;
    }
}
