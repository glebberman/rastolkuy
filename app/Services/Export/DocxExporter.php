<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\Export\DTOs\ParsedContent;
use App\Services\Export\DTOs\Risk;
use App\Services\Export\DTOs\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;

/**
 * Экспортер документов в DOCX формат.
 */
final readonly class DocxExporter
{
    /**
     * Экспортирует документ в DOCX.
     */
    public function export(ParsedContent $content, array $options = []): string
    {
        $includeOriginal = $options['include_original'] ?? true;
        $includeAnchors = $options['include_anchors'] ?? false;

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Устанавливаем стили
        $this->setDocumentStyles($phpWord);

        // Добавляем заголовок документа
        $this->addDocumentHeader($section, $content);

        // Добавляем секции
        $this->addSections($section, $content->sections, $includeOriginal, $includeAnchors);

        // Добавляем футер
        $this->addDocumentFooter($section);

        // Сохраняем в строку
        return $this->saveToString($phpWord);
    }

    /**
     * Устанавливает стили документа.
     */
    private function setDocumentStyles(PhpWord $phpWord): void
    {
        // Стиль заголовка документа
        $phpWord->addTitleStyle(1, [
            'name' => 'Times New Roman',
            'size' => 18,
            'bold' => true,
            'color' => '2563EB',
        ], [
            'alignment' => 'center',
            'spaceBefore' => 0,
            'spaceAfter' => 200,
        ]);

        // Стиль заголовка секции
        $phpWord->addTitleStyle(2, [
            'name' => 'Times New Roman',
            'size' => 14,
            'bold' => true,
            'color' => '1e40af',
        ], [
            'spaceBefore' => 200,
            'spaceAfter' => 100,
        ]);

        // Стиль заголовка подсекции
        $phpWord->addTitleStyle(3, [
            'name' => 'Times New Roman',
            'size' => 12,
            'bold' => true,
            'color' => '4b5563',
        ], [
            'spaceBefore' => 100,
            'spaceAfter' => 50,
        ]);

        // Стиль обычного текста
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        // Стиль для переводов
        $phpWord->addParagraphStyle('translation', [
            'spaceBefore' => 100,
            'spaceAfter' => 100,
            'borderSize' => 6,
            'borderColor' => 'E0E0E0',
            'shading' => [
                'pattern' => 'clear',
                'color' => 'F9F9F9',
            ],
        ]);

        // Стиль для рисков
        $phpWord->addParagraphStyle('risk', [
            'spaceBefore' => 100,
            'spaceAfter' => 100,
            'borderSize' => 6,
            'borderColor' => 'FCA5A5',
            'shading' => [
                'pattern' => 'clear',
                'color' => 'FEF2F2',
            ],
        ]);

        // Стиль для предупреждений
        $phpWord->addParagraphStyle('warning', [
            'spaceBefore' => 100,
            'spaceAfter' => 100,
            'borderSize' => 6,
            'borderColor' => 'FBBF24',
            'shading' => [
                'pattern' => 'clear',
                'color' => 'FFFBEB',
            ],
        ]);

        // Стиль для оригинального текста
        $phpWord->addParagraphStyle('original', [
            'spaceBefore' => 100,
            'spaceAfter' => 100,
            'shading' => [
                'pattern' => 'clear',
                'color' => 'F8F9FA',
            ],
        ]);
    }

    /**
     * Добавляет заголовок документа.
     */
    private function addDocumentHeader(\PhpOffice\PhpWord\Element\Section $section, ParsedContent $content): void
    {
        $title = $this->extractDocumentTitle($content);

        $section->addTitle($title, 1);
        $section->addText('Перевод на простой язык', [
            'name' => 'Times New Roman',
            'size' => 12,
            'italic' => true,
            'color' => '666666',
        ], [
            'alignment' => 'center',
            'spaceAfter' => 300,
        ]);
    }

    /**
     * Добавляет секции документа.
     */
    private function addSections(\PhpOffice\PhpWord\Element\Section $section, array $sections, bool $includeOriginal, bool $includeAnchors): void
    {
        foreach ($sections as $docSection) {
            $this->addSection($section, $docSection, $includeOriginal, $includeAnchors);
        }
    }

    /**
     * Добавляет одну секцию документа.
     */
    private function addSection(\PhpOffice\PhpWord\Element\Section $section, Section $docSection, bool $includeOriginal, bool $includeAnchors): void
    {
        // Якорь (если включен)
        if ($includeAnchors && $docSection->anchor !== null) {
            $section->addText($docSection->anchor, [
                'name' => 'Courier New',
                'size' => 8,
                'color' => '9CA3AF',
            ]);
        }

        // Заголовок секции
        $section->addTitle($docSection->title, 2);

        // Оригинальный контент (если включен)
        if ($includeOriginal && !empty($docSection->originalContent)) {
            $section->addTitle('Оригинальный текст:', 3);

            $textRun = $section->addTextRun('original');
            $textRun->addText($this->formatText($docSection->originalContent), [
                'italic' => true,
            ]);
        }

        // Переводы
        if ($docSection->hasTranslations()) {
            foreach ($docSection->translatedContent as $translation) {
                $textRun = $section->addTextRun('translation');
                $textRun->addText('Переведено: ', [
                    'bold' => true,
                    'color' => '2563EB',
                ]);
                $textRun->addText($this->formatText($translation));
            }
        }

        // Риски и противоречия
        if ($docSection->hasRisks()) {
            foreach ($docSection->risks as $risk) {
                $this->addRisk($section, $risk);
            }
        }
    }

    /**
     * Добавляет риск/противоречие.
     */
    private function addRisk(\PhpOffice\PhpWord\Element\Section $section, Risk $risk): void
    {
        $style = match ($risk->type) {
            'contradiction' => 'risk',
            'risk' => 'warning',
            'warning' => 'warning',
            default => 'warning'
        };

        $label = match ($risk->type) {
            'contradiction' => 'Найдено противоречие: ',
            'risk' => 'Найден риск: ',
            'warning' => 'Предупреждение: ',
            default => 'Внимание: '
        };

        $color = match ($risk->type) {
            'contradiction' => 'DC2626',
            'risk' => 'D97706',
            'warning' => '4B5563',
            default => '4B5563'
        };

        $textRun = $section->addTextRun($style);
        $textRun->addText($label, [
            'bold' => true,
            'color' => $color,
        ]);
        $textRun->addText($this->formatText($risk->text));
    }

    /**
     * Добавляет футер документа.
     */
    private function addDocumentFooter(\PhpOffice\PhpWord\Element\Section $section): void
    {
        $section->addTextBreak(2);

        $section->addText(
            'Документ переведен автоматически с помощью ИИ. Перевод носит информационный характер.',
            [
                'size' => 9,
                'italic' => true,
                'color' => '666666',
            ],
            [
                'alignment' => 'center',
                'borderTopSize' => 6,
                'borderTopColor' => 'E0E0E0',
                'spaceBefore' => 200,
            ],
        );

        $section->addText(
            'Сгенерировано: ' . date('d.m.Y H:i'),
            [
                'size' => 8,
                'color' => '9CA3AF',
            ],
            [
                'alignment' => 'center',
            ],
        );
    }

    /**
     * Извлекает заголовок документа.
     */
    private function extractDocumentTitle(ParsedContent $content): string
    {
        if (empty($content->sections)) {
            return 'Документ';
        }

        $firstSection = $content->sections[0];

        if (in_array(strtolower($firstSection->title), ['введение', 'без названия', 'main', 'intro'])) {
            return 'Документ';
        }

        return $firstSection->title;
    }

    /**
     * Форматирует текст для DOCX.
     */
    private function formatText(string $text): string
    {
        // Убираем лишние пробелы и переводы строк
        $text = trim($text);
        $result = preg_replace('/\s+/', ' ', $text);

        if ($result === null) {
            return $text;
        }

        return $result;
    }

    /**
     * Сохраняет документ в строку.
     */
    private function saveToString(PhpWord $phpWord): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'docx_export_');

        try {
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($tempFile);

            $content = file_get_contents($tempFile);

            if ($content === false) {
                throw new RuntimeException('Failed to read generated DOCX file');
            }

            return $content;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
