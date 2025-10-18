<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\DocumentElement;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use App\Services\Structure\DTOs\DocumentSection;
use App\Services\Structure\Scoring\DTOs\DocumentContext;
use App\Services\Structure\Scoring\DTOs\HeaderScore;
use App\Services\Structure\Scoring\HeaderScorer;
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

    /**
     * Maximum cache size to prevent memory leaks.
     */
    private const MAX_CACHE_SIZE = 1000;

    public function __construct(
        private readonly AnchorGeneratorInterface $anchorGenerator,
        private readonly HeaderScorer $headerScorer = new HeaderScorer(),
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

        // Попытка детекции через заголовки (HeaderElement)
        $headerSections = $this->detectByHeaders($elements);

        if (!empty($headerSections)) {
            array_push($sections, ...$headerSections);
        }

        // Попытка детекции через паттерны (нумерация, служебные слова)
        if (empty($sections)) {
            $patternSections = $this->detectByPatterns($elements);
            array_push($sections, ...$patternSections);
        }

        // Попытка детекции через ML-like scoring (новый универсальный метод)
        if (empty($sections)) {
            $scoringSections = $this->detectByScoring($elements);
            array_push($sections, ...$scoringSections);
        }

        // Эвристический анализ если ничего не найдено
        if (empty($sections)) {
            $heuristicSections = $this->detectByHeuristics($elements);
            array_push($sections, ...$heuristicSections);
        }

        // Fallback: если ничего не найдено, разбиваем по фиксированному размеру
        if (empty($sections)) {
            $fallbackSections = $this->detectByFixedSize($elements);
            array_push($sections, ...$fallbackSections);
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

        // Evict cache if it's too large to prevent memory leaks
        if (count($this->patternCache) >= self::MAX_CACHE_SIZE) {
            // Remove half of the oldest entries
            $this->patternCache = array_slice(
                $this->patternCache,
                (int) (self::MAX_CACHE_SIZE / 2),
                preserve_keys: true
            );
        }

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
     * Detect sections using ML-like scoring system.
     *
     * @param array<DocumentElement> $elements
     *
     * @return array<DocumentSection>
     */
    private function detectByScoring(array $elements): array
    {
        $context = DocumentContext::fromElements($elements);
        $scores = [];

        // Score all elements
        foreach ($elements as $index => $element) {
            $scores[$index] = $this->headerScorer->scoreElement($element, $context);
        }

        // Calculate adaptive threshold
        $threshold = $this->calculateAdaptiveThreshold($scores, $context);

        Log::info('ML-like scoring detection', [
            'threshold' => $threshold,
            'has_visual_data' => $context->hasVisualData,
        ]);

        // Detect sections based on scores
        $sections = [];
        $currentElements = [];
        $position = 0;

        foreach ($elements as $index => $element) {
            $score = $scores[$index];

            if ($score->isLikelyHeader($threshold) && !empty($currentElements)) {
                // Save previous section
                $sections[] = $this->createSectionFromElements(
                    $currentElements,
                    $position - count($currentElements),
                    $position - 1,
                    $this->mediumConfidence,
                );
                $currentElements = [];
            }

            $currentElements[] = $element;
            ++$position;
        }

        // Add last section
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
     * Calculate adaptive threshold based on score distribution.
     *
     * @param array<HeaderScore> $scores
     */
    private function calculateAdaptiveThreshold(
        array $scores,
        DocumentContext $context,
    ): float {
        $baseThreshold = $context->hasVisualData ? 0.65 : 0.6;

        if (empty($scores)) {
            return $baseThreshold;
        }

        // Extract total scores
        $scoreValues = array_map(fn (HeaderScore $s) => $s->total, $scores);
        $avgScore = array_sum($scoreValues) / count($scoreValues);
        $stdDev = $this->calculateStdDev($scoreValues, $avgScore);

        Log::info('Score statistics', [
            'avg_score' => round($avgScore, 3),
            'std_dev' => round($stdDev, 3),
            'base_threshold' => $baseThreshold,
        ]);

        // If high clustering - use statistical threshold
        if ($stdDev > 0.2) {
            return max($avgScore + $stdDev, $baseThreshold);
        }

        return $baseThreshold;
    }

    /**
     * Calculate standard deviation.
     *
     * @param array<float> $values
     */
    private function calculateStdDev(array $values, float $mean): float
    {
        if (empty($values)) {
            return 0.0;
        }

        $variance = 0.0;

        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        $variance /= count($values);

        // Protect against negative variance due to floating point precision errors
        return sqrt(max(0.0, $variance));
    }

    /**
     * Fallback: detect sections by fixed size when no headers found.
     *
     * Splits document into chunks of ~500-1000 words with natural boundaries.
     *
     * @param array<DocumentElement> $elements
     *
     * @return array<DocumentSection>
     */
    private function detectByFixedSize(array $elements): array
    {
        $sections = [];
        $currentElements = [];
        $currentWordCount = 0;
        $targetWordCount = 750; // Target ~750 words per section
        $position = 0;

        foreach ($elements as $element) {
            $words = str_word_count($element->getPlainText());
            $currentElements[] = $element;
            $currentWordCount += $words;

            // Check if we reached target size and have a natural boundary
            if ($currentWordCount >= $targetWordCount) {
                // Look for natural boundary (double newline or paragraph end)
                if ($this->isNaturalBoundary($element)) {
                    $section = $this->createSectionFromElements(
                        $currentElements,
                        $position - count($currentElements) + 1,
                        $position,
                        $this->lowConfidence,
                    );

                    if ($section !== null) {
                        $sections[] = $section;
                    }

                    $currentElements = [];
                    $currentWordCount = 0;
                }
            }

            ++$position;
        }

        // Add remaining elements
        if (!empty($currentElements)) {
            $section = $this->createSectionFromElements(
                $currentElements,
                $position - count($currentElements),
                $position - 1,
                $this->lowConfidence,
            );

            if ($section !== null) {
                $sections[] = $section;
            }
        }

        Log::info('Fixed-size fallback detection', [
            'sections_created' => count($sections),
            'target_word_count' => $targetWordCount,
        ]);

        return $sections;
    }

    /**
     * Check if element is a natural section boundary.
     */
    private function isNaturalBoundary(DocumentElement $element): bool
    {
        $content = $element->getPlainText();

        // Check for double newlines
        if (str_contains($content, "\n\n")) {
            return true;
        }

        // Check for paragraph ending (sentence with period)
        if (preg_match('/[.!?]\s*$/u', trim($content))) {
            return true;
        }

        return false;
    }

    /**
     * Выравнивает вложенные массивы в плоский массив строк.
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
