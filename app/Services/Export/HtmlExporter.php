<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\Export\DTOs\ParsedContent;
use App\Services\Export\DTOs\Risk;
use App\Services\Export\DTOs\Section;

/**
 * Экспортер документов в HTML формат.
 */
final readonly class HtmlExporter
{
    // CSS константы для стилизации
    private const BORDER_COLOR = '#E0E0E0';
    private const BORDER_RADIUS = '8px';
    private const BACKGROUND_COLOR = '#F9F9F9';
    private const PADDING = '12px';
    private const MARGIN = '8px';

    // Цвета рисков
    private const RISK_CONTRADICTION_BORDER = '#FCA5A5';
    private const RISK_CONTRADICTION_BG = '#FEF2F2';
    private const RISK_DANGER_BORDER = '#FBBF24';
    private const RISK_DANGER_BG = '#FFFBEB';
    private const RISK_WARNING_BORDER = '#A3A3A3';
    private const RISK_WARNING_BG = '#F3F4F6';

    // Цвета текста
    private const PRIMARY_COLOR = '#2563EB';
    private const SECONDARY_COLOR = '#1e40af';
    private const TEXT_COLOR = '#374151';
    private const MUTED_COLOR = '#9ca3af';

    /**
     * Экспортирует документ в HTML.
     */
    public function export(ParsedContent $content, array $options = []): string
    {
        $includeOriginal = $options['include_original'] ?? true;
        $includeAnchors = $options['include_anchors'] ?? false;

        return $this->buildHtmlStructure($content, $includeOriginal, $includeAnchors);
    }

    /**
     * Строит HTML структуру документа.
     */
    private function buildHtmlStructure(ParsedContent $content, bool $includeOriginal, bool $includeAnchors): string
    {
        $title = $this->extractDocumentTitle($content);
        $sectionsHtml = $this->buildSectionsHtml($content->sections, $includeOriginal, $includeAnchors);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$title} - Перевод на простой язык</title>
                <style>
                    {$this->getCssStyles()}
                </style>
            </head>
            <body>
                <div class="container">
                    <header class="document-header">
                        <h1>{$title}</h1>
                        <p class="subtitle">Перевод на простой язык</p>
                    </header>

                    <main class="document-content">
                        {$sectionsHtml}
                    </main>

                    <footer class="document-footer">
                        <p>Документ переведен автоматически с помощью ИИ. Перевод носит информационный характер.</p>
                        <p class="generated-at">Сгенерировано: {date('d.m.Y H:i')}</p>
                    </footer>
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * Строит HTML для секций документа.
     */
    private function buildSectionsHtml(array $sections, bool $includeOriginal, bool $includeAnchors): string
    {
        $html = '';

        foreach ($sections as $section) {
            $html .= $this->buildSectionHtml($section, $includeOriginal, $includeAnchors);
        }

        return $html;
    }

    /**
     * Строит HTML для одной секции.
     */
    private function buildSectionHtml(Section $section, bool $includeOriginal, bool $includeAnchors): string
    {
        $sectionId = 'section-' . md5($section->id);
        $html = '';

        // Якорь (если включен)
        if ($includeAnchors && $section->anchor !== null) {
            $html .= "<div class=\"anchor-comment\">{$section->anchor}</div>\n";
        }

        // Заголовок секции
        $html .= "<section id=\"{$sectionId}\" class=\"document-section\">\n";
        $html .= "<h2 class=\"section-title\">{$this->escapeHtml($section->title)}</h2>\n";

        // Оригинальный контент (если включен)
        if ($includeOriginal && !empty($section->originalContent)) {
            $html .= "<div class=\"original-content\">\n";
            $html .= "<h3>Оригинальный текст:</h3>\n";
            $html .= '<div class="original-text">' . $this->formatText($section->originalContent) . "</div>\n";
            $html .= "</div>\n";
        }

        // Переводы
        if ($section->hasTranslations()) {
            foreach ($section->translatedContent as $index => $translation) {
                $html .= "<div class=\"translation-block\">\n";
                $html .= "<div class=\"translation-label\">Переведено:</div>\n";
                $html .= '<div class="translation-text">' . $this->formatText($translation) . "</div>\n";
                $html .= "</div>\n";
            }
        }

        // Риски и противоречия
        if ($section->hasRisks()) {
            foreach ($section->risks as $risk) {
                $html .= $this->buildRiskHtml($risk);
            }
        }

        $html .= "</section>\n\n";

        return $html;
    }

    /**
     * Строит HTML для риска/противоречия.
     */
    private function buildRiskHtml(Risk $risk): string
    {
        $cssClass = match ($risk->type) {
            'contradiction' => 'risk-block risk-contradiction',
            'risk' => 'risk-block risk-danger',
            'warning' => 'risk-block risk-warning',
            default => 'risk-block'
        };

        $label = match ($risk->type) {
            'contradiction' => 'Найдено противоречие:',
            'risk' => 'Найден риск:',
            'warning' => 'Предупреждение:',
            default => 'Внимание:'
        };

        return <<<HTML
            <div class="{$cssClass}">
                <div class="risk-label">{$label}</div>
                <div class="risk-text">{$this->formatText($risk->text)}</div>
            </div>

            HTML;
    }

    /**
     * Извлекает заголовок документа из первой секции.
     */
    private function extractDocumentTitle(ParsedContent $content): string
    {
        if (empty($content->sections)) {
            return 'Документ';
        }

        $firstSection = $content->sections[0];

        // Если заголовок первой секции выглядит как заголовок документа
        if (in_array(strtolower($firstSection->title), ['введение', 'без названия', 'main', 'intro'])) {
            return 'Документ';
        }

        return $firstSection->title;
    }

    /**
     * Форматирует текст для HTML (экранирование и базовое форматирование).
     */
    private function formatText(string $text): string
    {
        $text = $this->escapeHtml($text);

        // Заменяем переводы строк на <br>
        $text = nl2br($text);

        // Простое форматирование для жирного текста
        $result = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

        return $result ?? $text;
    }

    /**
     * Экранирует HTML.
     */
    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Возвращает CSS стили для HTML документа.
     */
    private function getCssStyles(): string
    {
        $borderColor = self::BORDER_COLOR;
        $borderRadius = self::BORDER_RADIUS;
        $backgroundColor = self::BACKGROUND_COLOR;
        $padding = self::PADDING;
        $margin = self::MARGIN;
        $primaryColor = self::PRIMARY_COLOR;
        $secondaryColor = self::SECONDARY_COLOR;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;
        $riskContradictionBorder = self::RISK_CONTRADICTION_BORDER;
        $riskContradictionBg = self::RISK_CONTRADICTION_BG;
        $riskDangerBorder = self::RISK_DANGER_BORDER;
        $riskDangerBg = self::RISK_DANGER_BG;
        $riskWarningBorder = self::RISK_WARNING_BORDER;
        $riskWarningBg = self::RISK_WARNING_BG;

        return <<<CSS
                    /* Базовые стили */
                    * {
                        box-sizing: border-box;
                    }

                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        margin: 0;
                        padding: 0;
                        background-color: #f5f5f5;
                    }

                    .container {
                        max-width: 800px;
                        margin: 0 auto;
                        padding: 20px;
                        background-color: white;
                        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
                        min-height: 100vh;
                    }

                    /* Заголовки */
                    .document-header {
                        text-align: center;
                        margin-bottom: 40px;
                        padding-bottom: 20px;
                        border-bottom: 2px solid #e0e0e0;
                    }

                    .document-header h1 {
                        color: {$primaryColor};
                        margin-bottom: 10px;
                        font-size: 2.2em;
                    }

                    .subtitle {
                        color: #666;
                        font-size: 1.1em;
                        margin: 0;
                    }

                    /* Секции */
                    .document-section {
                        margin-bottom: 30px;
                    }

                    .section-title {
                        color: {$secondaryColor};
                        font-size: 1.5em;
                        margin-bottom: 15px;
                        padding-bottom: 5px;
                        border-bottom: 1px solid {$borderColor};
                    }

                    /* Оригинальный контент */
                    .original-content {
                        margin-bottom: 20px;
                    }

                    .original-content h3 {
                        color: #4b5563;
                        font-size: 1.1em;
                        margin-bottom: 10px;
                    }

                    .original-text {
                        background-color: #f8f9fa;
                        border-left: 4px solid #6b7280;
                        padding: 15px;
                        margin-bottom: 15px;
                        font-style: italic;
                    }

                    /* Блоки переводов */
                    .translation-block {
                        border: 1px solid {$borderColor};
                        border-radius: {$borderRadius};
                        padding: {$padding};
                        background-color: {$backgroundColor};
                        margin: {$margin} 0;
                    }

                    .translation-label {
                        font-weight: 600;
                        color: {$primaryColor};
                        margin-bottom: 4px;
                        font-size: 0.9em;
                    }

                    .translation-text {
                        color: {$textColor};
                    }

                    /* Блоки рисков */
                    .risk-block {
                        border-radius: {$borderRadius};
                        padding: {$padding};
                        margin: {$margin} 0;
                    }

                    .risk-contradiction {
                        border: 1px solid {$riskContradictionBorder};
                        background-color: {$riskContradictionBg};
                    }

                    .risk-danger {
                        border: 1px solid {$riskDangerBorder};
                        background-color: {$riskDangerBg};
                    }

                    .risk-warning {
                        border: 1px solid {$riskWarningBorder};
                        background-color: {$riskWarningBg};
                    }

                    .risk-label {
                        font-weight: 600;
                        margin-bottom: 4px;
                        font-size: 0.9em;
                    }

                    .risk-contradiction .risk-label {
                        color: #DC2626;
                    }

                    .risk-danger .risk-label {
                        color: #D97706;
                    }

                    .risk-warning .risk-label {
                        color: #4B5563;
                    }

                    .risk-text {
                        color: {$textColor};
                    }

                    /* Якоры */
                    .anchor-comment {
                        color: {$mutedColor};
                        font-family: monospace;
                        font-size: 0.8em;
                        margin: 5px 0;
                    }

                    /* Футер */
                    .document-footer {
                        margin-top: 40px;
                        padding-top: 20px;
                        border-top: 1px solid #e0e0e0;
                        text-align: center;
                        color: #666;
                        font-size: 0.9em;
                    }

                    .generated-at {
                        font-size: 0.8em;
                        color: {$mutedColor};
                    }

                    /* Responsive */
                    @media (max-width: 768px) {
                        .container {
                            padding: 15px;
                        }

                        .document-header h1 {
                            font-size: 1.8em;
                        }

                        .section-title {
                            font-size: 1.3em;
                        }
                    }

                    /* Печать */
                    @media print {
                        body {
                            background-color: white;
                        }

                        .container {
                            box-shadow: none;
                            max-width: none;
                        }

                        .document-footer {
                            page-break-inside: avoid;
                        }
                    }
            CSS;
    }
}
