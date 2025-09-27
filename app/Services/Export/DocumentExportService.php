<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Models\DocumentExport;
use App\Models\DocumentProcessing;
use App\Models\User;
use App\Services\Export\Validators\ExportValidator;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Log;
use RuntimeException;

/**
 * Основной сервис экспорта документов в различные форматы.
 */
final readonly class DocumentExportService
{
    public function __construct(
        private ContentProcessor $contentProcessor,
        private HtmlExporter $htmlExporter,
        private DocxExporter $docxExporter,
        private PdfExporter $pdfExporter,
        private ExportValidator $validator,
    ) {
    }

    /**
     * Экспортирует документ в указанный формат.
     */
    public function export(DocumentProcessing $document, string $format, array $options = []): DocumentExport
    {
        // Используем общий валидатор
        $this->validator->validateDocumentForExport($document);
        $this->validator->validateFormat($format);

        if ($document->user !== null) {
            $this->validator->validateExportLimits($document->user);
        }

        // Проверяем, есть ли уже готовый экспорт
        $existingExport = $this->findExistingExport($document, $format);

        if ($existingExport !== null && !$existingExport->isExpired()) {
            return $existingExport;
        }

        // Парсим контент документа
        $parsedContent = $this->contentProcessor->parseDocumentResult($document);

        // Генерируем экспорт в зависимости от формата
        $content = match ($format) {
            DocumentExport::FORMAT_HTML => $this->htmlExporter->export($parsedContent, $options),
            DocumentExport::FORMAT_DOCX => $this->docxExporter->export($parsedContent, $options),
            DocumentExport::FORMAT_PDF => $this->pdfExporter->export($parsedContent, $options),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };

        // Проверяем размер сгенерированного контента
        if ($document->user !== null) {
            $this->validator->validateContentSize($content, $document->user);
        }

        // Сохраняем файл и создаем запись в БД
        return $this->saveExport($document, $format, $content, $options);
    }

    /**
     * Экспортирует документ в HTML.
     */
    public function exportToHtml(DocumentProcessing $document, array $options = []): string
    {
        $parsedContent = $this->contentProcessor->parseDocumentResult($document);

        return $this->htmlExporter->export($parsedContent, $options);
    }

    /**
     * Экспортирует документ в DOCX.
     */
    public function exportToDocx(DocumentProcessing $document, array $options = []): string
    {
        $parsedContent = $this->contentProcessor->parseDocumentResult($document);

        return $this->docxExporter->export($parsedContent, $options);
    }

    /**
     * Экспортирует документ в PDF.
     */
    public function exportToPdf(DocumentProcessing $document, array $options = []): string
    {
        $parsedContent = $this->contentProcessor->parseDocumentResult($document);

        return $this->pdfExporter->export($parsedContent, $options);
    }

    /**
     * Получает экспорт для скачивания по токену.
     */
    public function getExportByToken(string $token): ?DocumentExport
    {
        $export = DocumentExport::byToken($token)->active()->first();

        if ($export === null) {
            return null;
        }

        // Проверяем, существует ли файл
        if (!Storage::disk('local')->exists($export->file_path)) {
            $export->delete();

            return null;
        }

        return $export;
    }

    /**
     * Получает содержимое файла экспорта.
     */
    public function getExportContent(DocumentExport $export): string
    {
        if ($export->isExpired()) {
            throw new RuntimeException('Export has expired');
        }

        if (!Storage::disk('local')->exists($export->file_path)) {
            throw new RuntimeException('Export file not found');
        }

        $content = Storage::disk('local')->get($export->file_path);

        if ($content === null) {
            throw new RuntimeException('Failed to read export file');
        }

        return $content;
    }

    /**
     * Удаляет истекшие экспорты.
     */
    public function cleanupExpiredExports(): int
    {
        $expiredExports = DocumentExport::expired()->get();
        $cleanedCount = 0;

        foreach ($expiredExports as $export) {
            try {
                if (Storage::disk('local')->exists($export->file_path)) {
                    Storage::disk('local')->delete($export->file_path);
                }
                $export->delete();
                ++$cleanedCount;
            } catch (Exception $e) {
                // Логируем ошибку, но продолжаем очистку
                Log::error('Failed to cleanup expired export', [
                    'export_id' => $export->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cleanedCount;
    }

    /**
     * Ищет существующий экспорт.
     */
    private function findExistingExport(DocumentProcessing $document, string $format): ?DocumentExport
    {
        return $document->exports()
            ->byFormat($format)
            ->active()
            ->first();
    }

    /**
     * Сохраняет экспорт в файловую систему и создает запись в БД.
     */
    private function saveExport(DocumentProcessing $document, string $format, string $content, array $options): DocumentExport
    {
        // Создаем путь к файлу
        $directory = config('export.storage.directory', 'exports') . "/{$document->uuid}/{$format}";
        $filename = $this->generateFilename($document, $format);
        $filePath = "{$directory}/{$filename}";

        // Получаем диск для хранения
        /** @var string $disk */
        $disk = config('export.storage.disk', 'local');

        // Создаем директорию если не существует
        Storage::disk($disk)->makeDirectory($directory);

        // Сохраняем файл
        Storage::disk($disk)->put($filePath, $content);

        // Получаем размер файла
        $fileSize = Storage::disk($disk)->size($filePath);

        // Вычисляем время истечения на основе тарифного плана пользователя
        $expirationHours = $document->user !== null
            ? $this->getExpirationHours($document->user)
            : $this->getConfigInt('export.expiration.default', 24);

        // Создаем запись в БД
        return DocumentExport::create([
            'document_processing_id' => $document->id,
            'format' => $format,
            'filename' => $filename,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'download_token' => Str::random($this->getTokenLength()),
            'expires_at' => now()->addHours($expirationHours),
        ]);
    }

    /**
     * Генерирует имя файла для экспорта.
     */
    private function generateFilename(DocumentProcessing $document, string $format): string
    {
        $baseFilename = pathinfo($document->original_filename, PATHINFO_FILENAME);
        $baseFilename = Str::slug($baseFilename);

        $extension = match ($format) {
            DocumentExport::FORMAT_HTML => 'html',
            DocumentExport::FORMAT_DOCX => 'docx',
            DocumentExport::FORMAT_PDF => 'pdf',
            default => throw new InvalidArgumentException("Unknown format: {$format}")
        };

        $timestamp = now()->format('Y-m-d_H-i-s');

        return "{$baseFilename}_translated_{$timestamp}.{$extension}";
    }

    /**
     * Определяет время истечения экспорта на основе тарифного плана пользователя.
     */
    private function getExpirationHours(User $user): int
    {
        // Получаем роль пользователя для определения тарифного плана
        if ($user->hasRole('admin')) {
            return $this->getConfigInt('export.expiration.enterprise', 168);
        }

        if ($user->hasRole('pro')) {
            return $this->getConfigInt('export.expiration.pro', 72);
        }

        if ($user->hasRole('customer')) {
            return $this->getConfigInt('export.expiration.basic', 24);
        }

        // Для гостей или неопределенных ролей
        return $this->getConfigInt('export.expiration.guest', 1);
    }

    /**
     * Получает целочисленное значение из конфигурации.
     */
    private function getConfigInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    /**
     * Получает длину токена из конфигурации.
     */
    private function getTokenLength(): int
    {
        return $this->getConfigInt('export.security.token_length', 64);
    }
}
