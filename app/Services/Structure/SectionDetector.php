<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\DocumentElement;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use App\Services\Structure\DTOs\DocumentSection;
use App\Services\Structure\Validation\InputValidator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class SectionDetector implements SectionDetectorInterface
{
    private readonly float $highConfidence;

    private readonly float $mediumConfidence;

    private readonly float $lowConfidence;

    private readonly int $minSectionLength;

    private readonly int $maxTitleLength;

    private readonly array $sectionPatterns;

    private readonly array $legalKeywords;

    /**
     * @var array<string, array|bool|null> Cache for compiled regex patterns and their results
     */
    private array $patternCache = [];

    public function __construct(
        private readonly AnchorGeneratorInterface $anchorGenerator,
    ) {
        /** @var array<string, mixed> $config */
        $config = Config::get('structure_analysis');

        // Type-safe access with explicit checks
        /** @var array<string, mixed> $confidenceLevels */
        $confidenceLevels = $config['confidence_levels'] ?? [];
        /** @var array<string, mixed> $detection */
        $detection = $config['detection'] ?? [];
        /** @var array<string, mixed> $sectionPatterns */
        $sectionPatterns = $config['section_patterns'] ?? [];
        /** @var array<string, mixed> $legalKeywords */
        $legalKeywords = $config['legal_keywords'] ?? [];

        // Безопасное извлечение значений с проверкой типов
        /** @var float $high */
        $high = $confidenceLevels['high'] ?? 0.9;
        /** @var float $medium */
        $medium = $confidenceLevels['medium'] ?? 0.7;
        /** @var float $low */
        $low = $confidenceLevels['low'] ?? 0.5;
        /** @var int $minLength */
        $minLength = $detection['min_section_length'] ?? 50;
        /** @var int $maxLength */
        $maxLength = $detection['max_title_length'] ?? 100;

        $this->highConfidence = $high;
        $this->mediumConfidence = $medium;
        $this->lowConfidence = $low;
        $this->minSectionLength = $minLength;
        $this->maxTitleLength = $maxLength;

        // Объединяем все паттерны из конфигурации более эффективно
        $this->sectionPatterns = $this->flattenPatterns([
            ...(array) ($sectionPatterns['numbered'] ?? []),
            ...(array) ($sectionPatterns['subsections'] ?? []),
            ...(array) ($sectionPatterns['named'] ?? []),
        ]);

        // Объединяем все ключевые слова более эффективно
        $this->legalKeywords = $this->flattenPatterns([
            ...(array) ($legalKeywords['contract_terms'] ?? []),
            ...(array) ($legalKeywords['legal_entities'] ?? []),
            ...(array) ($legalKeywords['actions'] ?? []),
        ]);
    }

    /**
     * @return array<DocumentSection>
     */
    public function detectSections(ExtractedDocument $document): array
    {
        Log::info('Starting section detection', [
            'document_elements' => count($document->elements),
        ]);

        $sections = [];
        $elements = $document->elements;

        // Попытка детекции через заголовки
        $headerSections = $this->detectByHeaders($elements);

        if (!empty($headerSections)) {
            array_push($sections, ...$headerSections);
        }

        // Попытка детекции через паттерны
        if (empty($sections)) {
            $patternSections = $this->detectByPatterns($elements);
            array_push($sections, ...$patternSections);
        }

        // Эвристический анализ если ничего не найдено
        if (empty($sections)) {
            $heuristicSections = $this->detectByHeuristics($elements);
            array_push($sections, ...$heuristicSections);
        }

        // Пост-обработка: объединение коротких секций, валидация
        $sections = $this->postProcess($sections);

        Log::info('Section detection completed', [
            'sections_detected' => count($sections),
        ]);

        return $sections;
    }

    /**
     * Очищает кэш паттернов для освобождения памяти.
     */
    public function clearPatternCache(): void
    {
        $this->patternCache = [];
    }

    /**
     * Возвращает статистику использования кэша.
     */
    public function getCacheStats(): array
    {
        return [
            'cache_size' => count($this->patternCache),
            'memory_usage' => memory_get_usage(true),
        ];
    }

    /**
     * @param array<DocumentElement> $elements
     *
     * @return array<DocumentSection>
     */
    private function detectByHeaders(array $elements): array
    {
        $sections = [];
        $currentSection = null;
        $currentElements = [];
        $position = 0;

        foreach ($elements as $element) {
            if ($element instanceof HeaderElement) {
                // Сохраняем предыдущую секцию
                if ($currentSection !== null && !empty($currentElements)) {
                    $sections[] = $this->createSection(
                        $currentSection['title'],
                        $currentElements,
                        $currentSection['level'],
                        $position - count($currentElements),
                        $position - 1,
                        $this->highConfidence,
                    );
                }

                // Начинаем новую секцию
                $currentSection = [
                    'title' => trim($element->content),
                    'level' => $element->level,
                ];
                $currentElements = [$element];
            } else {
                $currentElements[] = $element;
            }
            ++$position;
        }

        // Добавляем последнюю секцию
        if ($currentSection !== null && !empty($currentElements)) {
            $sections[] = $this->createSection(
                $currentSection['title'],
                $currentElements,
                $currentSection['level'],
                $position - count($currentElements),
                $position - 1,
                $this->highConfidence,
            );
        }

        return $sections;
    }

    /**
     * @param array<DocumentElement> $elements
     *
     * @return array<DocumentSection>
     */
    private function detectByPatterns(array $elements): array
    {
        $sections = [];
        $currentElements = [];
        $position = 0;

        foreach ($elements as $element) {
            $sectionInfo = $this->matchSectionPattern($element->content);

            if ($sectionInfo !== null) {
                // Сохраняем предыдущую секцию
                if (!empty($currentElements)) {
                    $sections[] = $this->createSectionFromElements(
                        $currentElements,
                        $position - count($currentElements),
                        $position - 1,
                        $this->mediumConfidence,
                    );
                }

                // Начинаем новую секцию
                $currentElements = [$element];
            } else {
                $currentElements[] = $element;
            }
            ++$position;
        }

        // Добавляем последнюю секцию
        if (!empty($currentElements)) {
            $sections[] = $this->createSectionFromElements(
                $currentElements,
                $position - count($currentElements),
                $position - 1,
                $this->mediumConfidence,
            );
        }

        return array_filter($sections);
    }

    /**
     * @param array<DocumentElement> $elements
     *
     * @return array<DocumentSection>
     */
    private function detectByHeuristics(array $elements): array
    {
        $sections = [];
        $currentElements = [];
        $position = 0;

        foreach ($elements as $element) {
            $isSectionStart = $this->isLikelySectionStart($element);

            if ($isSectionStart && !empty($currentElements)) {
                // Сохраняем предыдущую секцию
                $sections[] = $this->createSectionFromElements(
                    $currentElements,
                    $position - count($currentElements),
                    $position - 1,
                    $this->lowConfidence,
                );
                $currentElements = [];
            }

            $currentElements[] = $element;
            ++$position;
        }

        // Добавляем последнюю секцию
        if (!empty($currentElements)) {
            $sections[] = $this->createSectionFromElements(
                $currentElements,
                $position - count($currentElements),
                $position - 1,
                $this->lowConfidence,
            );
        }

        return array_filter($sections);
    }

    private function matchSectionPattern(string $content): ?array
    {
        $trimmedContent = trim($content);

        // Создаем кэш-ключ для контента
        $cacheKey = 'pattern_' . hash('md5', $trimmedContent);

        // Проверяем кэш
        if (isset($this->patternCache[$cacheKey])) {
            /** @var array|null $cachedResult */
            $cachedResult = $this->patternCache[$cacheKey];

            return is_array($cachedResult) ? $cachedResult : null;
        }

        foreach ($this->sectionPatterns as $pattern) {
            // Используем безопасное выполнение regex
            $matches = InputValidator::safeRegexMatch($pattern, $trimmedContent);

            if ($matches !== false && count($matches) >= 3) {
                $result = [
                    'prefix' => $matches[1],
                    'title' => $matches[2],
                    'level' => $this->determineLevelFromPrefix($matches[1]),
                ];

                // Кэшируем результат
                $this->patternCache[$cacheKey] = $result;

                return $result;
            }
        }

        // Кэшируем отрицательный результат
        $this->patternCache[$cacheKey] = null;

        return null;
    }

    private function determineLevelFromPrefix(string $prefix): int
    {
        // Подсчитываем количество точек для определения уровня
        $dotCount = substr_count($prefix, '.');

        if ($dotCount > 0) {
            return min($dotCount + 1, 6); // Максимум 6 уровней
        }

        // Проверяем ключевые слова безопасно
        if (InputValidator::safeRegexMatch('/^(Раздел|Глава)/i', $prefix) !== false) {
            return 1;
        }

        if (InputValidator::safeRegexMatch('/^(Статья|§)/i', $prefix) !== false) {
            return 2;
        }

        return 1;
    }

    private function isLikelySectionStart(DocumentElement $element): bool
    {
        $content = trim($element->content);

        // Создаем кэш-ключ для контента
        $cacheKey = 'likely_' . hash('md5', $content);

        // Проверяем кэш
        if (isset($this->patternCache[$cacheKey])) {
            $cachedResult = $this->patternCache[$cacheKey];
            return is_bool($cachedResult) ? $cachedResult : false;
        }

        // Проверяем длину - заголовки обычно короткие
        if (mb_strlen($content) > $this->maxTitleLength) {
            $this->patternCache[$cacheKey] = false;

            return false;
        }

        // Проверяем наличие двоеточия в конце
        if (str_ends_with($content, ':')) {
            $this->patternCache[$cacheKey] = true;

            return true;
        }

        // Проверяем на короткую строку с заглавной буквы
        if (mb_strlen($content) < 100 && mb_stripos($content, mb_substr($content, 0, 1)) === 0) {
            $this->patternCache[$cacheKey] = true;

            return true;
        }

        // Проверяем наличие юридических терминов
        $legalKeywordCount = 0;
        $contentLower = mb_strtolower($content);

        foreach ($this->legalKeywords as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                ++$legalKeywordCount;
            }
        }

        $result = $legalKeywordCount >= 2;
        $this->patternCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * @param array<DocumentElement> $elements
     */
    private function createSection(
        string $title,
        array $elements,
        int $level,
        int $startPosition,
        int $endPosition,
        float $confidence,
    ): DocumentSection {
        $content = implode("\n", array_map(
            static fn (DocumentElement $el) => $el->getPlainText(),
            $elements,
        ));

        $id = 'section_' . str_replace('.', '_', uniqid('', true));
        $anchor = $this->anchorGenerator->generate($id, $title);

        return new DocumentSection(
            id: $id,
            title: $title,
            content: $content,
            level: $level,
            startPosition: $startPosition,
            endPosition: $endPosition,
            anchor: $anchor,
            elements: $elements,
            confidence: $confidence,
            metadata: [
                'detection_method' => 'header_based',
                'element_types' => array_unique(array_map(
                    static fn (DocumentElement $el) => $el->type,
                    $elements,
                )),
            ],
        );
    }

    /**
     * @param array<DocumentElement> $elements
     */
    private function createSectionFromElements(
        array $elements,
        int $startPosition,
        int $endPosition,
        float $confidence,
    ): ?DocumentSection {
        if (empty($elements)) {
            return null;
        }

        // Пытаемся извлечь заголовок из первого элемента
        $firstElement = $elements[0];
        $title = $this->extractTitleFromElement($firstElement);

        if (mb_strlen($title) < 3) {
            $title = 'Untitled Section';
        }

        $content = implode("\n", array_map(
            static fn (DocumentElement $el) => $el->getPlainText(),
            $elements,
        ));

        // Пропускаем слишком короткие секции
        if (mb_strlen($content) < $this->minSectionLength) {
            return null;
        }

        $id = 'section_' . str_replace('.', '_', uniqid('', true));
        $anchor = $this->anchorGenerator->generate($id, $title);

        return new DocumentSection(
            id: $id,
            title: $title,
            content: $content,
            level: 1,
            startPosition: $startPosition,
            endPosition: $endPosition,
            anchor: $anchor,
            elements: $elements,
            confidence: $confidence,
            metadata: [
                'detection_method' => 'pattern_based',
                'element_types' => array_unique(array_map(
                    static fn (DocumentElement $el) => $el->type,
                    $elements,
                )),
            ],
        );
    }

    private function extractTitleFromElement(DocumentElement $element): string
    {
        $content = trim($element->content);

        // Ограничиваем длину заголовка
        if (mb_strlen($content) > $this->maxTitleLength) {
            return mb_substr($content, 0, $this->maxTitleLength) . '...';
        }

        return $content;
    }

    /**
     * @param array<DocumentSection> $sections
     *
     * @return array<DocumentSection>
     */
    private function postProcess(array $sections): array
    {
        // Фильтруем слишком короткие секции
        $sections = array_filter($sections, function (DocumentSection $section) {
            return mb_strlen($section->content) >= $this->minSectionLength;
        });

        // Объединяем очень короткие соседние секции
        $processed = [];
        $buffer = null;

        foreach ($sections as $section) {
            if ($buffer === null) {
                $buffer = $section;
                continue;
            }

            // Если текущая секция короткая, добавляем к буферу
            if (mb_strlen($section->content) < $this->minSectionLength * 2) {
                $buffer = $this->mergeSections($buffer, $section);
            } else {
                $processed[] = $buffer;
                $buffer = $section;
            }
        }

        if ($buffer !== null) {
            $processed[] = $buffer;
        }

        return $processed;
    }

    private function mergeSections(DocumentSection $first, DocumentSection $second): DocumentSection
    {
        $mergedElements = [...$first->elements, ...$second->elements];
        $mergedContent = $first->content . "\n\n" . $second->content;
        $mergedTitle = $first->title;

        if (mb_strlen($second->title) > mb_strlen($first->title)) {
            $mergedTitle = $second->title;
        }

        return new DocumentSection(
            id: $first->id,
            title: $mergedTitle,
            content: $mergedContent,
            level: min($first->level, $second->level),
            startPosition: $first->startPosition,
            endPosition: $second->endPosition,
            anchor: $first->anchor,
            elements: $mergedElements,
            confidence: min($first->confidence, $second->confidence),
            metadata: array_merge($first->metadata, [
                'merged_with' => $second->id,
                'merge_reason' => 'short_section',
            ]),
        );
    }

    /**
     * Выравнивает вложенные массивы в плоский массив строк
     * Фильтрует только строковые значения, игнорируя остальные типы.
     *
     * @return array<string>
     */
    private function flattenPatterns(array $data): array
    {
        $result = [];

        foreach ($data as $item) {
            if (is_string($item)) {
                $result[] = $item;
            } elseif (is_array($item)) {
                // Рекурсивно обрабатываем вложенные массивы
                $result = [...$result, ...$this->flattenPatterns($item)];
            }
        }

        return $result;
    }
}
