<?php

declare(strict_types=1);

namespace App\Services\Structure;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use App\Services\Parser\Extractors\Elements\DocumentElement;
use App\Services\Parser\Extractors\Elements\HeaderElement;
use App\Services\Structure\Contracts\AnchorGeneratorInterface;
use App\Services\Structure\Contracts\SectionDetectorInterface;
use App\Services\Structure\DTOs\DocumentSection;
use Illuminate\Support\Facades\Log;

final class SectionDetector implements SectionDetectorInterface
{
    private const float HIGH_CONFIDENCE = 0.9;
    private const float MEDIUM_CONFIDENCE = 0.7;
    private const float LOW_CONFIDENCE = 0.5;

    private const int MIN_SECTION_LENGTH = 50;
    private const int MAX_TITLE_LENGTH = 200;

    private const array SECTION_PATTERNS = [
        // Нумерованные разделы
        '/^(\d+\.?\s*[\.\s]*)(.*?)$/um',
        '/^(Раздел\s+\d+\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Глава\s+\d+\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Статья\s+\d+\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(§\s*\d+\.?\s*[\.\s]*)(.*?)$/um',

        // Подразделы
        '/^(\d+\.\d+\.?\s*[\.\s]*)(.*?)$/um',
        '/^(\d+\.\d+\.\d+\.?\s*[\.\s]*)(.*?)$/um',

        // Именованные разделы
        '/^(Введение\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Заключение\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Приложение\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Общие\s+положения\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Права\s+и\s+обязанности\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Ответственность\s+сторон\.?\s*[\.\s]*)(.*?)$/ium',
        '/^(Заключительные\s+положения\.?\s*[\.\s]*)(.*?)$/ium',
    ];

    private const array LEGAL_KEYWORDS = [
        'договор', 'соглашение', 'контракт', 'сторона', 'стороны',
        'обязательство', 'ответственность', 'права', 'обязанности',
        'исполнение', 'нарушение', 'условия', 'пункт', 'статья',
        'предмет', 'цена', 'оплата', 'срок', 'порядок',
    ];

    public function __construct(
        private readonly AnchorGeneratorInterface $anchorGenerator,
    ) {
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
            $sections = array_merge($sections, $headerSections);
        }

        // Попытка детекции через паттерны
        if (empty($sections)) {
            $patternSections = $this->detectByPatterns($elements);
            $sections = array_merge($sections, $patternSections);
        }

        // Эвристический анализ если ничего не найдено
        if (empty($sections)) {
            $heuristicSections = $this->detectByHeuristics($elements);
            $sections = array_merge($sections, $heuristicSections);
        }

        // Пост-обработка: объединение коротких секций, валидация
        $sections = $this->postProcess($sections);

        Log::info('Section detection completed', [
            'sections_detected' => count($sections),
        ]);

        return $sections;
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
                        self::HIGH_CONFIDENCE,
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
                self::HIGH_CONFIDENCE,
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
                        self::MEDIUM_CONFIDENCE,
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
                self::MEDIUM_CONFIDENCE,
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
                    self::LOW_CONFIDENCE,
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
                self::LOW_CONFIDENCE,
            );
        }

        return array_filter($sections);
    }

    private function matchSectionPattern(string $content): ?array
    {
        foreach (self::SECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, trim($content), $matches)) {
                return [
                    'prefix' => $matches[1],
                    'title' => $matches[2],
                    'level' => $this->determineLevelFromPrefix($matches[1]),
                ];
            }
        }

        return null;
    }

    private function determineLevelFromPrefix(string $prefix): int
    {
        // Подсчитываем количество точек для определения уровня
        $dotCount = substr_count($prefix, '.');

        if ($dotCount > 0) {
            return min($dotCount + 1, 6); // Максимум 6 уровней
        }

        // Проверяем ключевые слова
        if (preg_match('/^(Раздел|Глава)/i', $prefix)) {
            return 1;
        }

        if (preg_match('/^(Статья|§)/i', $prefix)) {
            return 2;
        }

        return 1;
    }

    private function isLikelySectionStart(DocumentElement $element): bool
    {
        $content = trim($element->content);

        // Проверяем длину - заголовки обычно короткие
        if (mb_strlen($content) > self::MAX_TITLE_LENGTH) {
            return false;
        }

        // Проверяем наличие двоеточия в конце
        if (str_ends_with($content, ':')) {
            return true;
        }

        // Проверяем на короткую строку с заглавной буквы
        if (mb_strlen($content) < 100 && mb_strtoupper(mb_substr($content, 0, 1)) === mb_substr($content, 0, 1)) {
            return true;
        }

        // Проверяем наличие юридических терминов
        $legalKeywordCount = 0;
        $contentLower = mb_strtolower($content);

        foreach (self::LEGAL_KEYWORDS as $keyword) {
            if (str_contains($contentLower, $keyword)) {
                ++$legalKeywordCount;
            }
        }

        return $legalKeywordCount >= 2;
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
            fn (DocumentElement $el) => $el->getPlainText(),
            $elements,
        ));

        $id = uniqid('section_', true);
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
                    fn (DocumentElement $el) => $el->type,
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
            fn (DocumentElement $el) => $el->getPlainText(),
            $elements,
        ));

        // Пропускаем слишком короткие секции
        if (mb_strlen($content) < self::MIN_SECTION_LENGTH) {
            return null;
        }

        $id = uniqid('section_', true);
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
                    fn (DocumentElement $el) => $el->type,
                    $elements,
                )),
            ],
        );
    }

    private function extractTitleFromElement(DocumentElement $element): string
    {
        $content = trim($element->content);

        // Ограничиваем длину заголовка
        if (mb_strlen($content) > self::MAX_TITLE_LENGTH) {
            return mb_substr($content, 0, self::MAX_TITLE_LENGTH) . '...';
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
            return mb_strlen($section->content) >= self::MIN_SECTION_LENGTH;
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
            if (mb_strlen($section->content) < self::MIN_SECTION_LENGTH * 2) {
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
}
