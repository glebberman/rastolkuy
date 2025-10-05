<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\DocumentProcessing;
use App\Services\Export\DTOs\ParsedContent;
use App\Services\Export\DTOs\Risk;
use App\Services\Export\DTOs\Section;
use InvalidArgumentException;

/**
 * Сервис для обработки контента документа для экспорта.
 */
final readonly class ContentProcessor
{
    /**
     * Парсит результат обработки документа и извлекает секции.
     */
    public function parseDocumentResult(DocumentProcessing $document): ParsedContent
    {
        if (empty($document->result) || !$document->isCompleted()) {
            throw new InvalidArgumentException('Document must be completed and have result');
        }

        $result = $document->result;

        if (is_array($result) && array_key_exists('content', $result) && (is_string($result['content']) || is_numeric($result['content']))) {
            $content = (string) $result['content'];
        } else {
            $content = '';
        }

        return $this->parseContent($content);
    }

    /**
     * Парсит содержимое и извлекает секции с якорями.
     */
    public function parseContent(string $content): ParsedContent
    {
        // Разбиваем контент по якорям
        $sections = $this->extractSections($content);
        $anchors = $this->extractAnchors($content);

        return new ParsedContent(
            originalContent: $content,
            sections: $sections,
            anchors: $anchors,
        );
    }

    /**
     * Удаляет якоря из контента.
     */
    public function removeAnchors(string $content): string
    {
        $anchorPattern = '/<!-- SECTION_ANCHOR_[^>]+ -->\s*/';

        return trim(preg_replace($anchorPattern, '', $content) ?? '');
    }

    /**
     * Заменяет якоря на пользовательский контент.
     */
    public function replaceAnchors(string $content, array $replacements): string
    {
        foreach ($replacements as $anchorId => $replacement) {
            $anchorPattern = "/<!-- SECTION_ANCHOR_{$anchorId} -->/";
            $content = preg_replace($anchorPattern, $replacement, $content) ?? $content;
        }

        return $content;
    }

    /**
     * Извлекает секции из контента.
     *
     * @return array<Section>
     */
    private function extractSections(string $content): array
    {
        $sections = [];

        // Паттерн для разделения по маркерам блоков перевода
        $blockPattern = '/<!-- TRANSLATION_BLOCK_START type="([^"]+)" -->\n(.*?)\n<!-- TRANSLATION_BLOCK_END -->/s';

        // Разбиваем контент по блокам перевода
        $parts = preg_split($blockPattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) < 2) {
            // Если блоков перевода нет, возвращаем весь контент как одну секцию
            return [
                new Section(
                    id: 'main',
                    title: 'Документ',
                    originalContent: $content,
                    translatedContent: [],
                    risks: [],
                    anchor: null,
                ),
            ];
        }

        // Обрабатываем секции
        // parts[0] = оригинальный текст первой секции
        // parts[1] = тип блока перевода
        // parts[2] = содержимое блока перевода
        // parts[3] = оригинальный текст второй секции
        // parts[4] = тип блока перевода
        // parts[5] = содержимое блока перевода
        // и так далее...

        $sectionIndex = 0;

        for ($i = 0, $iMax = count($parts); $i < $iMax; $i += 3) {
            $originalText = $parts[$i] ?? '';
            $blockType = $parts[$i + 1] ?? '';
            $blockContent = $parts[$i + 2] ?? '';

            if (empty(trim($originalText))) {
                continue;
            }

            // Парсим перевод и риски из блока
            $translatedContent = [];
            $risks = [];

            if (!empty($blockContent)) {
                $parsed = $this->parseTranslationBlock(trim($blockContent), $blockType);
                $translatedContent = $parsed['translations'];
                $risks = $parsed['risks'];
            }

            $sections[] = new Section(
                id: 'section_' . $sectionIndex++,
                title: $this->extractTitleFromContent(trim($originalText)),
                originalContent: trim($originalText),
                translatedContent: $translatedContent,
                risks: $risks,
                anchor: null,
            );
        }

        return $sections;
    }

    /**
     * Парсит блок перевода и извлекает переводы и риски.
     *
     * @return array{translations: array<string>, risks: array<Risk>}
     */
    private function parseTranslationBlock(string $blockContent, string $blockType): array
    {
        $translations = [];
        $risks = [];

        // Извлекаем переводы (текст между **[Переведено]:**, **[Найден риск]:**, и т.д.)
        $translationPattern = '/\*\*\[Переведено\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/s';
        if (preg_match_all($translationPattern, $blockContent, $matches)) {
            foreach ($matches[1] as $translation) {
                $translations[] = trim($translation);
            }
        }

        // Если переводов не найдено, используем весь контент
        if (empty($translations)) {
            $translations[] = trim($blockContent);
        }

        // Извлекаем риски
        $riskPatterns = [
            'risk' => '/\*\*\[Найден риск\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/s',
            'warning' => '/\*\*\[Внимание\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/s',
            'contradiction' => '/\*\*\[Найдено противоречие\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/s',
        ];

        foreach ($riskPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $blockContent, $matches)) {
                foreach ($matches[1] as $riskText) {
                    $risks[] = new Risk(
                        type: $type,
                        text: trim($riskText),
                    );
                }
            }
        }

        return [
            'translations' => $translations,
            'risks' => $risks,
        ];
    }

    /**
     * Извлекает заголовок из текста (первая строка или начало).
     */
    private function extractTitleFromContent(string $content): string
    {
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Обрезаем если слишком длинный
                return mb_strlen($line) > 80 ? mb_substr($line, 0, 77) . '...' : $line;
            }
        }

        return 'Раздел';
    }

    /**
     * Извлекает якоря из контента.
     *
     * @return array<string>
     */
    private function extractAnchors(string $content): array
    {
        $anchorPattern = '/<!-- SECTION_ANCHOR_([^>]+) -->/';

        $result = preg_match_all($anchorPattern, $content, $matches);
        if ($result === false || $result === 0) {
            return [];
        }

        return array_values($matches[1]);
    }

    /**
     * Парсит отдельную секцию.
     */
    private function parseSection(string $anchorId, string $content): Section
    {
        // Извлекаем заголовок секции (обычно первая строка после якоря)
        $lines = explode("\n", $content);
        $title = $this->extractTitle($lines);

        // Разделяем оригинальный контент и переводы
        $originalContent = $this->extractOriginalContent($content);
        $translatedContent = $this->extractTranslatedContent($content);
        $risks = $this->extractRisks($content);

        return new Section(
            id: $anchorId,
            title: $title,
            originalContent: $originalContent,
            translatedContent: $translatedContent,
            risks: $risks,
            anchor: "<!-- SECTION_ANCHOR_{$anchorId} -->",
        );
    }

    /**
     * Извлекает заголовок секции.
     */
    private function extractTitle(array $lines): string
    {
        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Убираем markdown разметку заголовков
            if (preg_match('/^#+\s*(.+)/', $line, $matches)) {
                return trim($matches[1]);
            }

            // Если строка выглядит как заголовок (содержит цифры и точки в начале)
            if (preg_match('/^\d+\.?\s*(.+)/', $line, $matches)) {
                return trim($matches[1]);
            }

            // Возвращаем первую непустую строку как заголовок
            return $line;
        }

        return 'Без названия';
    }

    /**
     * Извлекает оригинальный контент (до переводов).
     */
    private function extractOriginalContent(string $content): string
    {
        // Ищем первый блок с переводом и берем все что до него
        $translationPattern = '/\*\*\[Переведено]:\*\*/u';
        $parts = preg_split($translationPattern, $content, 2);

        if ($parts === false || count($parts) < 2) {
            return trim($content);
        }

        return trim($parts[0]);
    }

    /**
     * Извлекает переведенный контент.
     *
     * @return array<string>
     */
    private function extractTranslatedContent(string $content): array
    {
        $translations = [];
        $pattern = '/\*\*\[Переведено]:\*\*\s*([^*]+?)(?=\*\*\[|$)/u';

        $result = preg_match_all($pattern, $content, $matches);
        if ($result === false || $result === 0) {
            return $translations;
        }

        foreach ($matches[1] as $translation) {
            $translations[] = trim($translation);
        }

        return $translations;
    }

    /**
     * Извлекает риски и противоречия.
     *
     * @return array<Risk>
     */
    private function extractRisks(string $content): array
    {
        $risks = [];

        // Ищем блоки с рисками
        $patterns = [
            'contradiction' => '/\*\*\[Найдено противоречие\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/s',
            'risk' => '/\*\*\[Найден риск\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/s',
            'warning' => '/\*\*\[Предупреждение\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/s',
        ];

        foreach ($patterns as $type => $pattern) {
            $result = preg_match_all($pattern, $content, $matches);
            if ($result === false || $result === 0) {
                continue;
            }

            foreach ($matches[1] as $riskText) {
                $risks[] = new Risk(
                    type: $type,
                    text: trim($riskText),
                );
            }
        }

        return $risks;
    }
}