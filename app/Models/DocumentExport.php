<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Модель для отслеживания экспортированных документов.
 *
 * @property int $id
 * @property int $document_processing_id ID связанной обработки документа
 * @property 'docx'|'html'|'pdf' $format Формат экспорта
 * @property string $filename Имя файла экспорта
 * @property string $file_path Путь к экспортированному файлу
 * @property int $file_size Размер файла в байтах
 * @property string $download_token Токен для безопасного скачивания
 * @property Carbon $expires_at Время истечения токена
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<DocumentExport> where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static DocumentExport create(array $attributes)
 * @method static \Database\Factories\DocumentExportFactory factory($count = null, $state = [])
 */
class DocumentExport extends Model
{
    use HasFactory;

    /**
     * Возможные форматы экспорта.
     */
    public const string FORMAT_HTML = 'html';
    public const string FORMAT_DOCX = 'docx';
    public const string FORMAT_PDF = 'pdf';

    protected $fillable = [
        'document_processing_id',
        'format',
        'filename',
        'file_path',
        'file_size',
        'download_token',
        'expires_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'file_path', // Скрываем внутренний путь к файлу
        'download_token', // Скрываем токен по умолчанию
    ];

    /**
     * Проверяет, истек ли срок действия экспорта.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Проверяет, доступен ли экспорт для скачивания.
     */
    public function isAvailableForDownload(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Генерирует новый токен скачивания.
     */
    public function generateDownloadToken(): string
    {
        $token = Str::random(64);
        $this->update(['download_token' => $token]);

        return $token;
    }

    /**
     * Устанавливает время истечения (по умолчанию 24 часа).
     */
    public function setExpirationTime(?Carbon $expiresAt = null): void
    {
        $this->update([
            'expires_at' => $expiresAt ?? now()->addDay(),
        ]);
    }

    /**
     * Получает размер файла в человекочитаемом формате.
     */
    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; ++$i) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Получает MIME-тип на основе формата.
     */
    public function getMimeType(): string
    {
        return match ($this->format) {
            self::FORMAT_HTML => 'text/html',
            self::FORMAT_DOCX => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            self::FORMAT_PDF => 'application/pdf',
        };
    }

    /**
     * Скоуп для получения активных (не истекших) экспортов.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Скоуп для получения истекших экспортов.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Скоуп для получения экспортов по формату.
     */
    public function scopeByFormat(Builder $query, string $format): Builder
    {
        return $query->where('format', $format);
    }

    /**
     * Скоуп для поиска по токену.
     */
    public function scopeByToken(Builder $query, string $token): Builder
    {
        return $query->where('download_token', $token);
    }

    /**
     * Получает связанную обработку документа.
     */
    public function documentProcessing(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessing::class);
    }
}
