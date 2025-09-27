<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\Export\DTOs\ParsedContent;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/**
 * Экспортер документов в PDF формат.
 */
final readonly class PdfExporter
{
    public function __construct(
        private HtmlExporter $htmlExporter,
    ) {
    }

    /**
     * Экспортирует документ в PDF.
     */
    public function export(ParsedContent $content, array $options = []): string
    {
        // Сначала создаем HTML версию
        $html = $this->htmlExporter->export($content, $options);

        // Адаптируем HTML для PDF
        $pdfHtml = $this->adaptHtmlForPdf($html);

        // Генерируем PDF
        return $this->generatePdf($pdfHtml);
    }

    /**
     * Адаптирует HTML для лучшего отображения в PDF.
     */
    private function adaptHtmlForPdf(string $html): string
    {
        // Заменяем CSS стили на более подходящие для PDF
        $pdfCss = $this->getPdfCssStyles();

        // Заменяем блок стилей
        $html = preg_replace(
            '/<style>.*?<\/style>/s',
            "<style>\n{$pdfCss}\n</style>",
            $html,
        ) ?? $html;

        // Добавляем PDF-специфичные элементы
        $html = str_replace(
            '<body>',
            '<body><div class="pdf-wrapper">',
            $html,
        );

        return str_replace(
            '</body>',
            '</div></body>',
            $html,
        );
    }

    /**
     * Генерирует PDF из HTML.
     */
    private function generatePdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', sys_get_temp_dir());
        $options->set('fontDir', sys_get_temp_dir());
        $options->set('fontCache', sys_get_temp_dir());
        $options->set('chroot', sys_get_temp_dir());

        $dompdf = new Dompdf($options);

        // Устанавливаем размер бумаги и ориентацию
        $dompdf->setPaper('A4', 'portrait');

        // Загружаем HTML
        $dompdf->loadHtml($html);

        // Рендерим PDF
        $dompdf->render();

        // Возвращаем содержимое PDF
        $output = $dompdf->output();

        if ($output === null) {
            throw new RuntimeException('Failed to generate PDF');
        }

        return $output;
    }

    /**
     * Возвращает CSS стили, оптимизированные для PDF.
     */
    private function getPdfCssStyles(): string
    {
        return <<<'CSS'
                    /* PDF-оптимизированные стили */
                    @page {
                        margin: 2cm 1.5cm;
                        font-family: DejaVu Sans, sans-serif;
                    }

                    * {
                        box-sizing: border-box;
                    }

                    body {
                        font-family: DejaVu Sans, sans-serif;
                        font-size: 11pt;
                        line-height: 1.4;
                        color: #333;
                        margin: 0;
                        padding: 0;
                    }

                    .pdf-wrapper {
                        width: 100%;
                    }

                    /* Заголовки */
                    .document-header {
                        text-align: center;
                        margin-bottom: 20pt;
                        padding-bottom: 10pt;
                        border-bottom: 1pt solid #ccc;
                    }

                    .document-header h1 {
                        color: #2563EB;
                        margin-bottom: 5pt;
                        font-size: 18pt;
                        font-weight: bold;
                    }

                    .subtitle {
                        color: #666;
                        font-size: 12pt;
                        margin: 0;
                        font-style: italic;
                    }

                    /* Секции */
                    .document-section {
                        margin-bottom: 15pt;
                        page-break-inside: avoid;
                    }

                    .section-title {
                        color: #1e40af;
                        font-size: 14pt;
                        font-weight: bold;
                        margin-bottom: 8pt;
                        padding-bottom: 3pt;
                        border-bottom: 0.5pt solid #e0e0e0;
                    }

                    /* Оригинальный контент */
                    .original-content {
                        margin-bottom: 10pt;
                    }

                    .original-content h3 {
                        color: #4b5563;
                        font-size: 12pt;
                        font-weight: bold;
                        margin-bottom: 5pt;
                    }

                    .original-text {
                        background-color: #f8f9fa;
                        border-left: 2pt solid #6b7280;
                        padding: 8pt;
                        margin-bottom: 8pt;
                        font-style: italic;
                    }

                    /* Блоки переводов */
                    .translation-block {
                        border: 0.5pt solid #E0E0E0;
                        border-radius: 4pt;
                        padding: 8pt;
                        background-color: #F9F9F9;
                        margin: 5pt 0;
                        page-break-inside: avoid;
                    }

                    .translation-label {
                        font-weight: bold;
                        color: #2563EB;
                        margin-bottom: 3pt;
                        font-size: 10pt;
                    }

                    .translation-text {
                        color: #374151;
                    }

                    /* Блоки рисков */
                    .risk-block {
                        border-radius: 4pt;
                        padding: 8pt;
                        margin: 5pt 0;
                        page-break-inside: avoid;
                    }

                    .risk-contradiction {
                        border: 0.5pt solid #FCA5A5;
                        background-color: #FEF2F2;
                    }

                    .risk-danger {
                        border: 0.5pt solid #FBBF24;
                        background-color: #FFFBEB;
                    }

                    .risk-warning {
                        border: 0.5pt solid #A3A3A3;
                        background-color: #F3F4F6;
                    }

                    .risk-label {
                        font-weight: bold;
                        margin-bottom: 3pt;
                        font-size: 10pt;
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
                        color: #374151;
                    }

                    /* Якоря */
                    .anchor-comment {
                        color: #9ca3af;
                        font-family: monospace;
                        font-size: 8pt;
                        margin: 3pt 0;
                    }

                    /* Футер */
                    .document-footer {
                        margin-top: 20pt;
                        padding-top: 10pt;
                        border-top: 0.5pt solid #e0e0e0;
                        text-align: center;
                        color: #666;
                        font-size: 9pt;
                        page-break-inside: avoid;
                    }

                    .generated-at {
                        font-size: 8pt;
                        color: #9ca3af;
                        margin-top: 3pt;
                    }

                    /* Разрывы страниц */
                    .document-section {
                        page-break-inside: avoid;
                    }

                    .translation-block,
                    .risk-block {
                        page-break-inside: avoid;
                    }

                    /* Убираем элементы, не подходящие для печати */
                    .anchor-comment {
                        display: none;
                    }

                    /* Адаптируем цвета для монохромной печати */
                    @media print {
                        .translation-block {
                            background-color: #f5f5f5 !important;
                        }

                        .risk-block {
                            background-color: #f0f0f0 !important;
                        }
                    }
            CSS;
    }
}
