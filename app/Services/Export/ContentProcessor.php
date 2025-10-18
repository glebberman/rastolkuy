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
            // Если блоков перевода нет, пробуем разбить по якорям (новый формат)
            $anchorSections = $this->extractSectionsByAnchors($content);
            if (count($anchorSections) > 1) {
                return $anchorSections;
            }

            // Если якорей тоже нет, возвращаем весь контент как одну секцию
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

        // Извлекаем переводы (текст между **[Переведено]:**, **[Найден риск]:**, и т.д.)
        $translationPattern = '/\*\*\[Переведено\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/us';
        if (preg_match_all($translationPattern, $blockContent, $matches)) {
            foreach ($matches[1] as $translation) {
                $translations[] = trim($translation);
            }
        }

        // Если переводов не найдено, используем весь контент
        if (empty($translations)) {
            $translations[] = trim($blockContent);
        }

        // Извлекаем риски (используем общий метод)
        $risks = $this->extractRisksFromContent($blockContent);

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
                // Убираем markdown заголовки (##)
                $line = preg_replace('/^#+\s*/', '', $line) ?? $line;

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
     * Извлекает секции по якорям (новый формат из FakeAdapter).
     *
     * @return array<Section>
     */
    private function extractSectionsByAnchors(string $content): array
    {
        // Разбиваем контент по якорям
        $anchorPattern = '/<!-- SECTION_ANCHOR_([^>]+) -->/';
        $parts = preg_split($anchorPattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) < 2) {
            return [];
        }

        $sections = [];

        // Определяем формат: якоря в начале или в конце секций
        // Если первый part пустой или очень короткий - якоря в начале (FakeAdapter)
        // Если первый part содержит контент - якоря в конце (тесты)
        $firstPart = trim($parts[0]);
        $anchorsAtStart = empty($firstPart) || mb_strlen($firstPart) < 10;

        if ($anchorsAtStart) {
            // Формат FakeAdapter: якоря в НАЧАЛЕ секций
            // parts[0] - пустой или очень короткий
            // parts[1] - ID первого якоря
            // parts[2] - контент первой секции
            // parts[3] - ID второго якоря
            // parts[4] - контент второй секции

            for ($i = 1, $iMax = count($parts); $i < $iMax; $i += 2) {
                $anchorId = $parts[$i] ?? '';
                $sectionContent = $parts[$i + 1] ?? '';

                if (empty($anchorId)) {
                    continue;
                }

                $parsed = $this->parseSectionContent(trim($sectionContent));

                $sections[] = new Section(
                    id: $anchorId,
                    title: !empty($parsed['title']) ? $parsed['title'] : 'Раздел',
                    originalContent: $parsed['original'],
                    translatedContent: $parsed['translations'],
                    risks: $parsed['risks'],
                    anchor: $anchorId,
                );
            }
        } else {
            // Формат тестов: якоря в КОНЦЕ секций
            // parts[0] - контент первой секции
            // parts[1] - ID якоря для первой секции
            // parts[2] - контент второй секции
            // parts[3] - ID якоря для второй секции

            for ($i = 0, $iMax = count($parts); $i < $iMax; $i += 2) {
                $sectionContent = $parts[$i] ?? '';
                $anchorId = $parts[$i + 1] ?? '';

                if (empty($anchorId)) {
                    continue;
                }

                $parsed = $this->parseSectionContent(trim($sectionContent));

                $sections[] = new Section(
                    id: $anchorId,
                    title: !empty($parsed['title']) ? $parsed['title'] : 'Раздел',
                    originalContent: $parsed['original'],
                    translatedContent: $parsed['translations'],
                    risks: $parsed['risks'],
                    anchor: $anchorId,
                );
            }
        }

        return $sections;
    }

    /**
     * Парсит контент секции (новый формат).
     *
     * @return array{title: string, original: string, translations: array<string>, risks: array<Risk>}
     */
    private function parseSectionContent(string $content): array
    {
        $title = $this->extractTitleFromContent($content);

        // Извлекаем переводы
        $translations = [];
        $translationPattern = '/\*\*\[Переведено\]:\*\*\s*([^*]+?)(?=\*\*\[|$)/us';
        if (preg_match_all($translationPattern, $content, $matches)) {
            foreach ($matches[1] as $translation) {
                $translations[] = trim($translation);
            }
        }

        // Извлекаем риски (используем общий метод)
        $risks = $this->extractRisksFromContent($content);

        // Оригинальный контент - это текст до первого маркера перевода/риска
        $original = preg_split('/\*\*\[(?:Переведено|Найден риск|Внимание|Предупреждение|Найдено противоречие)\]:\*\*/u', $content, 2)[0] ?? $content;

        return [
            'title' => $title,
            'original' => trim($original),
            'translations' => $translations,
            'risks' => $risks,
        ];
    }

    /**
     * Извлекает риски из контента (общий метод для устранения дублирования).
     *
     * @return array<Risk>
     */
    private function extractRisksFromContent(string $content): array
    {
        $risks = [];
        $riskPatterns = [
            'risk' => '/\*\*\[Найден риск\]:\*\*\s*(.+?)(?=\*\*\[|$)/s',
            'warning' => '/\*\*\[(?:Внимание|Предупреждение)\]:\*\*\s*(.+?)(?=\*\*\[|$)/s',
            'contradiction' => '/\*\*\[Найдено противоречие\]:\*\*\s*(.+?)(?=\*\*\[|$)/s',
        ];

        foreach ($riskPatterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $riskText) {
                    $risks[] = new Risk(
                        type: $type,
                        text: trim($riskText),
                    );
                }
            }
        }

        return $risks;
    }


}