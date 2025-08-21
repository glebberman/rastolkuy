<?php

declare(strict_types=1);

namespace App\Services\Structure\Validation;

use App\Services\Parser\Extractors\DTOs\ExtractedDocument;
use InvalidArgumentException;

final class InputValidator
{
    private const int MAX_DOCUMENT_SIZE_MB = 50;
    private const int MAX_ELEMENTS_COUNT = 10000;
    private const int MAX_TITLE_LENGTH = 1000;
    private const int MAX_ANCHOR_ID_LENGTH = 255;
    private const int MAX_TEXT_SEARCH_LENGTH = 1000000; // 1MB
    private const int MAX_REGEX_INPUT_LENGTH = 10000; // Maximum input length for regex operations

    /**
     * Валидация документа для анализа структуры.
     */
    public static function validateDocument(ExtractedDocument $document): void
    {
        if (empty($document->elements)) {
            throw new InvalidArgumentException('Document must contain at least one element');
        }

        if (count($document->elements) > self::MAX_ELEMENTS_COUNT) {
            throw new InvalidArgumentException(
                sprintf(
                    'Document contains too many elements: %d (max: %d)',
                    count($document->elements),
                    self::MAX_ELEMENTS_COUNT,
                ),
            );
        }

        $contentLength = mb_strlen($document->getPlainText());
        $maxSizeBytes = self::MAX_DOCUMENT_SIZE_MB * 1024 * 1024;

        if ($contentLength > $maxSizeBytes) {
            throw new InvalidArgumentException(
                sprintf(
                    'Document content too large: %d bytes (max: %d MB)',
                    $contentLength,
                    self::MAX_DOCUMENT_SIZE_MB,
                ),
            );
        }

        // Проверка на потенциально вредоносное содержимое
        $plainText = $document->getPlainText();

        if (self::containsSuspiciousContent($plainText)) {
            throw new InvalidArgumentException('Document contains suspicious content');
        }
    }

    /**
     * Валидация заголовка секции для генерации якоря.
     */
    public static function validateSectionTitle(string $title): void
    {
        if (empty(trim($title))) {
            throw new InvalidArgumentException('Section title cannot be empty');
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Section title too long: %d characters (max: %d)',
                    mb_strlen($title),
                    self::MAX_TITLE_LENGTH,
                ),
            );
        }

        // Проверка на потенциально опасные символы (только контрольные символы и теги)
        if (preg_match('/[<>\x00-\x1f\x7f]/', $title)) {
            throw new InvalidArgumentException('Section title contains invalid characters');
        }
    }

    /**
     * Валидация ID якоря.
     */
    public static function validateAnchorId(string $anchorId): void
    {
        if (empty(trim($anchorId))) {
            throw new InvalidArgumentException('Anchor ID cannot be empty');
        }

        if (mb_strlen($anchorId) > self::MAX_ANCHOR_ID_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Anchor ID too long: %d characters (max: %d)',
                    mb_strlen($anchorId),
                    self::MAX_ANCHOR_ID_LENGTH,
                ),
            );
        }

        // ID должен содержать только безопасные символы
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $anchorId)) {
            throw new InvalidArgumentException('Anchor ID can only contain letters, numbers, underscores and hyphens');
        }
    }

    /**
     * Валидация текста для поиска якорей.
     */
    public static function validateSearchText(string $text): void
    {
        if (mb_strlen($text) > self::MAX_TEXT_SEARCH_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Text too large for search: %d characters (max: %d)',
                    mb_strlen($text),
                    self::MAX_TEXT_SEARCH_LENGTH,
                ),
            );
        }
    }

    /**
     * Валидация уровня уверенности.
     */
    public static function validateConfidence(float $confidence): void
    {
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new InvalidArgumentException(
                sprintf('Confidence must be between 0.0 and 1.0, got: %f', $confidence),
            );
        }
    }

    /**
     * Валидация массива документов для батч-обработки.
     */
    public static function validateDocumentBatch(array $documents, int $maxBatchSize = 100): void
    {
        if (empty($documents)) {
            throw new InvalidArgumentException('Document batch cannot be empty');
        }

        if (count($documents) > $maxBatchSize) {
            throw new InvalidArgumentException(
                sprintf(
                    'Batch too large: %d documents (max: %d)',
                    count($documents),
                    $maxBatchSize,
                ),
            );
        }

        foreach ($documents as $key => $document) {
            if (!$document instanceof ExtractedDocument) {
                throw new InvalidArgumentException(
                    sprintf('Invalid document at key "%s": must be ExtractedDocument instance', $key),
                );
            }
        }
    }

    /**
     * Проверка на подозрительное содержимое.
     */
    private static function containsSuspiciousContent(string $content): bool
    {
        // Проверка на потенциально вредоносные паттерны
        $suspiciousPatterns = [
            '/<script[^>]*>.*?<\/script>/is',  // JavaScript
            '/javascript:/i',                  // JavaScript URLs
            '/data:text\/html/i',             // Data URLs
            '/vbscript:/i',                   // VBScript
            '/<iframe[^>]*>/i',               // iframes
            '/<object[^>]*>/i',               // Objects
            '/<embed[^>]*>/i',                // Embeds
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Валидирует паттерн regex перед выполнением для защиты от ReDoS
     */
    public static function validateRegexPattern(string $pattern): void
    {
        // Проверяем на известные проблемные конструкции
        $problematicPatterns = [
            '/\*\+/',          // Nested quantifiers: *+
            '/\+\*/',          // Nested quantifiers: +*
            '/\?\+/',          // Nested quantifiers: ?+
            '/\*\{/',          // Complex quantifiers: *{
            '/\+\{/',          // Complex quantifiers: +{
            '/\(\.\*\)\+/',    // Catastrophic backtracking: (.*)+
            '/\(\.\+\)\*/',    // Catastrophic backtracking: (.+)*
        ];

        foreach ($problematicPatterns as $problematic) {
            if (preg_match($problematic, $pattern)) {
                throw new InvalidArgumentException(
                    'Potentially unsafe regex pattern detected'
                );
            }
        }
    }

    /**
     * Безопасное выполнение regex с ограничением времени
     */
    public static function safeRegexMatch(string $pattern, string $subject, int $timeoutMs = 1000): array|false
    {
        self::validateRegexPattern($pattern);
        
        // Ограничиваем длину входной строки
        if (mb_strlen($subject) > self::MAX_REGEX_INPUT_LENGTH) {
            $subject = mb_substr($subject, 0, self::MAX_REGEX_INPUT_LENGTH);
        }

        // Выполняем с ограничением времени через PCRE settings
        $oldTimeLimit = ini_get('pcre.backtrack_limit');
        $oldRecursionLimit = ini_get('pcre.recursion_limit');
        
        // Устанавливаем более строгие лимиты
        ini_set('pcre.backtrack_limit', '100000');
        ini_set('pcre.recursion_limit', '100000');
        
        try {
            $result = @preg_match($pattern, $subject, $matches);
            
            // Проверяем на ошибки PCRE
            if ($result === false || preg_last_error() !== PREG_NO_ERROR) {
                return false;
            }
            
            // Если нет совпадений, возвращаем false
            if ($result === 0) {
                return false;
            }
            
            return $matches;
        } finally {
            // Восстанавливаем оригинальные лимиты
            ini_set('pcre.backtrack_limit', $oldTimeLimit);
            ini_set('pcre.recursion_limit', $oldRecursionLimit);
        }
    }
}
