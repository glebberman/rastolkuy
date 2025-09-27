<?php

declare(strict_types=1);

namespace App\Services\Export\Validators;

use App\Models\DocumentProcessing;
use App\Models\User;
use InvalidArgumentException;

/**
 * Общий валидатор для экспорта документов.
 */
final readonly class ExportValidator
{
    /**
     * Проверяет, что документ готов для экспорта.
     */
    public function validateDocumentForExport(DocumentProcessing $document): void
    {
        if (!$document->isCompleted()) {
            throw new InvalidArgumentException('Document must be completed to export');
        }

        if (empty($document->result)) {
            throw new InvalidArgumentException('Document must have processing result to export');
        }
    }

    /**
     * Проверяет поддерживаемый формат экспорта.
     */
    public function validateFormat(string $format): void
    {
        $supportedFormats = ['html', 'docx', 'pdf'];

        if (!in_array($format, $supportedFormats, true)) {
            throw new InvalidArgumentException(
                "Unsupported format: {$format}. Supported: " . implode(', ', $supportedFormats),
            );
        }

        // Проверяем, что формат включен в конфигурации
        /** @var array<string, mixed>|null $formatConfig */
        $formatConfig = config("export.formats.{$format}");

        if (!$formatConfig || !($formatConfig['enabled'] ?? false)) {
            throw new InvalidArgumentException("Format {$format} is currently disabled");
        }
    }

    /**
     * Проверяет размер экспортируемого контента.
     */
    public function validateContentSize(string $content, User $user): void
    {
        $fileSize = strlen($content);
        $maxSize = $this->getMaxFileSize($user);

        if ($fileSize > $maxSize) {
            throw new InvalidArgumentException(
                sprintf(
                    'Export file size (%s) exceeds limit (%s)',
                    $this->formatBytes($fileSize),
                    $this->formatBytes($maxSize),
                ),
            );
        }
    }

    /**
     * Проверяет лимиты экспорта для пользователя.
     */
    public function validateExportLimits(User $user): void
    {
        $hourlyLimit = $this->getHourlyLimit($user);
        $dailyLimit = $this->getDailyLimit($user);

        // Проверяем количество экспортов за последний час
        $hourlyCount = $user->documentProcessings()
            ->whereHas('exports', function ($query): void {
                $query->where('created_at', '>=', now()->subHour());
            })
            ->count();

        if ($hourlyCount >= $hourlyLimit) {
            throw new InvalidArgumentException(
                "Hourly export limit ({$hourlyLimit}) exceeded. Try again later.",
            );
        }

        // Проверяем количество экспортов за последний день
        $dailyCount = $user->documentProcessings()
            ->whereHas('exports', function ($query): void {
                $query->where('created_at', '>=', now()->subDay());
            })
            ->count();

        if ($dailyCount >= $dailyLimit) {
            throw new InvalidArgumentException(
                "Daily export limit ({$dailyLimit}) exceeded. Please upgrade your plan.",
            );
        }
    }

    /**
     * Определяет максимальный размер файла для пользователя.
     */
    private function getMaxFileSize(User $user): int
    {
        /** @var array<string, int> $limits */
        $limits = config('export.file_size.limits', []);
        /** @var int $defaultMaxSize */
        $defaultMaxSize = config('export.file_size.max_size', 50 * 1024 * 1024);

        if ($user->hasRole('admin')) {
            return (int) ($limits['enterprise'] ?? $defaultMaxSize);
        }

        if ($user->hasRole('pro')) {
            return (int) ($limits['pro'] ?? $defaultMaxSize);
        }

        if ($user->hasRole('customer')) {
            return (int) ($limits['basic'] ?? $defaultMaxSize);
        }

        return (int) ($limits['guest'] ?? (5 * 1024 * 1024));
    }

    /**
     * Получает лимит экспортов в час для пользователя.
     */
    private function getHourlyLimit(User $user): int
    {
        /** @var array<string, int> $limits */
        $limits = config('export.rate_limits.per_hour', []);

        if ($user->hasRole('admin')) {
            return (int) ($limits['enterprise'] ?? 500);
        }

        if ($user->hasRole('pro')) {
            return (int) ($limits['pro'] ?? 100);
        }

        if ($user->hasRole('customer')) {
            return (int) ($limits['basic'] ?? 20);
        }

        return (int) ($limits['guest'] ?? 3);
    }

    /**
     * Получает лимит экспортов в день для пользователя.
     */
    private function getDailyLimit(User $user): int
    {
        /** @var array<string, int> $limits */
        $limits = config('export.rate_limits.per_day', []);

        if ($user->hasRole('admin')) {
            return (int) ($limits['enterprise'] ?? 5000);
        }

        if ($user->hasRole('pro')) {
            return (int) ($limits['pro'] ?? 1000);
        }

        if ($user->hasRole('customer')) {
            return (int) ($limits['basic'] ?? 100);
        }

        return (int) ($limits['guest'] ?? 5);
    }

    /**
     * Форматирует размер файла в читаемый вид.
     */
    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            ++$unitIndex;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}
